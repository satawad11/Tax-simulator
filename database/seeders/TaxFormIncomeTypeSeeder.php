<?php

namespace Database\Seeders;

use App\Models\IncomeType;
use App\Models\TaxForm;
use App\Models\TaxFormIncomeType;
use Illuminate\Database\Seeder;

class TaxFormIncomeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $forms = TaxForm::whereHas('taxYear', fn ($query) => $query->where('year', 2568))
            ->whereIn('code', ['PND90', 'PND91'])->get();

        foreach ($forms as $form) {
            $codes = $form->code === 'PND91' ? ['SECTION_40_1'] : array_map(fn (int $section): string => 'SECTION_40_'.$section, range(1, 8));

            foreach (IncomeType::whereIn('code', $codes)->get() as $incomeType) {
                TaxFormIncomeType::firstOrCreate(['tax_form_id' => $form->id, 'income_type_id' => $incomeType->id]);
            }
        }
    }
}
