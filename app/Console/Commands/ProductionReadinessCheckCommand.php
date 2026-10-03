<?php

namespace App\Console\Commands;

use App\Models\TaxYear;
use Database\Seeders\TaxBaselineSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('ops:production-readiness {--repository-only : Check code, migrations, database connectivity, and the published baseline without requiring production environment values}')]
#[Description('Run read-only production readiness checks without displaying secrets')]
class ProductionReadinessCheckCommand extends Command
{
    private const EXPECTED_BASELINE_COUNTS = [
        'brackets' => 8,
        'expenseRules' => 70,
        // 15 carried forward from the original baseline, plus the two ใบแนบ ข้อ 22.1 เมืองหลัก lines.
        'allowanceRules' => 17,
        'donationRules' => 2,
        'recommendationRules' => 4,
    ];

    public function handle(): int
    {
        $checks = [];
        $this->check($checks, 'PHP 8.4+', version_compare(PHP_VERSION, '8.4.0', '>='), PHP_VERSION);

        if (! $this->option('repository-only')) {
            $this->environmentChecks($checks);
        }

        $databaseReady = $this->databaseCheck($checks);
        if ($databaseReady) {
            $this->migrationCheck($checks);
            $this->baselineCheck($checks);
        }

        $this->table(['Check', 'Status', 'Detail'], $checks);
        $failed = array_filter($checks, fn (array $check): bool => $check[1] === 'FAIL');

        if ($failed !== []) {
            $this->error(count($failed).' production readiness check(s) failed. No mutation was performed.');

            return self::FAILURE;
        }

        $this->info('Production readiness checks passed. No mutation was performed.');

        return self::SUCCESS;
    }

    /** @param list<array{string, string, string}> $checks */
    private function environmentChecks(array &$checks): void
    {
        $this->check($checks, 'Application environment', app()->environment('production'),
            'expected production; actual '.app()->environment());
        $this->check($checks, 'Debug disabled', config('app.debug') === false,
            config('app.debug') === false ? 'disabled' : 'enabled');

        $url = (string) config('app.url');
        $this->check($checks, 'HTTPS application URL', str_starts_with(strtolower($url), 'https://'),
            parse_url($url, PHP_URL_SCHEME) ?: 'missing scheme');

        $key = (string) config('app.key');
        $this->check($checks, 'Application key present', trim($key) !== '' && strlen($key) >= 32,
            trim($key) === '' ? 'missing' : 'present (value hidden)');
        $this->check($checks, 'Secure session cookie', config('session.secure') === true,
            config('session.secure') === true ? 'enabled' : 'disabled');
        $this->check($checks, 'MySQL connection selected', config('database.default') === 'mysql',
            (string) config('database.default'));
    }

    /** @param list<array{string, string, string}> $checks */
    private function databaseCheck(array &$checks): bool
    {
        try {
            DB::select('SELECT 1');
            $this->check($checks, 'Database connectivity', true, (string) DB::connection()->getDriverName());

            return true;
        } catch (Throwable $exception) {
            $this->check($checks, 'Database connectivity', false, $exception::class);

            return false;
        }
    }

    /** @param list<array{string, string, string}> $checks */
    private function migrationCheck(array &$checks): void
    {
        if (! Schema::hasTable('migrations')) {
            $this->check($checks, 'Migrations current', false, 'migrations table missing');

            return;
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = collect(File::glob(database_path('migrations/*.php')))
            ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))->all();
        $pending = array_values(array_diff($files, $ran));
        $this->check($checks, 'Migrations current', $pending === [],
            $pending === [] ? count($files).' migration(s) applied' : implode(', ', $pending));
    }

    /** @param list<array{string, string, string}> $checks */
    private function baselineCheck(array &$checks): void
    {
        /*
         * The baseline is whichever version is published, not a fixed name. Naming one meant the
         * check reported a failure the moment that version was correctly retired in favour of a
         * successor — reading "FAIL: status archived" while the deployment was in fact healthy.
         * What matters is the invariant PublishedTaxRuleResolver depends on: exactly one.
         */
        $year = TaxYear::where('year', 2568)->first();
        $published = $year?->ruleVersions()->where('status', 'published')->get();
        $version = $published?->first();
        $publishedCount = $published?->count() ?? 0;
        $name = $version->version ?? TaxBaselineSeeder::BASELINE_VERSION;

        $this->check($checks, 'Published baseline for 2568', $publishedCount === 1,
            $version === null ? 'no published version' : "version {$name}; published versions {$publishedCount}");

        if ($version === null) {
            return;
        }

        foreach (self::EXPECTED_BASELINE_COUNTS as $relation => $expected) {
            $actual = $version->$relation()->count();
            $this->check($checks, "{$name} {$relation}", $actual === $expected, "expected {$expected}; actual {$actual}");
        }
    }

    /** @param list<array{string, string, string}> $checks */
    private function check(array &$checks, string $name, bool $passes, string $detail): void
    {
        $checks[] = [$name, $passes ? 'PASS' : 'FAIL', $detail];
    }
}
