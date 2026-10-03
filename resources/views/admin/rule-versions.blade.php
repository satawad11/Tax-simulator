{{-- Milestone 09.1 — the rule-version list, on the shared table scale. --}}
<x-admin-layout title="ชุดกฎภาษี">
    <section data-admin-page="rule-versions" aria-busy="true">
        <h1 class="ui-section-title">ชุดกฎภาษี</h1>
        <p class="ui-body mt-2 max-w-3xl">
            ชุดกฎที่เผยแพร่แล้วแก้ไขไม่ได้ หากต้องแก้ ให้ทำสำเนาเป็นฉบับร่าง แก้ไข ตรวจสอบ แล้วจึงเผยแพร่ฉบับใหม่
            แบบจำลองที่บันทึกไว้จะยังผูกกับชุดกฎเดิมที่ใช้คำนวณ ผลเก่าจึงไม่เปลี่ยนย้อนหลัง
        </p>

        <div data-admin-loading class="ui-loading mt-6" role="status">กำลังโหลดชุดกฎ…</div>

        <div data-admin-content class="ui-card mt-6 hidden p-0 sm:p-0">
            <div class="ui-scroll-x">
                <table class="ui-table min-w-[44rem]">
                    <thead>
                        <tr>
                            <th scope="col">เวอร์ชัน</th>
                            <th scope="col">ปีภาษี</th>
                            <th scope="col">สถานะ</th>
                            <th scope="col">เผยแพร่เมื่อ</th>
                            <th scope="col" class="text-right"><span class="sr-only">จัดการ</span></th>
                        </tr>
                    </thead>
                    <tbody data-version-rows></tbody>
                </table>
            </div>
        </div>

        <p data-admin-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
    </section>
</x-admin-layout>
