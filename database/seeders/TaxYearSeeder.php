<?php

namespace Database\Seeders;

use App\Models\TaxYear;
use Illuminate\Database\Seeder;

class TaxYearSeeder extends Seeder
{
    public function run(): void
    {
        TaxYear::firstOrCreate(['year' => 2568], ['name' => 'ปีภาษี 2568', 'is_active' => true]);
    }
}
