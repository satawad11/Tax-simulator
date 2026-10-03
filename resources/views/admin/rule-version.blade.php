{{--
    Milestone 09.1 — one rule version in detail, on the shared card and note scales.

    The two conditions that block an action are stated as notes rather than as silently missing
    buttons: a published or archived version is immutable, and a version already referenced by a
    saved simulation cannot be deleted at all.
--}}
<x-admin-layout title="รายละเอียดชุดกฎ">
    <section data-admin-page="rule-version" data-version-id="{{ $versionId }}" aria-busy="true">
        <nav class="ui-meta mb-5" aria-label="เส้นทางหน้า">
            <a href="{{ route('admin.rule-versions') }}" class="hover:text-blue-700">ชุดกฎภาษี</a>
            <x-icon name="chevron-right" class="size-3" />
            <span class="font-semibold text-slate-700">รายละเอียด</span>
        </nav>

        <div data-admin-loading class="ui-loading" role="status">กำลังโหลดรายละเอียดชุดกฎ…</div>

        <div data-admin-content class="hidden">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 data-version-title class="ui-section-title"></h1>
                    <p data-version-meta class="ui-body mt-1"></p>
                </div>
                <div data-version-actions class="flex flex-wrap gap-2"></div>
            </div>

            <div data-version-lock class="ui-note ui-note-info mt-5 hidden">
                <span class="ui-note-icon bg-blue-600" aria-hidden="true"><x-icon name="info" class="size-3.5" /></span>
                <p><strong class="font-bold">ชุดกฎนี้แก้ไขไม่ได้</strong> — เผยแพร่หรือจัดเก็บแล้ว การป้องกันนี้บังคับทั้งในหน้านี้และที่ระดับโมเดล</p>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <section class="ui-card lg:col-span-2">
                    <h2 class="ui-form-title">จำนวนกฎในชุดนี้</h2>
                    <dl data-version-counts class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2"></dl>
                    <p data-version-sources class="ui-help"></p>
                    <div data-version-history class="ui-note ui-note-warning mt-4 hidden">
                        <span class="ui-note-icon bg-amber-600" aria-hidden="true"><x-icon name="warning" class="size-3.5" /></span>
                        <p><strong class="font-bold">ลบไม่ได้</strong> — ชุดกฎนี้ถูกอ้างอิงโดยแบบจำลองที่บันทึกไว้แล้ว</p>
                    </div>
                </section>

                <section class="ui-card">
                    <h2 class="ui-form-title">ผลตรวจสอบ</h2>
                    <div data-version-validation class="ui-body mt-3"></div>
                </section>
            </div>

            <section class="ui-card mt-6">
                <h2 class="ui-form-title">ข้อมูลกฎ</h2>
                <p class="ui-help">เลือกประเภทกฎเพื่อดูข้อมูลในชุดนี้</p>
                <div data-rule-resources class="mt-3 flex flex-wrap gap-2"></div>
                <div data-rule-table class="ui-scroll-x mt-4 text-sm"></div>
            </section>
        </div>

        <p data-admin-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
    </section>
</x-admin-layout>
