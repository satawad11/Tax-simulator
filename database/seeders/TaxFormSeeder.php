<?php

namespace Database\Seeders;

use App\Models\TaxForm;
use App\Models\TaxYear;
use Illuminate\Database\Seeder;

class TaxFormSeeder extends Seeder
{
    public function run(): void
    {
        $year = TaxYear::where('year', 2568)->firstOrFail();

        foreach (['PND90' => 'ภ.ง.ด.90', 'PND91' => 'ภ.ง.ด.91'] as $code => $name) {
            TaxForm::firstOrCreate(['tax_year_id' => $year->id, 'code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
