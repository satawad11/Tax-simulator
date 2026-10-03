<?php

namespace Database\Seeders;

use App\Models\TaxSource;
use App\Models\TaxYear;
use Illuminate\Database\Seeder;

/**
 * Milestone 09.1 — the repository-approved source documents, as registry rows.
 *
 * These are the documents Milestones 04–07.5 actually read, and nothing else. The registry
 * records where a rule came from; it never establishes a rule, and no row here carries a rate,
 * a threshold or an amount. `file_path` is always a path inside this repository — M8 does not
 * fetch external URLs, and M9.1 does not add any.
 *
 * Rows are written with firstOrCreate keyed on `code`, so a source an administrator has already
 * registered or edited through the admin console keeps its own title and description.
 */
class TaxSourceSeeder extends Seeder
{
    public function run(): void
    {
        $taxYearId = TaxYear::where('year', 2568)->value('id');

        $sources = [
            [
                'code' => 'PND90_FORM_2568',
                'title' => 'แบบ ภ.ง.ด.90 ปีภาษี 2568',
                'source_type' => 'OFFICIAL_FORM',
                'file_path' => 'docs/tax-source/PND90-2568-form.pdf',
                'description' => 'แบบแสดงรายการภาษีเงินได้บุคคลธรรมดา ภ.ง.ด.90 สำหรับผู้มีเงินได้ตามมาตรา 40(1)–40(8)',
            ],
            [
                'code' => 'PND90_INSTRUCTIONS_2568',
                'title' => 'คำแนะนำการกรอกแบบ ภ.ง.ด.90 ปีภาษี 2568',
                'source_type' => 'FILING_INSTRUCTIONS',
                'file_path' => 'docs/tax-source/PND90-2568-filing-instructions.pdf',
                'description' => 'คำแนะนำการกรอกแบบ ภ.ง.ด.90 ใช้อ้างอิงรายการหักค่าใช้จ่ายและค่าลดหย่อนที่ระบบรองรับ',
            ],
            [
                'code' => 'PND91_FORM_2568',
                'title' => 'แบบ ภ.ง.ด.91 ปีภาษี 2568',
                'source_type' => 'OFFICIAL_FORM',
                'file_path' => 'docs/tax-source/PND91-2568-form.pdf',
                'description' => 'แบบแสดงรายการภาษีเงินได้บุคคลธรรมดา ภ.ง.ด.91 สำหรับผู้มีเงินได้จากการจ้างแรงงานตามมาตรา 40(1)',
            ],
            [
                'code' => 'PND91_INSTRUCTIONS_2568',
                'title' => 'คำแนะนำการกรอกแบบ ภ.ง.ด.91 ปีภาษี 2568',
                'source_type' => 'FILING_INSTRUCTIONS',
                'file_path' => 'docs/tax-source/PND91-2568-filing-instructions.pdf',
                'description' => 'คำแนะนำการกรอกแบบ ภ.ง.ด.91 ใช้อ้างอิงรายการหักค่าใช้จ่ายและค่าลดหย่อนที่ระบบรองรับ',
            ],
            [
                'code' => 'TAX_RATE_TABLE_2568',
                'title' => 'ตารางอัตราภาษีเงินได้บุคคลธรรมดา ปีภาษี 2568',
                'source_type' => 'ATTACHMENT',
                'file_path' => 'docs/tax-source/tax-rates-2568.jpg',
                'description' => 'ตารางอัตราภาษีแบบขั้นบันไดที่ใช้อ้างอิงขั้นภาษีของรุ่นกฎ 2568.1',
            ],
        ];

        foreach ($sources as $source) {
            TaxSource::firstOrCreate(
                ['code' => $source['code']],
                [...$source, 'tax_year_id' => $taxYearId, 'active' => true],
            );
        }
    }
}
