{{--
    Milestone 09.1 — the content editor, on the shared field scale.

    Two constraints are stated in the interface rather than left to be discovered: the body is
    structured plain text and the API rejects HTML, and the slug freezes at first publish so a
    URL that has been shared keeps resolving to the same post.
--}}
<x-admin-layout :title="$contentId ? 'แก้ไขเนื้อหา' : 'สร้างเนื้อหา'">
    <section data-admin-page="content-form" data-content-id="{{ $contentId }}" aria-busy="true">
        <nav class="ui-meta mb-5" aria-label="เส้นทางหน้า">
            <a href="{{ route('admin.content') }}" class="hover:text-blue-700">เนื้อหา</a>
            <x-icon name="chevron-right" class="size-3" />
            <span class="font-semibold text-slate-700">{{ $contentId ? 'แก้ไข' : 'สร้างใหม่' }}</span>
        </nav>

        <h1 class="ui-section-title">{{ $contentId ? 'แก้ไขเนื้อหา' : 'สร้างเนื้อหา' }}</h1>

        <div data-admin-loading class="ui-loading mt-6" role="status">กำลังเตรียมแบบฟอร์ม…</div>

        <form data-content-form data-content-id="{{ $contentId }}" class="ui-card mt-6 hidden space-y-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="ui-field">ประเภท<span class="ui-field-required" aria-hidden="true">*</span>
                    {{--
                        The values are the vocabulary ContentPost::TYPES actually declares, in the
                        case the API validates against. They were previously offered in upper case,
                        which the API rejected, so no content could be saved from this form at all
                        and an existing post's type never preselected on edit.
                    --}}
                    <select name="type">
                        <option value="article">บทความ</option>
                        <option value="guide">คู่มือ</option>
                        <option value="news">ข่าวสาร</option>
                        <option value="faq">คำถามที่พบบ่อย</option>
                    </select>
                </label>
                <label class="ui-field">หมวดหมู่<span class="ui-field-note">ไม่บังคับ</span>
                    <select name="category_id"><option value="">— ไม่ระบุ —</option></select>
                    <span class="ui-help">จัดการรายการได้ที่หน้า <a href="{{ route('admin.taxonomy') }}" class="font-semibold text-blue-700 hover:underline">หมวดหมู่และแท็ก</a></span>
                </label>
            </div>

            <label class="ui-field">หัวข้อ<span class="ui-field-required" aria-hidden="true">*</span>
                <input name="title" required>
            </label>

            <label class="ui-field">Slug (URL)<span class="ui-field-required" aria-hidden="true">*</span>
                <input name="slug" required class="font-mono text-sm">
                <span data-slug-help class="ui-help hidden">Slug ถูกล็อกหลังเผยแพร่ครั้งแรก เพื่อไม่ให้ลิงก์ที่เผยแพร่ไปแล้วเปลี่ยนปลายทาง</span>
            </label>

            <label class="ui-field">เกริ่นนำ<span class="ui-field-note">ไม่บังคับ</span>
                <textarea name="excerpt" rows="2"></textarea>
            </label>

            <label class="ui-field">เนื้อหา<span class="ui-field-required" aria-hidden="true">*</span>
                <textarea name="body" rows="14" required class="font-mono text-sm"></textarea>
                <span class="ui-help">ข้อความล้วนแบบมีโครงสร้าง — ใช้ # สำหรับหัวข้อ และ - หรือ 1. สำหรับรายการ ระบบไม่รับ HTML</span>
            </label>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="ui-field">SEO Title<span class="ui-field-note">ไม่บังคับ</span><input name="meta_title"></label>
                <label class="ui-field">SEO Description<span class="ui-field-note">ไม่บังคับ</span><input name="meta_description"></label>
            </div>

            {{--
                Phase 3 — three fields the API has always accepted and no control ever set.

                Each one drives something a reader can already see: the FAQ page and the FAQ API
                both order by `sort_order`, and the knowledge and news pages both offer a tax-year
                filter. Without these controls an administrator could publish a FAQ they could not
                order and a post that the year filter could never find.
            --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="ui-field">ปีภาษีที่เกี่ยวข้อง<span class="ui-field-note">ไม่บังคับ</span>
                    <select name="tax_year_id"><option value="">— ไม่ระบุ —</option></select>
                    <span class="ui-help">ใช้กับตัวกรอง “ปีภาษี” บนหน้าความรู้ภาษีและข่าวสาร</span>
                </label>
                <label class="ui-field">ลำดับการแสดง<span class="ui-field-note">ไม่บังคับ</span>
                    <input name="sort_order" type="number" inputmode="numeric" min="0" max="9999" placeholder="0">
                    <span class="ui-help">เลขน้อยแสดงก่อน ใช้จัดลำดับคำถามที่พบบ่อยเป็นหลัก</span>
                </label>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="ui-field">ชื่อแหล่งที่มา<span class="ui-field-note">ไม่บังคับ</span>
                    <input name="source_name" maxlength="255" placeholder="เช่น กรมสรรพากร">
                </label>
                <label class="ui-field">ลิงก์แหล่งที่มา<span class="ui-field-note">ไม่บังคับ</span>
                    <input name="source_url" type="url" maxlength="2048" placeholder="https://…">
                    <span class="ui-help">แสดงเป็นเครดิตท้ายบทความ รับเฉพาะ http และ https</span>
                </label>
            </div>

            <fieldset class="rounded-xl border border-slate-200 p-4">
                <legend class="px-1 text-sm font-semibold text-slate-700">แท็ก</legend>
                <div data-content-tags class="flex flex-wrap gap-3"></div>
            </fieldset>

            <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="featured"> แสดงในรายการแนะนำบนหน้าแรก
            </label>

            <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                <button type="submit" class="ui-button-primary">บันทึกฉบับร่าง</button>
                {{--
                    Preview opens the saved post, not the unsaved form, so it appears only once
                    there is something stored to look at. Saying so on the button is cheaper than
                    letting an author wonder why their last paragraph is missing.
                --}}
                <button data-content-preview type="button" class="ui-button-secondary hidden">
                    <x-icon name="eye" class="size-4" />ดูตัวอย่าง (ตามที่บันทึกไว้ล่าสุด)
                </button>
                <button data-existing-action="publish" type="button" class="ui-button-secondary hidden">เผยแพร่</button>
                <button data-existing-action="unpublish" type="button" class="ui-button-secondary hidden">ยกเลิกเผยแพร่</button>
                <a href="{{ route('admin.content') }}" class="ui-button-secondary">กลับ</a>
            </div>

            <p data-admin-message role="status" aria-live="polite" class="text-sm"></p>
        </form>
    </section>
</x-admin-layout>
