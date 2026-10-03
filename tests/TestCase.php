<?php

namespace Tests;

use App\Models\TaxRuleVersion;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The rule version a new calculation resolves to, for a tax year.
     *
     * A tax year holds more than one version as soon as a baseline is carried forward — the
     * retired predecessor keeps every rule it was published with, so a query for "the rule with
     * this code" now matches once per version. A test that means the live rule has to say so.
     */
    protected function publishedVersionId(int $year = 2568): int
    {
        return TaxRuleVersion::whereHas('taxYear', fn ($query) => $query->where('year', $year))
            ->where('status', 'published')->sole()->id;
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if (! ($connection === 'sqlite' && $database === ':memory:')
            && ! ($connection === 'mysql' && $database === 'tax_simulator_test')) {
            throw new LogicException('Tests may only use in-memory SQLite or the dedicated tax_simulator_test MySQL database.');
        }

        return $app;
    }
}
