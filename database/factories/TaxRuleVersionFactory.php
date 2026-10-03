<?php

namespace Database\Factories;

use App\Models\TaxRuleVersion;
use App\Models\TaxYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxRuleVersion> */
class TaxRuleVersionFactory extends Factory
{
    protected $model = TaxRuleVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tax_year_id' => TaxYear::factory(),
            'version' => fake()->unique()->bothify('test-????####'),
            'status' => 'draft',
        ];
    }
}
