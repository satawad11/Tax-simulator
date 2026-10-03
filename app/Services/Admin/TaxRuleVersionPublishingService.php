<?php

namespace App\Services\Admin;

use App\Models\TaxRuleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a validated draft rule version.
 *
 * Milestone 08, and the one place a version's status becomes `published`.
 *
 * What publishing does **not** do is as important as what it does. It does not touch the
 * version it supersedes, it does not move any saved tax return onto the new version, and it
 * does not recalculate or rewrite a single stored snapshot. A member's completed simulation
 * stays tied to the rules it was calculated under, for as long as it exists.
 *
 * What changes is only which version `PublishedTaxRuleResolver` hands to a *new* calculation.
 */
class TaxRuleVersionPublishingService
{
    public function __construct(
        private AdminAuditService $audit,
        private TaxRuleVersionValidationService $validator,
    ) {}

    /** @return array{version: TaxRuleVersion, validation: array<string, mixed>} */
    public function publish(User $actor, TaxRuleVersion $version): array
    {
        return DB::transaction(function () use ($actor, $version): array {
            // Lock the row so two concurrent publishes cannot both see a draft.
            $locked = TaxRuleVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'RULE_VERSION_NOT_DRAFT: only a draft rule version can be published.',
                ]);
            }

            $validation = $this->validator->validate($locked);
            if ($validation['valid'] !== true) {
                $this->audit->record($actor, AdminAuditService::RULE_VERSION_VALIDATED, 'tax_rule_version', $locked->id,
                    'Publication refused: '.count($validation['errors']).' structural error(s) in '.$locked->version,
                    null, ['errors' => array_column($validation['errors'], 'code')]);
                throw ValidationException::withMessages([
                    'validation' => 'RULE_VERSION_INVALID: '.implode(', ', array_column($validation['errors'], 'code')),
                ]);
            }

            $superseded = $this->supersede($actor, $locked);

            $before = $locked->only(['status', 'published_at']);
            // The model itself allows draft -> published and stamps published_at; it is the
            // same guard that refuses every other write to a published version.
            $locked->status = 'published';
            $locked->save();

            $this->audit->record($actor, AdminAuditService::RULE_VERSION_PUBLISHED, 'tax_rule_version', $locked->id,
                'Published rule version '.$locked->version.'; existing returns keep their original version'
                    .($superseded === [] ? '' : '; superseded '.implode(', ', $superseded)),
                $before, $locked->only(['status', 'published_at']));

            return ['version' => $locked->fresh(), 'validation' => $validation];
        });
    }

    /**
     * Retires the tax year's previous published version, so exactly one stays selectable.
     *
     * `PublishedTaxRuleResolver` has refused a year with two published versions since M3, and
     * rightly: "which rules apply" must have one answer. Archiving is how the old answer steps
     * aside without being destroyed — its rules stay readable, and every saved return that
     * points at it keeps pointing at it and keeps resolving.
     *
     * @return list<string> the versions that stepped aside
     */
    private function supersede(User $actor, TaxRuleVersion $incoming): array
    {
        $superseded = [];
        $previous = TaxRuleVersion::where('tax_year_id', $incoming->tax_year_id)
            ->where('status', 'published')->whereKeyNot($incoming->id)->lockForUpdate()->get();

        foreach ($previous as $version) {
            // The model refuses a write to a published version, so this deliberate, audited
            // status transition goes through the query builder. No rule data is touched.
            DB::table('tax_rule_versions')->where('id', $version->id)
                ->update(['status' => 'archived', 'updated_at' => now()]);
            $this->audit->record($actor, AdminAuditService::RULE_VERSION_ARCHIVED, 'tax_rule_version', $version->id,
                'Superseded by '.$incoming->version.'; existing returns keep resolving this version',
                ['status' => 'published'], ['status' => 'archived']);
            $superseded[] = $version->version;
        }

        return $superseded;
    }

    /** @return array<string, mixed> */
    public function validate(User $actor, TaxRuleVersion $version): array
    {
        $validation = $this->validator->validate($version);
        $this->audit->record($actor, AdminAuditService::RULE_VERSION_VALIDATED, 'tax_rule_version', $version->id,
            'Validated rule version '.$version->version.': '
                .($validation['valid'] ? 'valid' : count($validation['errors']).' error(s)'),
            null, ['valid' => $validation['valid'], 'errors' => array_column($validation['errors'], 'code')]);

        return $validation;
    }
}
