{{-- Milestone 09.1 — the content list, on the shared table and button scales. --}}
<x-admin-layout title="เนื้อหา">
    <section data-admin-page="content" aria-busy="true">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="ui-section-title">เนื้อหา</h1>
                <p class="ui-body mt-2">บทความ คู่มือ ข่าวสาร และคำถามที่พบบ่อยทั้งหมดในระบบ</p>
            </div>
            <a href="{{ route('admin.content.create') }}" class="ui-button-primary">
                <x-icon name="plus" class="size-4" />สร้างเนื้อหาใหม่
            </a>
        </div>

        <div data-admin-loading class="ui-loading mt-6" role="status">กำลังโหลดเนื้อหา…</div>

        <div data-admin-content class="mt-6 hidden">
            <form data-content-filters class="ui-card grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
                <label class="ui-field">ค้นหา
                    <input name="search" type="search" placeholder="หัวข้อหรือ slug" autocomplete="off" value="{{ request('search') }}">
                </label>
                <label class="ui-field">สถานะ
                    <select name="status">
                        <option value="">ทั้งหมด</option>
                        <option value="published" @selected(request('status') === 'published')>เผยแพร่แล้ว</option>
                        <option value="draft" @selected(request('status') === 'draft')>ฉบับร่าง</option>
                        <option value="archived" @selected(request('status') === 'archived')>จัดเก็บแล้ว</option>
                    </select>
                </label>
                <label class="ui-field">ประเภท
                    <select name="type">
                        <option value="">ทั้งหมด</option>
                        <option value="article" @selected(request('type') === 'article')>บทความ</option>
                        <option value="guide" @selected(request('type') === 'guide')>คู่มือ</option>
                        <option value="news" @selected(request('type') === 'news')>ข่าวสาร</option>
                        <option value="faq" @selected(request('type') === 'faq')>คำถามที่พบบ่อย</option>
                    </select>
                </label>
                <button type="submit" class="ui-button-primary">ค้นหา</button>
            </form>

            <p data-content-summary class="ui-help mt-4" role="status" aria-live="polite"></p>

            <div class="ui-card mt-3 p-0 sm:p-0">
                <div class="ui-scroll-x">
                    <table class="ui-table min-w-[52rem]">
                        <thead>
                            <tr>
                                <th scope="col">หัวข้อ</th>
                                <th scope="col">ประเภท</th>
                                <th scope="col">สถานะ</th>
                                <th scope="col">หมวดหมู่</th>
                                <th scope="col">เผยแพร่</th>
                                <th scope="col" class="text-right"><span class="sr-only">จัดการ</span></th>
                            </tr>
                        </thead>
                        <tbody data-content-rows></tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <button type="button" data-content-previous class="ui-button-quiet" disabled>ก่อนหน้า</button>
                <span data-content-page class="ui-help"></span>
                <button type="button" data-content-next class="ui-button-quiet" disabled>ถัดไป</button>
            </div>
        </div>

        <p data-admin-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
    </section>
</x-admin-layout>
