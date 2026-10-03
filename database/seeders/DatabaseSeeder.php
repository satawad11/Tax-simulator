<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The default entry point for `php artisan db:seed`.
 *
 * Milestone 09.1 split what this used to do into named groups:
 *
 *   ReferenceDataSeeder   every master row the application needs to function anywhere;
 *   TaxBaselineSeeder     a read-only check that the published 2568.1 baseline is intact;
 *   CmsInitialDataSeeder  the starting articles, guides, news and FAQs;
 *   Development*Seeder    known accounts and synthetic demo returns.
 *
 * The last two run only in an approved development environment. That keeps `db:seed` safe to
 * run anywhere, keeps the test suite's baseline exactly what it was before M9.1, and means
 * neither demo accounts nor sample copy can reach a deployment by accident. An operator who
 * does want the initial CMS content in a non-development environment asks for it explicitly:
 * `php artisan db:seed --class=CmsInitialDataSeeder`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            TaxBaselineSeeder::class,
        ]);

        if (! app()->environment('local')) {
            return;
        }

        $this->call([
            CmsInitialDataSeeder::class,
            DevelopmentAccountSeeder::class,
            DevelopmentDemoDataSeeder::class,
        ]);
    }
}
