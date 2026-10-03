<?php

namespace App\Services\Admin;

use App\Models\AllowanceCapGroup;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Copies a rule version's rule data into a new draft.
 *
 * Milestone 08. This is how a published version is changed: never in place, always by cloning
 * it into a draft, editing the draft, validating it and publishing that. The clone copies every
 * table the calculation baseline reads and nothing else — no member data, no tax return, no
 * calculation history, no scenario.
 *
 * Writes go through the query builder because the Eloquent models refuse any write whose
 * version is published, and the *source* here is normally published. The destination is always
 * a fresh draft, so the guarantee those models exist to protect is not weakened: this service
 * never writes a row belonging to a published version.
 */
class TaxRuleVersionCloneService
{
    /** Rule tables keyed by rule_version_id that the engine reads during a calculation. */
    private const VERSIONED_TABLES = ['tax_brackets', 'income_rules', 'expense_rules',
        'allowance_rules', 'income_exemption_rules', 'donation_rules', 'recommendation_rules'];

    public function __construct(private AdminAuditService $audit) {}

    /**
     * @param  int|null  $targetYear  the tax year to clone into, defaulting to the source's own.
     *
     * Milestone 09.1 added the target year. Carrying a baseline into the next tax year is the
     * ordinary way a new year begins — most years change a few rates and leave the rest — and
     * without this an administrator had to re-enter every bracket, expense rule and allowance by
     * hand. The copy is still rule data only: the destination year must already exist, with its
     * own forms, because a form belongs to the year rather than to a rule version.
     */
    public function clone(User $actor, TaxRuleVersion $source, string $version,
        ?string $description = null, ?int $targetYear = null): TaxRuleVersion
    {
        return DB::transaction(function () use ($actor, $source, $version, $description, $targetYear): TaxRuleVersion {
            $sourceYear = $source->taxYear;
            if (! $sourceYear instanceof TaxYear) {
                throw ValidationException::withMessages(['id' => 'The source version is not attached to a tax year.']);
            }

            $year = $targetYear === null ? $sourceYear : TaxYear::where('year', $targetYear)->first();
            if (! $year instanceof TaxYear) {
                throw ValidationException::withMessages([
                    'tax_year' => 'TAX_YEAR_NOT_FOUND: ไม่พบปีภาษีปลายทาง กรุณาสร้างปีภาษีก่อนทำสำเนา',
                ]);
            }
            if ($year->isNot($sourceYear) && $year->forms()->doesntExist()) {
                // Without forms the destination year cannot start a simulation, so a baseline
                // copied into it would be unreachable. Say so now rather than after the copy.
                throw ValidationException::withMessages([
                    'tax_year' => 'TAX_YEAR_HAS_NO_FORMS: ปีภาษีปลายทางยังไม่มีแบบภาษี '
                        .'กรุณาสร้างปีภาษีโดยคัดลอกโครงสร้างแบบฟอร์มก่อน',
                ]);
            }
            if (TaxRuleVersion::where('tax_year_id', $year->id)->where('version', $version)->exists()) {
                throw ValidationException::withMessages([
                    'version' => 'RULE_VERSION_DUPLICATE: this tax year already has a version with that identifier.',
                ]);
            }
            $draft = TaxRuleVersion::create([
                'tax_year_id' => $year->id, 'version' => $version, 'status' => 'draft',
                'description' => $description ?? 'Cloned from '.$source->version,
                'source_reference' => $source->source_reference,
                'effective_from' => $source->effective_from, 'effective_to' => $source->effective_to,
            ]);

            $expenseRuleMap = [];
            foreach (self::VERSIONED_TABLES as $table) {
                $map = $this->copyTable($table, $source->id, $draft->id, $year->id);
                if ($table === 'expense_rules') {
                    $expenseRuleMap = $map;
                }
            }
            $this->copyExpenseTiers($expenseRuleMap);
            $this->copyCapGroups($source->id, $draft->id, $year->id);
            $this->copyRuleSources($source->id, $draft->id);

            $this->audit->record($actor, AdminAuditService::RULE_VERSION_CLONED, 'tax_rule_version', $draft->id,
                'Cloned rule version '.$source->version.' into draft '.$version
                    .($year->isNot($sourceYear) ? ' for tax year '.$year->year : ''),
                ['version' => $source->version, 'status' => $source->status],
                ['version' => $draft->version, 'status' => $draft->status]);

            return $draft->fresh();
        });
    }

    /** @return array<int, int> old row id => new row id */
    private function copyTable(string $table, int $sourceVersionId, int $draftVersionId, int $yearId): array
    {
        $map = [];
        foreach (DB::table($table)->where('rule_version_id', $sourceVersionId)->orderBy('id')->get() as $row) {
            $values = (array) $row;
            $oldId = (int) $values['id'];
            unset($values['id']);
            $values['rule_version_id'] = $draftVersionId;
            if (array_key_exists('tax_year_id', $values)) {
                $values['tax_year_id'] = $yearId;
            }
            $values['created_at'] = now();
            $values['updated_at'] = now();
            $map[$oldId] = (int) DB::table($table)->insertGetId($values);
        }

        return $map;
    }

    /** @param array<int, int> $expenseRuleMap */
    private function copyExpenseTiers(array $expenseRuleMap): void
    {
        if ($expenseRuleMap === []) {
            return;
        }
        foreach (DB::table('expense_rule_tiers')->whereIn('expense_rule_id', array_keys($expenseRuleMap))
            ->orderBy('id')->get() as $tier) {
            $values = (array) $tier;
            unset($values['id']);
            $values['expense_rule_id'] = $expenseRuleMap[(int) $tier->expense_rule_id];
            $values['created_at'] = now();
            $values['updated_at'] = now();
            DB::table('expense_rule_tiers')->insert($values);
        }
    }

    private function copyCapGroups(int $sourceVersionId, int $draftVersionId, int $yearId): void
    {
        foreach (AllowanceCapGroup::where('rule_version_id', $sourceVersionId)->with('allowanceTypes')
            ->orderBy('id')->get() as $group) {
            $values = array_diff_key($group->getAttributes(), array_flip(['id', 'created_at', 'updated_at']));
            $values['rule_version_id'] = $draftVersionId;
            $values['tax_year_id'] = $yearId;
            $values['created_at'] = now();
            $values['updated_at'] = now();
            $newId = DB::table('allowance_cap_groups')->insertGetId($values);

            foreach ($group->allowanceTypes as $type) {
                DB::table('allowance_cap_group_members')->insert([
                    'allowance_cap_group_id' => $newId, 'allowance_type_id' => $type->id,
                    'sort_order' => $type->pivot->sort_order, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Evidence follows the rules it documents, so a cloned draft can be audited exactly like
     * the version it came from. Entity ids are not remapped here; the citation is re-pointed by
     * the admin as rules are edited, and the version-level link is what the UI reads.
     */
    private function copyRuleSources(int $sourceVersionId, int $draftVersionId): void
    {
        foreach (DB::table('tax_rule_sources')->where('rule_version_id', $sourceVersionId)->orderBy('id')->get() as $row) {
            $values = (array) $row;
            unset($values['id']);
            $values['rule_version_id'] = $draftVersionId;
            $values['created_at'] = now();
            $values['updated_at'] = now();
            DB::table('tax_rule_sources')->insertOrIgnore($values);
        }
    }
}
