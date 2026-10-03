{{-- Milestone 09.1 — the console overview, on the shared card and loading scales. --}}
<x-admin-layout title="ภาพรวม">
    <section data-admin-page="dashboard" aria-busy="true">
        <h1 class="ui-section-title">ภาพรวม</h1>
        <p class="ui-body mt-2 max-w-3xl">สรุปสถานะเนื้อหาและชุดกฎภาษีในระบบ พร้อมกิจกรรมล่าสุดจากบันทึกการตรวจสอบ</p>

        <div data-admin-loading class="ui-loading mt-6" role="status">กำลังโหลดข้อมูลผู้ดูแล…</div>

        <div data-admin-content class="hidden">
            {{-- Actionable items first: what an operator should do next, before the raw counts. --}}
            <div data-dashboard-attention class="mt-6 grid grid-cols-1 gap-3 lg:grid-cols-2"></div>

            <div data-dashboard-counts class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3"></div>

            <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="ui-card">
                    <h2 class="ui-form-title">ชุดกฎภาษีที่เผยแพร่</h2>
                    <ul data-dashboard-versions class="mt-3 space-y-2 text-sm leading-6"></ul>
                </section>
                <section class="ui-card">
                    <h2 class="ui-form-title">กิจกรรมล่าสุด</h2>
                    <ul data-dashboard-activity class="mt-3 space-y-2 text-sm leading-6"></ul>
                </section>
            </div>
        </div>

        <p data-admin-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
    </section>
</x-admin-layout>
