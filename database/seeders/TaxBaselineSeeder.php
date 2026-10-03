<?php

namespace Database\Seeders;

use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Milestone 09.1 — verification, not mutation.
 *
 * Milestone 07.5 closed the tax engine on published rule version 2568.1. This seeder therefore
 * writes nothing at all: it reads the baseline and fails loudly if the environment it was run
 * against does not hold the expected shape. Publishing, superseding and archiving remain the
 * business of TaxRuleVersionPublishingService, which is the only code allowed to move a version
 * between states, and no seeder may take that path.
 *
 * The invariant checked here is the one PublishedTaxRuleResolver depends on: a tax year has at
 * most one published rule version.
 */
class TaxBaselineSeeder extends Seeder
{
    /**
     * The version a new calculation resolves to.
     *
     * 2568.1 held this until 2568.3 carried it forward with ใบแนบ ข้อ 13, ข้อ 20 and ข้อ 22.1.
     * 2568.1 was archived rather than edited, and is verified below to still exist: every saved
     * return calculated under it keeps pointing at it and keeps resolving.
     */
    public const BASELINE_VERSION = '2568.3';

    public const PREDECESSOR_VERSION = '2568.1';

    public const BASELINE_YEAR = 2568;

    public function run(): void
    {
        $year = TaxYear::where('year', self::BASELINE_YEAR)->first();

        if ($year === null) {
            throw new RuntimeException('Tax year '.self::BASELINE_YEAR.' is missing. Run ReferenceDataSeeder first.');
        }

        $baseline = TaxRuleVersion::where('tax_year_id', $year->id)
            ->where('version', self::BASELINE_VERSION)->first();

        if ($baseline === null) {
            throw new RuntimeException('Rule version '.self::BASELINE_VERSION.' is missing. Run ReferenceDataSeeder first.');
        }

        /*
         * The predecessor must survive its own retirement. Archiving retires a version from new
         * calculations; a return calculated under it still resolves it, so its disappearance
         * would break history rather than tidy it.
         */
        $predecessor = TaxRuleVersion::where('tax_year_id', $year->id)
            ->where('version', self::PREDECESSOR_VERSION)->first();

        if ($predecessor === null || $predecessor->status === 'published') {
            throw new RuntimeException('Rule version '.self::PREDECESSOR_VERSION.' must exist and be retired, '
                .'not '.($predecessor === null ? 'missing' : $predecessor->status).'. Resolve this before seeding.');
        }

        $published = TaxRuleVersion::where('tax_year_id', $year->id)->where('status', 'published')->get();

        if ($published->count() !== 1 || ! $published->first()->is($baseline) || $baseline->status !== 'published') {
            throw new RuntimeException('Tax year '.self::BASELINE_YEAR.' has '.$published->count()
                .' published rule versions, and the sole published baseline must be '.self::BASELINE_VERSION
                .'. Resolve this before seeding.');
        }

        $this->command?->info('Tax baseline: rule version '.$baseline->version.' is "'.$baseline->status.'"'
            .' for tax year '.self::BASELINE_YEAR.'. No rule row was written.');
    }
}
