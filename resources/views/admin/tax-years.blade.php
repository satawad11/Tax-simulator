{{--
    Milestone 09.1 — tax year administration.

    This page exists so that "next year's rates changed" is an administrative task rather than a
    code change. It opens the year and copies its form structure; the rates themselves are carried
    over afterwards by cloning a rule version into the new year from the ชุดกฎภาษี page.

    Two rules are stated in the interface because they are the ones that surprise people:
    a new year needs its forms or a simulation cannot start in it at all, and a year is retired
    rather than deleted, so everything already calculated under it keeps resolving unchanged.
--}}
<x-admin-layout title="ปีภาษี">
    <section data-admin-page="tax-years" aria-busy="true">
        <h1 class="ui-section-title">ปีภาษี</h1>
        <p class="ui-body mt-2 max-w-3xl">
            ปีภาษีคือกล่องที่บรรจุแบบภาษี ชุดกฎ และแบบจำลองทั้งหมดของปีนั้น
            การเปิดปีใหม่ไม่กระทบปีเดิม — แบบที่บันทึกไว้และผลที่คำนวณแล้วยังผูกกับชุดกฎของปีตัวเองเสมอ
        </p>

        <div data-admin-loading class="ui-loading mt-6" role="status">กำลังโหลดปีภาษี…</div>

        <div data-admin-content class="hidden">
            <div class="ui-note ui-note-info mt-6">
                <span class="ui-note-icon bg-blue-600" aria-hidden="true"><x-icon name="info" class="size-3.5" /></span>
                <div>
                    <p class="font-bold">ขั้นตอนเมื่ออัตราภาษีเปลี่ยนในปีถัดไป</p>
                    <ol class="mt-1.5 list-decimal space-y-1 pl-5">
                        <li>เปิดปีภาษีใหม่ที่นี่ โดยคัดลอกโครงสร้างแบบฟอร์มจากปีเดิม</li>
                        <li>ไปที่หน้า <a href="{{ route('admin.rule-versions') }}" class="font-semibold underline">ชุดกฎภาษี</a>
                            แล้วทำสำเนาชุดกฎของปีเดิมไปยังปีใหม่</li>
                        <li>แก้เฉพาะอัตราที่เปลี่ยน ตรวจสอบ แล้วจึงเผยแพร่</li>
                    </ol>
                </div>
            </div>

            <form data-year-form class="ui-card mt-6">
                <h2 data-year-form-title class="ui-form-title">เปิดปีภาษีใหม่</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="ui-field">ปีภาษี (พ.ศ.)<span class="ui-field-required" aria-hidden="true">*</span>
                        <input name="year" required inputmode="numeric" placeholder="2569">
                        <span class="ui-help">แก้ไขภายหลังไม่ได้ เพราะชุดกฎและแบบที่บันทึกไว้อ้างอิงปีนี้</span>
                    </label>
                    <label class="ui-field">ชื่อที่แสดง<span class="ui-field-note">ไม่บังคับ</span>
                        <input name="name" maxlength="255" placeholder="ปีภาษี 2569">
                    </label>
                    <label class="ui-field">คัดลอกโครงสร้างแบบฟอร์มจากปี<span class="ui-field-note">แนะนำ</span>
                        <select name="copy_forms_from_year"><option value="">— ไม่คัดลอก —</option></select>
                        <span class="ui-help">ปีที่ไม่มีแบบภาษีจะเริ่มทดลองคำนวณไม่ได้เลย</span>
                    </label>
                    <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700 sm:mt-7">
                        <input type="checkbox" name="active" checked> เปิดให้เลือกปีนี้ในระบบ
                    </label>
                    <label class="ui-field">เริ่มยื่นแบบ<span class="ui-field-note">ไม่บังคับ</span>
                        <input name="filing_start_date" type="date">
                    </label>
                    <label class="ui-field">สิ้นสุดการยื่นแบบ<span class="ui-field-note">ไม่บังคับ</span>
                        <input name="filing_end_date" type="date">
                    </label>
                </div>
                <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                    <button type="submit" class="ui-button-primary">บันทึก</button>
                    <button type="button" data-year-cancel class="ui-button-secondary hidden">ยกเลิกการแก้ไข</button>
                </div>
            </form>

            <div class="ui-card mt-6 p-0 sm:p-0">
                <div class="ui-scroll-x">
                    <table class="ui-table min-w-[56rem]">
                        <thead>
                            <tr>
                                <th scope="col">ปีภาษี</th>
                                <th scope="col">ชื่อที่แสดง</th>
                                <th scope="col">แบบภาษี</th>
                                <th scope="col">ชุดกฎ</th>
                                <th scope="col">ที่เผยแพร่</th>
                                <th scope="col">แบบจำลองที่ใช้ปีนี้</th>
                                <th scope="col">สถานะ</th>
                                <th scope="col" class="text-right"><span class="sr-only">จัดการ</span></th>
                            </tr>
                        </thead>
                        <tbody data-year-rows></tbody>
                    </table>
                </div>
            </div>
        </div>

        <p data-admin-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
    </section>
</x-admin-layout>
