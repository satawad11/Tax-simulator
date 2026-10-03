{{--
    Milestone 09.1 — the approved source-document registry, now writable from the console.

    Registering a document is an ordinary administrative act, but before M9.1 it could only be
    done by editing a seeder. The one rule worth stating in the interface: a source cited by a
    published rule version cannot be deleted, because a published rule must keep pointing at the
    document it was read from. The row shows its citation count, and the control names the
    outcome that will actually occur.
--}}
<x-admin-layout title="เอกสารอ้างอิง">
    <section data-admin-page="tax-sources" aria-busy="true">
        <h1 class="ui-section-title">เอกสารอ้างอิงทางภาษี</h1>
        <p class="ui-body mt-2 max-w-3xl">
            ทะเบียนเอกสารต้นทางที่อนุมัติแล้ว ทุกรายการเป็นไฟล์ในคลังโค้ดของโครงการ ไม่มีการอ้างอิงแหล่งภายนอก
            เอกสารที่ถูกอ้างอิงโดยชุดกฎที่เผยแพร่แล้วจะลบไม่ได้ ระบบจะปิดใช้งานแทน
        </p>

        <div data-admin-loading class="ui-loading mt-6" role="status">กำลังโหลดเอกสารอ้างอิง…</div>

        <div data-admin-content class="hidden">
            <form data-source-form class="ui-card mt-6">
                <h2 data-source-form-title class="ui-form-title">เพิ่มเอกสารอ้างอิง</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="ui-field">รหัสเอกสาร<span class="ui-field-required" aria-hidden="true">*</span>
                        <input name="code" required maxlength="100" pattern="[A-Z0-9_]+" class="font-mono text-sm"
                               placeholder="PND90_FORM_2568">
                        <span class="ui-help">ตัวพิมพ์ใหญ่ ตัวเลข และขีดล่างเท่านั้น</span>
                    </label>
                    <label class="ui-field">ประเภทเอกสาร<span class="ui-field-required" aria-hidden="true">*</span>
                        <select name="source_type" required>
                            <option value="OFFICIAL_FORM">แบบฟอร์มทางการ (OFFICIAL_FORM)</option>
                            <option value="FILING_INSTRUCTIONS">คำแนะนำการกรอก (FILING_INSTRUCTIONS)</option>
                            <option value="ATTACHMENT">เอกสารแนบ (ATTACHMENT)</option>
                            <option value="INTERNAL_APPROVED_REFERENCE">เอกสารอ้างอิงภายในที่อนุมัติแล้ว</option>
                        </select>
                    </label>
                    <label class="ui-field sm:col-span-2">ชื่อเอกสาร<span class="ui-field-required" aria-hidden="true">*</span>
                        <input name="title" required maxlength="255">
                    </label>
                    <label class="ui-field sm:col-span-2">ที่อยู่ไฟล์ในคลังโค้ด<span class="ui-field-note">ไม่บังคับ</span>
                        <input name="file_path" maxlength="500" class="font-mono text-sm" placeholder="docs/tax-source/…">
                        <span class="ui-help">ต้องเป็นเส้นทางภายในโครงการ ระบบไม่รับ URL ภายนอก</span>
                    </label>
                    <label class="ui-field">วันที่ของเอกสาร<span class="ui-field-note">ไม่บังคับ</span>
                        <input name="document_date" type="date">
                    </label>
                    <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700 sm:mt-7">
                        <input type="checkbox" name="active" checked> เปิดใช้งาน
                    </label>
                    <label class="ui-field sm:col-span-2">คำอธิบาย<span class="ui-field-note">ไม่บังคับ</span>
                        <textarea name="description" rows="2" maxlength="2000"></textarea>
                    </label>
                </div>
                <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                    <button type="submit" class="ui-button-primary">บันทึก</button>
                    <button type="button" data-source-cancel class="ui-button-secondary hidden">ยกเลิกการแก้ไข</button>
                </div>
            </form>

            <div class="ui-card mt-6 p-0 sm:p-0">
                <div class="ui-scroll-x">
                    <table class="ui-table min-w-[56rem]">
                        <thead>
                            <tr>
                                <th scope="col">รหัส</th>
                                <th scope="col">ชื่อเอกสาร</th>
                                <th scope="col">ประเภท</th>
                                <th scope="col">ไฟล์</th>
                                <th scope="col">การอ้างอิง</th>
                                <th scope="col">สถานะ</th>
                                <th scope="col" class="text-right"><span class="sr-only">จัดการ</span></th>
                            </tr>
                        </thead>
                        <tbody data-source-rows></tbody>
                    </table>
                </div>
            </div>
        </div>

        <p data-admin-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
    </section>
</x-admin-layout>
