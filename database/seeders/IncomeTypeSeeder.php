<?php

namespace Database\Seeders;

use App\Models\IncomeType;
use Illuminate\Database\Seeder;

class IncomeTypeSeeder extends Seeder
{
    public function run(): void
    {
        for ($section = 1; $section <= 8; $section++) {
            IncomeType::firstOrCreate(['code' => 'SECTION_40_'.$section], ['section_code' => '40('.$section.')', 'name' => 'เงินได้ตามมาตรา 40('.$section.')']);
        }
    }
}
