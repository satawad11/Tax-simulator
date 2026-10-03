{{--
    Milestone 09.1 — category and tag administration.

    AdminContentTaxonomyController has supported this since M8, but no page called it, so an
    administrator could file content only under labels a seeder had created. This page is that
    missing surface and nothing more: it renders empty and asks the authorized API for every row.

    One behaviour is worth stating in the interface rather than leaving as a surprise: a label
    that content is already filed under is deactivated instead of deleted, so a published post
    never loses the category it was published with. The button therefore says what will actually
    happen, and the table shows the usage count that decides it.
--}}
<x-admin-layout title="หมวดหมู่และแท็ก">
    <section data-admin-page="taxonomy" aria-busy="true">
        <h1 class="ui-section-title">หมวดหมู่และแท็ก</h1>
        <p class="ui-body mt-2 max-w-3xl">
            ป้ายกำกับที่ใช้จัดหมวดเนื้อหาในระบบ รายการที่ยังมีเนื้อหาอ้างอิงอยู่จะถูกปิดใช้งานแทนการลบ
            เพื่อให้เนื้อหาที่เผยแพร่ไปแล้วไม่สูญเสียหมวดหมู่เดิม
        </p>

        <div data-admin-loading class="ui-loading mt-6" role="status">กำลังโหลดหมวดหมู่และแท็ก…</div>

        <div data-admin-content class="mt-6 hidden grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ([
                ['kind' => 'category', 'title' => 'หมวดหมู่', 'hint' => 'ใช้จัดกลุ่มหลักของเนื้อหา แสดงเป็นชิปกรองบนหน้าความรู้ภาษีและข่าวสาร'],
                ['kind' => 'tag', 'title' => 'แท็ก', 'hint' => 'ใช้เชื่อมโยงเนื้อหาข้ามหมวดหมู่ เนื้อหาหนึ่งชิ้นมีได้หลายแท็ก'],
            ] as $panel)
                <section class="ui-card" aria-labelledby="{{ $panel['kind'] }}-heading">
                    <h2 id="{{ $panel['kind'] }}-heading" class="ui-form-title">{{ $panel['title'] }}</h2>
                    <p class="ui-help">{{ $panel['hint'] }}</p>

                    <form data-taxonomy-form="{{ $panel['kind'] }}" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <label class="ui-field">ชื่อ<span class="ui-field-required" aria-hidden="true">*</span>
                            <input name="name" required maxlength="255" placeholder="เช่น {{ $panel['kind'] === 'category' ? 'การวางแผนภาษี' : 'ค่าลดหย่อน' }}">
                        </label>
                        <label class="ui-field">Slug (URL)<span class="ui-field-required" aria-hidden="true">*</span>
                            <input name="slug" required maxlength="191" class="font-mono text-sm"
                                   placeholder="{{ $panel['kind'] === 'category' ? 'tax-planning' : 'allowance' }}">
                        </label>
                        <button type="submit" class="ui-button-primary">เพิ่ม</button>
                    </form>

                    <div class="ui-scroll-x mt-5">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th scope="col">ชื่อ</th>
                                    <th scope="col">Slug</th>
                                    <th scope="col">เนื้อหาที่ใช้</th>
                                    <th scope="col">สถานะ</th>
                                    <th scope="col"><span class="sr-only">จัดการ</span></th>
                                </tr>
                            </thead>
                            <tbody data-taxonomy-rows="{{ $panel['kind'] }}"></tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>

        <p data-admin-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
    </section>
</x-admin-layout>
