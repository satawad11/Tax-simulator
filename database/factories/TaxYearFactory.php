<?php

namespace Database\Factories;

use App\Models\TaxYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxYear> */
class TaxYearFactory extends Factory
{
    protected $model = TaxYear::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'year' => fake()->unique()->numberBetween(2600, 60000),
            'name' => 'Synthetic test tax year',
            'is_active' => false,
        ];
    }
}
