<?php

namespace Database\Factories;

use App\Models\TaxForm;
use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaxReturn> */
class TaxReturnFactory extends Factory
{
    protected $model = TaxReturn::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rule_version_id' => TaxRuleVersion::factory(),
            'tax_year_id' => fn (array $attributes) => TaxRuleVersion::findOrFail($attributes['rule_version_id'])->tax_year_id,
            'tax_form_id' => fn (array $attributes) => TaxForm::factory()->create(['tax_year_id' => $attributes['tax_year_id']])->id,
            'title' => 'Synthetic test return',
            'status' => 'draft',
        ];
    }
}
