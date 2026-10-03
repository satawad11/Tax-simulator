<?php

namespace App\Services\Tax;

final class IncomeTypePresentationCatalogue
{
    /**
     * Source-facing labels from the approved ภ.ง.ด.90/91 forms and instructions.
     *
     * @var array<string, array{plain_language_name: string, examples: string, form_sections: array<string, string>}>
     */
    private const PRESENTATION = [
        'SECTION_40_1' => [
            'plain_language_name' => 'เงินเดือน ค่าจ้าง โบนัส บำนาญ และเงินได้จากการจ้างแรงงาน',
            'examples' => 'ตัวอย่าง: เงินเดือน โบนัส ค่าจ้าง หรือบำนาญจากนายจ้าง',
            'form_sections' => ['PND90' => 'ข้อ 1 รายการเงินได้ตามมาตรา 40(1) และ 40(2)', 'PND91' => 'ข้อ ก เงินได้ตามมาตรา 40(1)'],
        ],
        'SECTION_40_2' => [
            'plain_language_name' => 'ค่าธรรมเนียม ค่านายหน้า เบี้ยประชุม และค่าตอบแทนจากหน้าที่หรือตำแหน่งงาน',
            'examples' => 'ตัวอย่าง: ค่านายหน้า เบี้ยประชุม หรือค่าธรรมเนียมจากการรับทำงานให้',
            'form_sections' => ['PND90' => 'ข้อ 1 รายการเงินได้ตามมาตรา 40(1) และ 40(2)'],
        ],
        'SECTION_40_3' => [
            'plain_language_name' => 'ค่าแห่งลิขสิทธิ์ กู๊ดวิลล์ สิทธิอย่างอื่น หรือเงินรายปี',
            'examples' => 'เลือกประเภทย่อยตามที่ปรากฏในข้อ 2 ของแบบ',
            'form_sections' => ['PND90' => 'ข้อ 2 รายการเงินได้ตามมาตรา 40(3)'],
        ],
        'SECTION_40_4' => [
            'plain_language_name' => 'ดอกเบี้ย เงินปันผล ส่วนแบ่งกำไร และเงินได้จากการลงทุน',
            'examples' => 'ตัวอย่าง: ดอกเบี้ย เงินปันผล หรือส่วนแบ่งกำไรจากกองทุนรวม',
            'form_sections' => ['PND90' => 'ข้อ 3 รายการเงินได้ตามมาตรา 40(4)'],
        ],
        'SECTION_40_5' => [
            'plain_language_name' => 'ค่าเช่าทรัพย์สินและเงินจากการผิดสัญญาเช่าซื้อหรือขายเงินผ่อน',
            'examples' => 'ตัวอย่าง: ค่าเช่าบ้าน ที่ดิน ยานพาหนะ หรือทรัพย์สินอื่น',
            'form_sections' => ['PND90' => 'ข้อ 4 รายการเงินได้ตามมาตรา 40(5)'],
        ],
        'SECTION_40_6' => [
            'plain_language_name' => 'เงินได้จากวิชาชีพอิสระ',
            'examples' => 'วิชากฎหมาย การประกอบโรคศิลปะ วิศวกรรม สถาปัตยกรรม การบัญชี หรือประณีตศิลปกรรม',
            'form_sections' => ['PND90' => 'ข้อ 5 รายการเงินได้ตามมาตรา 40(6)'],
        ],
        'SECTION_40_7' => [
            'plain_language_name' => 'เงินได้จากการรับเหมาที่ผู้รับเหมาจัดหาสัมภาระสำคัญ',
            'examples' => 'ใช้เมื่อผู้รับเหมาลงทุนจัดหาสัมภาระสำคัญนอกจากเครื่องมือ',
            'form_sections' => ['PND90' => 'ข้อ 6 รายการเงินได้ตามมาตรา 40(7)'],
        ],
        'SECTION_40_8' => [
            'plain_language_name' => 'เงินได้จากธุรกิจ การพาณิชย์ เกษตร อุตสาหกรรม ขนส่ง และเงินได้อื่น',
            'examples' => 'เลือกประเภทย่อยและกิจกรรมให้ตรงกับข้อ 7 และตารางที่ 2',
            'form_sections' => ['PND90' => 'ข้อ 7 รายการเงินได้ตามมาตรา 40(8)'],
        ],
    ];

    /** @return array{plain_language_name: string, examples: string, form_sections: array<string, string>} */
    public static function for(string $code): array
    {
        return self::PRESENTATION[$code] ?? [
            'plain_language_name' => $code,
            'examples' => '',
            'form_sections' => [],
        ];
    }
}
