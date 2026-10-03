<?php

namespace App\Services\Admin;

use App\Models\AllowanceCapGroup;
use App\Models\TaxRuleSource;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Draft rule-version lifecycle: create, edit metadata, archive, and the admin read model.
 *
 * Milestone 08. Publication lives in TaxRuleVersionPublishingService. Every write here goes
 * through the Eloquent model, whose ProtectsPublishedRules trait refuses any change to a
 * published version — so the immutability guarantee is enforced by the model layer, not by
 * this service remembering to check.
 */
class TaxRuleVersionService
{
    public function __construct(private AdminAuditService $audit, private TaxRuleVersionValidationService $validator) {}

    public function create(User $actor, int $taxYear, string $version, ?string $description = null): TaxRuleVersion
    {
        return DB::transaction(function () use ($actor, $taxYear, $version, $description): TaxRuleVersion {
            $year = TaxYear::where('year', $taxYear)->first();
            if (! $year) {
                throw ValidationException::withMessages(['tax_year' => 'Unknown tax year.']);
            }
            if (TaxRuleVersion::where('tax_year_id', $year->id)->where('version', $version)->exists()) {
                throw ValidationException::withMessages([
                    'version' => 'RULE_VERSION_DUPLICATE: this tax year already has a version with that identifier.',
                ]);
            }
            // Always a draft. A version is never born published — the model refuses that too.
            $draft = TaxRuleVersion::create(['tax_year_id' => $year->id, 'version' => $version,
                'status' => 'draft', 'description' => $description]);
            $this->audit->record($actor, AdminAuditService::RULE_VERSION_CREATED, 'tax_rule_version', $draft->id,
                'Created draft rule version '.$version.' for tax year '.$taxYear, null,
                ['version' => $version, 'status' => 'draft']);

            return $draft;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, TaxRuleVersion $version, array $data): TaxRuleVersion
    {
        $this->assertDraft($version);

        return DB::transaction(function () use ($actor, $version, $data): TaxRuleVersion {
            $before = $version->only(['version', 'description', 'status']);
            $version->fill(array_intersect_key($data, array_flip(['version', 'description',
                'source_reference', 'effective_from', 'effective_to'])))->save();
            $this->audit->record($actor, AdminAuditService::RULE_VERSION_CREATED, 'tax_rule_version', $version->id,
                'Updated draft rule version '.$version->version, $before,
                $version->only(['version', 'description', 'status']));

            return $version->fresh();
        });
    }

    /**
     * Archiving takes a version out of selection for new calculations while leaving every
     * historical return readable. A version anything points at is never deleted.
     */
    public function archive(User $actor, TaxRuleVersion $version): TaxRuleVersion
    {
        return DB::transaction(function () use ($actor, $version): TaxRuleVersion {
            if ($version->status === 'archived') {
                return $version;
            }
            $before = $version->only(['status']);
            // The model refuses a write to a published version, so archiving one is done
            // through the query builder — a deliberate, audited transition, not an edit of
            // any rule data. Nothing about the version's rules changes.
            DB::table('tax_rule_versions')->where('id', $version->id)
                ->update(['status' => 'archived', 'updated_at' => now()]);
            $version->refresh();
            $this->audit->record($actor, AdminAuditService::RULE_VERSION_ARCHIVED, 'tax_rule_version', $version->id,
                'Archived rule version '.$version->version, $before, ['status' => 'archived']);

            return $version;
        });
    }

    /** True while a saved return, calculation or scenario still points at this version. */
    public function isReferenced(TaxRuleVersion $version): bool
    {
        return $version->taxReturns()->exists()
            || $version->calculations()->exists()
            || DB::table('tax_scenarios')->where('rule_version_id', $version->id)->exists();
    }

    public function assertDraft(TaxRuleVersion $version): void
    {
        if ($version->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'RULE_VERSION_NOT_DRAFT: only a draft rule version can be edited. Clone it to a new draft instead.',
            ]);
        }
    }

    /**
     * The admin read model: what this version holds, and how much of it is evidenced.
     *
     * @return array<string, mixed>
     */
    public function summary(TaxRuleVersion $version): array
    {
        $counts = [
            'tax_brackets' => $version->brackets()->count(),
            'income_rules' => $version->incomeRules()->count(),
            'expense_rules' => $version->expenseRules()->count(),
            'allowance_rules' => $version->allowanceRules()->count(),
            'allowance_cap_groups' => AllowanceCapGroup::where('rule_version_id', $version->id)->count(),
            'donation_rules' => $version->donationRules()->count(),
            'recommendation_rules' => $version->recommendationRules()->count(),
        ];
        $cited = TaxRuleSource::where('rule_version_id', $version->id)->count();

        return [
            'counts' => $counts,
            'source_coverage' => ['citations' => $cited,
                'distinct_sources' => TaxRuleSource::where('rule_version_id', $version->id)
                    ->distinct()->count('tax_source_id')],
            'referenced_by_history' => $this->isReferenced($version),
            'validation' => $version->status === 'draft'
                ? $this->validator->validate($version)
                : ['valid' => null, 'errors' => [], 'warnings' => []],
        ];
    }
}
