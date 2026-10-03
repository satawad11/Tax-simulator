<?php

namespace Database\Seeders;

use App\Models\TaxRuleVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rule version 2568.3 — 2568.1 plus the ใบแนบ lines this baseline can now calculate.
 *
 * 2568.1 stays exactly as it was published and is archived rather than edited, because a rule
 * change is a new version. That is the guarantee `ProtectsPublishedRules` keeps at the model
 * level, and the reason a member's saved return goes on resolving the version it was calculated
 * under after this seeder runs: archiving retires a version from *new* calculations and destroys
 * nothing.
 *
 * What 2568.3 adds, and only this:
 *   - ใบแนบ ข้อ 13  — ค่าซื้อและค่าติดตั้งระบบกล้องโทรทัศน์วงจรปิด (เงินได้ที่ได้รับยกเว้น)
 *   - ใบแนบ ข้อ 20  — ค่าจ้างก่อสร้างอาคารเพื่ออยู่อาศัยขึ้นใหม่ (เงินได้ที่ได้รับยกเว้น)
 *   - ใบแนบ ข้อ 22.1 — ค่าท่องเที่ยวเมืองหลัก, two ลดหย่อน lines as the booklet grants them
 *
 * Every other rule is carried across unchanged, so no existing figure moves. The M4 regression
 * case — no family, net 620,000, tax 45,500 — is identical on both versions, and a test asserts it.
 */
class TaxRuleVersion2568_3Seeder extends Seeder
{
    public const VERSION = '2568.3';

    public const PREDECESSOR = '2568.1';

    public const DESCRIPTION = 'ใบแนบ ข้อ 13, ข้อ 20 (เงินได้ที่ได้รับยกเว้นหลังหักค่าใช้จ่าย) และ ข้อ 22.1 เมืองหลัก';

    /** Rule tables keyed by rule_version_id that the engine reads during a calculation. */
    private const VERSIONED_TABLES = ['tax_brackets', 'income_rules', 'expense_rules',
        'allowance_rules', 'income_exemption_rules', 'donation_rules', 'recommendation_rules'];

    public function run(): void
    {
        DB::transaction(function (): void {
            $year = DB::table('tax_years')->where('year', 2568)->first();
            if (! $year) {
                throw new RuntimeException('Tax year 2568 is missing. Run TaxYearSeeder first.');
            }
            $source = DB::table('tax_rule_versions')->where('tax_year_id', $year->id)
                ->where('version', self::PREDECESSOR)->lockForUpdate()->first();
            if (! $source) {
                throw new RuntimeException('Rule version '.self::PREDECESSOR.' must exist before '.self::VERSION.'.');
            }

            $draft = $this->draft($year->id, $source);
            $pending = $draft->status !== 'published';

            /*
             * Carrying forward and publishing happen once; seeding this version's own rules runs
             * every time. Returning early on "already published" left a version whose rules had
             * been lost — to a rolled-back migration, or a partial restore — published and empty,
             * with no command able to put them back. The rule seeders are idempotent and refuse
             * to overwrite a differing value, so running them always is safe and repairing.
             */
            if ($pending) {
                $this->carryForward($source->id, $draft->id, $year->id);
            }
            $this->call([Pnd90IncomeExemptionRuleSeeder::class, DomesticTravelAllowanceRuleSeeder::class]);
            if ($pending) {
                $this->publish($draft->id, $source);
            }
        });
    }

    private function draft(int $yearId, object $source): object
    {
        $existing = DB::table('tax_rule_versions')->where('tax_year_id', $yearId)
            ->where('version', self::VERSION)->lockForUpdate()->first();

        if ($existing) {
            /*
             * A 2568.3 that this seeder did not write is not ours to adopt. Silently filling an
             * unrelated draft with these rules — or publishing it — would put rules nobody
             * reviewed in front of every filer, so say what is there and stop.
             */
            if ($existing->status !== 'published' && $existing->description !== self::DESCRIPTION) {
                throw new RuntimeException('A different rule version '.self::VERSION.' already exists ("'
                    .$existing->description.'"). Rename or remove that draft before seeding this one.');
            }

            return $existing;
        }

        // Eloquent, so the model's own guard applies: a version is created as a draft and can
        // never be created already published.
        $draft = TaxRuleVersion::create(['tax_year_id' => $yearId, 'version' => self::VERSION,
            'status' => 'draft', 'description' => self::DESCRIPTION,
            'source_reference' => $source->source_reference,
            'effective_from' => $source->effective_from, 'effective_to' => $source->effective_to]);

        return DB::table('tax_rule_versions')->where('id', $draft->id)->first();
    }

    /** Copies every rule the predecessor holds, unchanged, into the draft. */
    private function carryForward(int $sourceId, int $draftId, int $yearId): void
    {
        $expenseRuleMap = [];
        foreach (self::VERSIONED_TABLES as $table) {
            if (DB::table($table)->where('rule_version_id', $draftId)->exists()) {
                continue;
            }
            foreach (DB::table($table)->where('rule_version_id', $sourceId)->orderBy('id')->get() as $row) {
                $values = (array) $row;
                $oldId = (int) $values['id'];
                unset($values['id']);
                $values['rule_version_id'] = $draftId;
                $values['tax_year_id'] = $yearId;
                $values['created_at'] = now();
                $values['updated_at'] = now();
                $newId = (int) DB::table($table)->insertGetId($values);
                if ($table === 'expense_rules') {
                    $expenseRuleMap[$oldId] = $newId;
                }
            }
        }
        $this->carryTiers($expenseRuleMap);
        $this->carryCapGroups($sourceId, $draftId, $yearId);
    }

    /** @param array<int, int> $expenseRuleMap */
    private function carryTiers(array $expenseRuleMap): void
    {
        foreach ($expenseRuleMap as $oldId => $newId) {
            foreach (DB::table('expense_rule_tiers')->where('expense_rule_id', $oldId)->orderBy('id')->get() as $tier) {
                $values = (array) $tier;
                unset($values['id']);
                $values['expense_rule_id'] = $newId;
                $values['created_at'] = now();
                $values['updated_at'] = now();
                DB::table('expense_rule_tiers')->insert($values);
            }
        }
    }

    private function carryCapGroups(int $sourceId, int $draftId, int $yearId): void
    {
        if (DB::table('allowance_cap_groups')->where('rule_version_id', $draftId)->exists()) {
            return;
        }
        foreach (DB::table('allowance_cap_groups')->where('rule_version_id', $sourceId)->orderBy('id')->get() as $group) {
            $values = (array) $group;
            $oldId = (int) $values['id'];
            unset($values['id']);
            $values['rule_version_id'] = $draftId;
            $values['tax_year_id'] = $yearId;
            $values['created_at'] = now();
            $values['updated_at'] = now();
            $newId = (int) DB::table('allowance_cap_groups')->insertGetId($values);
            foreach (DB::table('allowance_cap_group_members')->where('allowance_cap_group_id', $oldId)->get() as $member) {
                $row = (array) $member;
                unset($row['id']);
                $row['allowance_cap_group_id'] = $newId;
                $row['created_at'] = now();
                $row['updated_at'] = now();
                DB::table('allowance_cap_group_members')->insert($row);
            }
        }
    }

    /**
     * Publishes the draft and retires its predecessor, so exactly one version stays selectable —
     * the invariant `PublishedTaxRuleResolver` has depended on since M3.
     *
     * The status transitions go through the query builder for the same reason
     * TaxRuleVersionPublishingService does it: the models refuse a write to a published version,
     * and this is the deliberate transition that guard is designed to make explicit rather than
     * accidental. No rule row is touched, and nothing pointing at 2568.1 stops resolving.
     */
    private function publish(int $draftId, object $source): void
    {
        DB::table('tax_rule_versions')->where('id', $draftId)
            ->update(['status' => 'published', 'published_at' => now(), 'updated_at' => now()]);

        /*
         * Every other published version of this year steps aside, not merely the named
         * predecessor. Retiring only the predecessor would leave any *other* published version
         * in place — and a year with two published versions is exactly the state
         * PublishedTaxRuleResolver refuses, so every calculation would fail with a 409 until
         * someone noticed. Archiving destroys nothing: the rules stay readable and every saved
         * return that points at one goes on resolving it.
         */
        DB::table('tax_rule_versions')
            ->where('tax_year_id', $source->tax_year_id)
            ->where('status', 'published')->where('id', '!=', $draftId)
            ->update(['status' => 'archived', 'updated_at' => now()]);
    }
}
