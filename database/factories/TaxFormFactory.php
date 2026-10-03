<?php

namespace Database\Factories;

use App\Models\TaxForm;
use App\Models\TaxYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxForm> */
class TaxFormFactory extends Factory
{
    protected $model = TaxForm::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tax_year_id' => TaxYear::factory(),
            'code' => fake()->unique()->bothify('TEST_????####'),
            'name' => 'Synthetic test form',
            'is_active' => true,
        ];
    }
}
