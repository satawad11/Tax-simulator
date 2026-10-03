<?php

namespace Database\Seeders;

use App\Models\ContentCategory;
use App\Models\ContentPost;
use App\Models\ContentTag;
use App\Models\TaxYear;
use App\Services\Content\ContentBodyFormat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Milestone 09.1 — the initial CMS content a fresh environment needs to look like a working site.
 *
 * Two rules shaped every string below.
 *
 * First, no content here states a tax rule. The project standard is that only a
 * repository-approved source document may justify a number, and a seeded article is not such a
 * document. So the copy explains how the *simulator* works — what to prepare, what a result
 * status means, what a warning means, how a scenario differs from the saved return — and points
 * the reader at the calculation itself for any amount. There is no rate, no threshold, no
 * deadline and no legal claim in this file.
 *
 * Second, a body is structured plain text, never HTML (see ContentBodyFormat). Every body is
 * asserted against that format before it is written, so a seeded row can never be the one row
 * that bypasses the rule the API enforces.
 *
 * Idempotency: categories, tags and posts are written with firstOrCreate keyed on their slug,
 * and publication state is set only on the row this seeder actually created. An administrator
 * who later edits, unpublishes or re-files a seeded post keeps that change across reruns.
 */
class CmsInitialDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->categories();
        $tags = $this->tags();
        $taxYearId = TaxYear::where('year', 2568)->value('id');

        foreach ($this->posts() as $post) {
            $this->seedPost($post, $categories, $tags, $taxYearId);
        }
    }

    /** @return array<string, int> slug => id */
    private function categories(): array
    {
        $rows = [
            ['slug' => 'tax-knowledge', 'name' => 'ความรู้ภาษี', 'sort_order' => 1,
                'description' => 'บทความพื้นฐานสำหรับทำความเข้าใจภาษีเงินได้บุคคลธรรมดา'],
            ['slug' => 'user-guide', 'name' => 'คู่มือการใช้งาน', 'sort_order' => 2,
                'description' => 'วิธีใช้งานระบบจำลองภาษีตั้งแต่เตรียมข้อมูลจนถึงอ่านผล'],
            ['slug' => 'tax-news', 'name' => 'ข่าวสารภาษี', 'sort_order' => 3,
                'description' => 'ความเคลื่อนไหวและประกาศที่เกี่ยวข้องกับระบบจำลองภาษี'],
            ['slug' => 'tax-planning', 'name' => 'การวางแผนภาษี', 'sort_order' => 4,
                'description' => 'แนวคิดการทดลองสถานการณ์และเปรียบเทียบผลก่อนตัดสินใจ'],
            ['slug' => 'faq', 'name' => 'คำถามที่พบบ่อย', 'sort_order' => 5,
                'description' => 'คำถามที่ผู้ใช้ถามบ่อยเกี่ยวกับการใช้งานระบบ'],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $ids[$row['slug']] = ContentCategory::firstOrCreate(
                ['slug' => $row['slug']],
                [...$row, 'active' => true],
            )->id;
        }

        return $ids;
    }

    /** @return array<string, int> slug => id */
    private function tags(): array
    {
        $rows = [
            'pnd90' => 'ภ.ง.ด.90',
            'pnd91' => 'ภ.ง.ด.91',
            'allowance' => 'ค่าลดหย่อน',
            'income' => 'เงินได้',
            'withholding-tax' => 'ภาษีหัก ณ ที่จ่าย',
            'tax-refund' => 'เงินคืนภาษี',
            'tax-planning' => 'วางแผนภาษี',
            'tax-year-2568' => 'ปีภาษี 2568',
        ];

        $ids = [];
        foreach ($rows as $slug => $name) {
            $ids[$slug] = ContentTag::firstOrCreate(['slug' => $slug], ['name' => $name, 'active' => true])->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array<string, int>  $categories
     * @param  array<string, int>  $tags
     */
    private function seedPost(array $post, array $categories, array $tags, ?int $taxYearId): void
    {
        if (! ContentBodyFormat::isSafe($post['content'])) {
            // A seeded body that is not plain structured text would be the one row that bypasses
            // the rule the write API enforces. Fail rather than write it.
            throw new RuntimeException('Seeded content body for "'.$post['slug'].'" is not valid structured text.');
        }

        DB::transaction(function () use ($post, $categories, $tags, $taxYearId): void {
            if (ContentPost::withTrashed()->where('slug', $post['slug'])->exists()) {
                // The row exists — possibly edited, re-filed, unpublished or deleted by an
                // administrator. Seeding never overrules that.
                return;
            }

            $publishedAt = ($post['status'] ?? 'published') === 'published' ? now() : null;

            $record = ContentPost::create([
                'content_category_id' => $categories[$post['category']],
                'tax_year_id' => ($post['tax_year'] ?? false) ? $taxYearId : null,
                'type' => $post['type'],
                'title' => $post['title'],
                'slug' => $post['slug'],
                'excerpt' => $post['excerpt'],
                'content' => $post['content'],
                'featured' => $post['featured'] ?? false,
                'sort_order' => $post['sort_order'] ?? 0,
                'meta_title' => $post['title'],
                'meta_description' => $post['excerpt'],
            ]);

            // status/published_at are not fillable by design; a seeder sets them the same way the
            // publishing workflow does, without inventing an admin actor for the audit log.
            $record->forceFill([
                'status' => $post['status'] ?? 'published',
                'published_at' => $publishedAt,
                'first_published_at' => $publishedAt,
            ])->save();

            $record->tags()->sync(array_map(fn (string $slug): int => $tags[$slug], $post['tags'] ?? []));
        });
    }

    /** @return list<array<string, mixed>> */
    private function posts(): array
    {
        return [...$this->guides(), ...$this->articles(), ...$this->news(), ...$this->faqs(), $this->draft()];
    }

    /** @return list<array<string, mixed>> */
    private function guides(): array
    {
        return [
            [
                'type' => 'guide', 'slug' => 'guide-prepare-data-before-simulation',
                'title' => 'เตรียมข้อมูลอะไรบ้างก่อนเริ่มทดลองคำนวณภาษี',
                'category' => 'user-guide', 'featured' => true, 'sort_order' => 1, 'tax_year' => true,
                'tags' => ['income', 'allowance', 'withholding-tax', 'tax-year-2568'],
                'excerpt' => 'รวบรวมตัวเลขที่ควรมีอยู่ตรงหน้า เพื่อให้การทดลองคำนวณเสร็จในครั้งเดียวและได้ผลที่ใกล้เคียงความจริงที่สุด',
                'content' => <<<'TEXT'
                    การทดลองคำนวณจะราบรื่นขึ้นมากถ้าเตรียมตัวเลขไว้ก่อน ระบบไม่ต้องการเอกสารแนบใด ๆ ต้องการเพียงจำนวนเงินที่คุณกรอกเอง

                    # ข้อมูลผู้มีเงินได้
                    - วันเดือนปีเกิด
                    - สถานภาพสมรสในปีภาษีที่ต้องการทดลอง
                    - ข้อมูลคู่สมรสและผู้พึ่งพา ถ้ามี

                    # ข้อมูลเงินได้
                    - เงินได้รวมทั้งปีของแต่ละแหล่ง แยกตามประเภทเงินได้
                    - เงินได้ส่วนที่ได้รับยกเว้น ถ้าทราบ
                    - ผู้มีเงินได้หลายประเภท ควรแยกตัวเลขของแต่ละประเภทให้ชัดเจนก่อนเริ่มกรอก

                    # ค่าลดหย่อนและเงินบริจาค
                    - จำนวนเงินที่จ่ายจริงในแต่ละรายการค่าลดหย่อนที่คุณมี
                    - จำนวนเงินบริจาค แยกตามประเภทที่ระบบแสดงให้เลือก

                    # ภาษีที่ถูกหักไว้แล้ว
                    - ภาษีหัก ณ ที่จ่ายรวมทั้งปี
                    - ภาษีที่ชำระล่วงหน้าไว้แล้ว ถ้ามี

                    # ข้อควรทราบ
                    ระบบจะคำนวณค่าใช้จ่าย ค่าลดหย่อนส่วนบุคคล และภาษีตามขั้นให้เอง คุณไม่ต้องคำนวณล่วงหน้า กรอกเฉพาะจำนวนเงินที่คุณมีหลักฐานจริงก็พอ

                    ผลลัพธ์ที่ได้เป็นการประมาณการเพื่อการเรียนรู้และการวางแผน ไม่ใช่การยื่นแบบภาษีจริง
                    TEXT,
            ],
            [
                'type' => 'guide', 'slug' => 'guide-how-the-simulator-works',
                'title' => 'ระบบจำลองภาษีทำงานอย่างไร',
                'category' => 'user-guide', 'sort_order' => 2,
                'tags' => ['pnd90', 'pnd91', 'tax-year-2568'],
                'excerpt' => 'ตั้งแต่กรอกข้อมูลจนถึงผลการคำนวณ ระบบทำอะไรบ้าง และทำไมการคำนวณทั้งหมดจึงเกิดขึ้นที่ฝั่งเซิร์ฟเวอร์',
                'content' => <<<'TEXT'
                    ระบบนี้เป็นเครื่องมือจำลอง ไม่ใช่ระบบยื่นแบบภาษีอย่างเป็นทางการ หน้าที่ของระบบคือรับข้อมูลที่คุณกรอก แล้วแสดงผลการคำนวณพร้อมคำอธิบายว่าแต่ละตัวเลขมาจากไหน

                    # ขั้นตอนการทำงาน
                    1. คุณเลือกแบบภาษีที่ต้องการทดลอง
                    2. กรอกข้อมูลผู้เสียภาษี เงินได้ ค่าลดหย่อน เงินบริจาค และภาษีที่ถูกหักไว้แล้ว
                    3. ระบบส่งข้อมูลไปคำนวณที่เซิร์ฟเวอร์
                    4. เซิร์ฟเวอร์คำนวณตามรุ่นกฎภาษีที่เผยแพร่อยู่ แล้วส่งผลกลับมาแสดง

                    # ทำไมจึงคำนวณที่เซิร์ฟเวอร์
                    - ผลการคำนวณมาจากกฎชุดเดียว ไม่ว่าจะใช้ผ่านช่องทางใด
                    - ทุกผลลัพธ์อ้างอิงรุ่นกฎที่ระบุได้ว่าใช้กฎชุดใดคำนวณ
                    - หน้าเว็บไม่คำนวณตัวเลขภาษีเอง จึงไม่มีทางที่หน้าจอกับผลจริงจะไม่ตรงกัน

                    # สิ่งที่ระบบจะบอกคุณเสมอ
                    - ผลสรุปว่าต้องชำระเพิ่ม ได้คืน หรือไม่มีภาษีต้องชำระ
                    - รายละเอียดการคำนวณภาษีแบบขั้นบันได
                    - คำเตือน เมื่อมีข้อมูลที่ระบบยังไม่รองรับหรือยังไม่ได้ตรวจสอบ

                    ถ้ารายการใดยังไม่รองรับ ระบบจะปิดการกรอกรายการนั้นพร้อมเหตุผล แทนที่จะเดาตัวเลขให้
                    TEXT,
            ],
            [
                'type' => 'guide', 'slug' => 'guide-guest-vs-member',
                'title' => 'ทดลองแบบไม่สมัครสมาชิก กับ สมัครสมาชิก ต่างกันอย่างไร',
                'category' => 'user-guide', 'sort_order' => 3,
                'tags' => ['tax-planning'],
                'excerpt' => 'ทดลองคำนวณได้ทันทีโดยไม่ต้องสมัครสมาชิก ส่วนการบันทึกแบบร่างและการวางแผนต้องใช้บัญชีสมาชิก',
                'content' => <<<'TEXT'
                    คุณสามารถทดลองคำนวณภาษีได้ทันทีโดยไม่ต้องสมัครสมาชิก การสมัครสมาชิกมีผลกับการเก็บข้อมูลไว้ใช้ต่อเท่านั้น ไม่มีผลกับวิธีคำนวณ

                    # ทดลองโดยไม่สมัครสมาชิก
                    - กรอกข้อมูลและดูผลการคำนวณได้ครบถ้วน
                    - ไม่มีการบันทึกข้อมูลของคุณไว้ในระบบ
                    - เมื่อปิดหน้าเว็บ ข้อมูลที่กรอกจะหายไป

                    # เมื่อสมัครสมาชิก
                    - บันทึกแบบร่างไว้กรอกต่อภายหลังได้
                    - ดูประวัติการคำนวณของแบบเดิมย้อนหลังได้
                    - สร้างสถานการณ์วางแผนเพื่อเปรียบเทียบผลกับแบบที่บันทึกไว้

                    # ข้อมูลของคุณ
                    ผลการคำนวณที่บันทึกไว้จะผูกกับรุ่นกฎภาษีที่ใช้คำนวณในขณะนั้น ผลเก่าจึงไม่เปลี่ยนย้อนหลังแม้ระบบจะมีรุ่นกฎใหม่ในภายหลัง
                    TEXT,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function articles(): array
    {
        return [
            [
                'type' => 'article', 'slug' => 'article-pnd90-vs-pnd91',
                'title' => 'ภ.ง.ด.90 กับ ภ.ง.ด.91 ต่างกันอย่างไร',
                'category' => 'tax-knowledge', 'featured' => true, 'sort_order' => 1, 'tax_year' => true,
                'tags' => ['pnd90', 'pnd91', 'income', 'tax-year-2568'],
                'excerpt' => 'ความต่างหลักอยู่ที่ประเภทเงินได้ที่แต่ละแบบรองรับ บทความนี้อธิบายวิธีดูว่าข้อมูลของคุณเข้ากับแบบใด',
                'content' => <<<'TEXT'
                    ผู้เริ่มต้นมักสับสนว่าควรทดลองด้วยแบบใด ความต่างหลักอยู่ที่ประเภทของเงินได้ที่แบบนั้นรองรับ

                    # ภ.ง.ด.91
                    ใช้กับผู้มีเงินได้จากการจ้างแรงงานตามมาตรา 40(1) เพียงประเภทเดียว การกรอกจึงสั้นกว่า เพราะมีแหล่งเงินได้แบบเดียวให้ระบุ

                    # ภ.ง.ด.90
                    ใช้กับผู้มีเงินได้ตามมาตรา 40(1) ถึง 40(8) เช่น มีเงินเดือนและมีรายได้จากค่าเช่า วิชาชีพอิสระ หรือธุรกิจร่วมด้วย การกรอกจะมีขั้นตอนแยกประเภทเงินได้เพิ่มเข้ามา

                    # วิธีเลือกอย่างง่าย
                    - ถ้าทั้งปีมีเพียงเงินเดือนหรือค่าจ้างจากการทำงาน ให้เริ่มที่ ภ.ง.ด.91
                    - ถ้ามีเงินได้ประเภทอื่นร่วมด้วยแม้เพียงรายการเดียว ให้เริ่มที่ ภ.ง.ด.90

                    # ถ้ายังไม่แน่ใจ
                    เลือกแบบใดก่อนก็ได้ ระบบไม่ได้ยื่นแบบจริง คุณจึงทดลองทั้งสองแบบเพื่อเปรียบเทียบความเข้าใจได้ รายการเงินได้ที่แต่ละแบบรองรับจริงจะแสดงในขั้นตอนกรอกเงินได้
                    TEXT,
            ],
            [
                'type' => 'article', 'slug' => 'article-understanding-result-status',
                'title' => 'อ่านผลการคำนวณ ต้องชำระเพิ่ม ได้คืน หรือไม่มีภาษีต้องชำระ',
                'category' => 'tax-knowledge', 'featured' => true, 'sort_order' => 2,
                'tags' => ['tax-refund', 'withholding-tax'],
                'excerpt' => 'ผลสรุปสามแบบที่ระบบแสดง แต่ละแบบหมายความว่าอย่างไร และควรดูตัวเลขใดต่อ',
                'content' => <<<'TEXT'
                    เมื่อคำนวณเสร็จ ระบบจะสรุปผลออกมาเป็นหนึ่งในสามสถานะ ทั้งสามสถานะมาจากการเปรียบเทียบภาษีที่คำนวณได้กับภาษีที่ถูกหักหรือชำระไว้แล้ว

                    # ต้องชำระเพิ่ม
                    ภาษีที่คำนวณได้มากกว่าที่ถูกหักหรือชำระไว้แล้ว ส่วนต่างคือจำนวนที่ยังขาดอยู่

                    # ได้คืน
                    ภาษีที่ถูกหักหรือชำระไว้แล้วมากกว่าภาษีที่คำนวณได้ ส่วนต่างคือจำนวนที่ชำระเกิน

                    # ไม่มีภาษีต้องชำระ
                    ภาษีที่คำนวณได้เท่ากับที่ชำระไว้แล้วพอดี หรือคำนวณแล้วไม่มีภาษีที่ต้องชำระ

                    # ดูอะไรต่อ
                    - เงินได้สุทธิ คือฐานที่นำไปคำนวณภาษีตามขั้น
                    - ตารางภาษีแบบขั้นบันได แสดงว่าเงินได้สุทธิถูกแบ่งเข้าขั้นใดบ้าง
                    - คำเตือน บอกว่ามีข้อมูลส่วนใดที่ผลนี้ยังไม่ได้รวมไว้

                    ตัวเลขทั้งหมดเป็นการประมาณการจากข้อมูลที่คุณกรอกในระบบจำลอง
                    TEXT,
            ],
            [
                'type' => 'article', 'slug' => 'article-what-calculation-warnings-mean',
                'title' => 'คำเตือนในผลการคำนวณหมายถึงอะไร',
                'category' => 'tax-knowledge', 'sort_order' => 3,
                'tags' => ['allowance', 'income'],
                'excerpt' => 'เมื่อระบบยังไม่รองรับรายการใด ระบบจะบอกตรง ๆ แทนการเดา บทความนี้อธิบายว่าควรอ่านคำเตือนอย่างไร',
                'content' => <<<'TEXT'
                    ระบบออกแบบมาให้บอกสิ่งที่ยังไม่รู้ ดีกว่าเดาแล้วให้ตัวเลขที่ดูน่าเชื่อถือแต่ผิด คำเตือนจึงเป็นส่วนหนึ่งของผลลัพธ์ ไม่ใช่ข้อผิดพลาด

                    # คำเตือนมักบอกอะไร
                    - รายการที่คุณกรอกไว้ แต่ยังไม่ถูกนำมารวมในผลนี้
                    - รายการที่ระบบยังไม่รองรับสำหรับรุ่นกฎที่ใช้คำนวณ
                    - เงื่อนไขที่ต้องการข้อมูลเพิ่มจึงจะสรุปได้

                    # ควรทำอย่างไร
                    - อ่านคำเตือนก่อนนำตัวเลขไปใช้ตัดสินใจ
                    - ถ้าคำเตือนระบุรายการที่คุณมีจริง ให้ถือว่าผลนี้ยังไม่ครบ
                    - ใช้ผลเป็นภาพรวมเพื่อการเรียนรู้และวางแผน ไม่ใช่ตัวเลขสุดท้าย

                    # ทำไมบางรายการจึงถูกปิดไว้
                    รายการที่ยังไม่มีแหล่งอ้างอิงที่ได้รับอนุมัติจะถูกปิดการกรอกพร้อมเหตุผล ระบบไม่ใส่ตัวเลขแทนคุณในกรณีเช่นนี้
                    TEXT,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function news(): array
    {
        return [
            [
                'type' => 'news', 'slug' => 'news-tax-year-2568-simulation-open',
                'title' => 'เปิดให้ทดลองคำนวณภาษีปีภาษี 2568 แล้ว',
                'category' => 'tax-news', 'featured' => true, 'sort_order' => 1, 'tax_year' => true,
                'tags' => ['tax-year-2568', 'pnd90', 'pnd91'],
                'excerpt' => 'ระบบจำลองเปิดให้ทดลองคำนวณทั้ง ภ.ง.ด.90 และ ภ.ง.ด.91 สำหรับปีภาษี 2568 โดยไม่ต้องสมัครสมาชิก',
                'content' => <<<'TEXT'
                    ระบบจำลองภาษีเปิดให้ทดลองคำนวณสำหรับปีภาษี 2568 แล้ว รองรับทั้งแบบ ภ.ง.ด.90 และ ภ.ง.ด.91

                    # สิ่งที่ใช้ได้ตอนนี้
                    - ทดลองคำนวณได้ทันทีโดยไม่ต้องสมัครสมาชิก
                    - ดูรายละเอียดการคำนวณภาษีแบบขั้นบันไดพร้อมคำอธิบาย
                    - สมัครสมาชิกเพื่อบันทึกแบบร่างและดูประวัติการคำนวณ

                    ระบบนี้เป็นเครื่องมือเพื่อการเรียนรู้และการวางแผน ไม่ใช่ระบบยื่นแบบภาษีอย่างเป็นทางการ
                    TEXT,
            ],
            [
                'type' => 'news', 'slug' => 'news-pnd90-multi-income-support',
                'title' => 'รองรับการกรอกเงินได้หลายประเภทในแบบ ภ.ง.ด.90',
                'category' => 'tax-news', 'sort_order' => 2,
                'tags' => ['pnd90', 'income'],
                'excerpt' => 'ผู้ที่มีเงินได้มากกว่าหนึ่งประเภทสามารถแยกกรอกแต่ละแหล่งเงินได้ และดูผลรวมในการคำนวณเดียวกัน',
                'content' => <<<'TEXT'
                    แบบ ภ.ง.ด.90 ในระบบจำลองรองรับการกรอกเงินได้หลายรายการในการทดลองครั้งเดียว

                    # สิ่งที่ทำได้
                    - เพิ่มแหล่งเงินได้ได้มากกว่าหนึ่งรายการ
                    - ระบุประเภทเงินได้ของแต่ละรายการแยกกัน
                    - ดูผลรวมและรายละเอียดการคำนวณในผลลัพธ์เดียว

                    # ข้อควรทราบ
                    รายการประเภทเงินได้ที่เลือกได้จะแสดงตามแบบภาษีที่คุณเลือก และรายการที่ยังไม่รองรับจะแจ้งเหตุผลไว้ในหน้าเดียวกัน
                    TEXT,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function faqs(): array
    {
        $rows = [
            ['faq-need-account-to-try', 'ต้องสมัครสมาชิกก่อนทดลองคำนวณหรือไม่',
                'ไม่ต้อง คุณทดลองคำนวณและดูผลได้ครบถ้วนโดยไม่ต้องสมัครสมาชิก',
                <<<'TEXT'
                    ไม่ต้องสมัครสมาชิกก็ทดลองคำนวณได้ทันที และดูผลการคำนวณได้ครบถ้วน

                    การสมัครสมาชิกมีผลเฉพาะกับการเก็บข้อมูลไว้ใช้ต่อ เช่น บันทึกแบบร่าง ดูประวัติการคำนวณ และสร้างสถานการณ์วางแผน
                    TEXT],
            ['faq-is-this-real-filing', 'ระบบนี้ยื่นภาษีจริงหรือไม่',
                'ไม่ใช่ ระบบนี้เป็นเครื่องมือจำลองเพื่อการเรียนรู้และการวางแผนเท่านั้น',
                <<<'TEXT'
                    ไม่ใช่ ระบบนี้เป็นเครื่องมือจำลองเพื่อการเรียนรู้และวางแผน ไม่ได้เชื่อมต่อกับระบบยื่นแบบของหน่วยงานราชการ

                    ผลลัพธ์ที่ได้เป็นการประมาณการจากข้อมูลที่คุณกรอก ไม่ใช่การยื่นแบบหรือการรับรองผลโดยหน่วยงานใด
                    TEXT],
            ['faq-pnd90-vs-pnd91', 'ภ.ง.ด.90 กับ ภ.ง.ด.91 ต่างกันอย่างไร',
                'ต่างกันที่ประเภทเงินได้ที่แต่ละแบบรองรับ',
                <<<'TEXT'
                    ภ.ง.ด.91 ใช้กับผู้มีเงินได้จากการจ้างแรงงานตามมาตรา 40(1) เพียงประเภทเดียว

                    ภ.ง.ด.90 ใช้กับผู้มีเงินได้ตามมาตรา 40(1) ถึง 40(8) ซึ่งครอบคลุมเงินได้ประเภทอื่นนอกจากเงินเดือน

                    ถ้าทั้งปีมีเพียงเงินเดือน ให้เริ่มที่ ภ.ง.ด.91 ถ้ามีเงินได้ประเภทอื่นร่วมด้วย ให้เริ่มที่ ภ.ง.ด.90
                    TEXT],
            ['faq-why-some-allowances-unsupported', 'ทำไมบางค่าลดหย่อนระบบยังไม่รองรับ',
                'ระบบจะเปิดใช้เฉพาะรายการที่มีแหล่งอ้างอิงที่ได้รับอนุมัติแล้วเท่านั้น',
                <<<'TEXT'
                    ระบบเปิดให้กรอกเฉพาะรายการที่มีแหล่งอ้างอิงที่ได้รับอนุมัติสำหรับรุ่นกฎที่ใช้คำนวณ

                    รายการที่ยังไม่รองรับจะถูกปิดการกรอกพร้อมแสดงเหตุผล แทนที่จะเดาจำนวนเงินหรือเงื่อนไขให้

                    เมื่อรายการนั้นได้รับการตรวจสอบและอนุมัติแล้ว จึงจะเปิดใช้งานในรุ่นกฎถัดไป
                    TEXT],
            ['faq-what-is-estimated-refund', 'ผลประมาณการเงินคืนหมายถึงอะไร',
                'หมายถึงส่วนที่ภาษีถูกหักหรือชำระไว้แล้วมากกว่าภาษีที่คำนวณได้',
                <<<'TEXT'
                    เมื่อภาษีที่ถูกหัก ณ ที่จ่ายหรือชำระไว้แล้ว มากกว่าภาษีที่คำนวณได้จากข้อมูลที่กรอก ระบบจะสรุปผลว่าได้คืน และแสดงส่วนต่างนั้น

                    ตัวเลขนี้เป็นการประมาณการจากข้อมูลในระบบจำลองเท่านั้น ไม่ใช่จำนวนที่ได้รับอนุมัติจากหน่วยงานใด

                    ควรอ่านคำเตือนที่แสดงพร้อมผลด้วย เพราะอาจมีรายการที่ยังไม่ถูกนำมารวม
                    TEXT],
            ['faq-how-to-save-draft', 'บันทึกแบบร่างได้อย่างไร',
                'เข้าสู่ระบบด้วยบัญชีสมาชิก แล้วใช้ปุ่มบันทึกแบบร่างระหว่างกรอกข้อมูล',
                <<<'TEXT'
                    การบันทึกแบบร่างใช้ได้เมื่อเข้าสู่ระบบด้วยบัญชีสมาชิกแล้ว

                    1. สมัครสมาชิกหรือเข้าสู่ระบบ
                    2. เริ่มทดลองคำนวณตามปกติ
                    3. ใช้ปุ่มบันทึกแบบร่างระหว่างกรอกข้อมูล
                    4. กลับมากรอกต่อได้จากหน้าแบบภาษีของฉัน

                    แบบร่างที่คำนวณและบันทึกเป็นผลสมบูรณ์แล้วจะกลายเป็นรายการอ่านอย่างเดียว หากต้องการแก้ไข ให้ทำสำเนาเป็นแบบร่างใหม่
                    TEXT],
        ];

        $faqs = [];
        foreach ($rows as $index => [$slug, $title, $excerpt, $content]) {
            $faqs[] = [
                'type' => 'faq', 'slug' => $slug, 'title' => $title, 'category' => 'faq',
                'sort_order' => $index + 1, 'excerpt' => $excerpt, 'content' => $content,
                'tags' => [],
            ];
        }

        return $faqs;
    }

    /**
     * One unpublished row, so the admin console has something to edit and publish.
     *
     * @return array<string, mixed>
     */
    private function draft(): array
    {
        return [
            'type' => 'article', 'slug' => 'article-tax-planning-scenarios',
            'title' => 'สถานการณ์วางแผนภาษีทำงานอย่างไร',
            'category' => 'tax-planning', 'status' => 'draft', 'sort_order' => 4,
            'tags' => ['tax-planning'],
            'excerpt' => 'สถานการณ์วางแผนคือการทดลองเปลี่ยนตัวเลขบางรายการ เพื่อดูผลเปรียบเทียบ โดยไม่แตะแบบที่บันทึกไว้',
            'content' => <<<'TEXT'
                สถานการณ์วางแผนช่วยตอบคำถามว่า ถ้าเปลี่ยนตัวเลขนี้ ผลจะต่างไปเท่าไร โดยไม่กระทบแบบที่บันทึกไว้

                # หลักการ
                - เริ่มจากแบบภาษีที่คุณบันทึกไว้เป็นฐานเปรียบเทียบ
                - ปรับเฉพาะรายการที่ต้องการทดลอง
                - ระบบคำนวณผลของสถานการณ์ใหม่ แล้วแสดงเทียบกับฐาน

                # สิ่งที่ระบบแสดง
                - ผลก่อนปรับ
                - ผลหลังปรับ
                - ส่วนต่าง และประมาณการภาษีที่เปลี่ยนไป

                # ข้อมูลต้นฉบับไม่เปลี่ยน
                การสร้างและคำนวณสถานการณ์ไม่แก้ไขแบบภาษีต้นทาง และไม่เพิ่มรายการลงในประวัติการคำนวณของแบบนั้น
                TEXT,
        ];
    }
}
