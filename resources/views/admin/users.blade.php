{{--
    Phase 2 — account administration.

    Until this page existed, a second administrator could only be created by editing the database,
    an administrator who left could not be demoted through the product, and nobody could respond
    to a compromised account. That is a different problem from RBAC, and this is deliberately not
    an RBAC console: two roles, a search, and two actions.

    The page renders empty and asks the authorised API for every row. What it never shows is as
    deliberate as what it does: no password field, no token value, and nothing at all about a
    member's tax returns — not even a count, which was dropped on review because nothing on this
    page can act on it.
--}}
<x-admin-layout title="บัญชีผู้ใช้">
    <section data-admin-page="users" aria-busy="true">
        <h1 class="ui-section-title">บัญชีผู้ใช้</h1>
        <p class="ui-body mt-2 max-w-3xl">
            บัญชีทั้งหมดในระบบ ผู้ดูแลระบบแสดงก่อนเสมอ
            หน้านี้เปลี่ยนบทบาท ระงับบัญชี และออกจากระบบให้บัญชีอื่นได้
            แต่ไม่สามารถดูหรือตั้งรหัสผ่านของใครได้ และไม่แสดงข้อมูลแบบภาษีของสมาชิก
        </p>

        <div data-admin-loading class="ui-loading mt-6" role="status">กำลังโหลดบัญชีผู้ใช้…</div>

        <div data-admin-content class="mt-6 hidden">
            <form data-user-filters class="ui-card grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
                <label class="ui-field">ค้นหา
                    <input name="search" type="search" placeholder="ชื่อหรืออีเมล" autocomplete="off">
                </label>
                <label class="ui-field">บทบาท
                    <select name="role">
                        <option value="">ทั้งหมด</option>
                        <option value="admin">ผู้ดูแลระบบ</option>
                        <option value="member">สมาชิก</option>
                    </select>
                </label>
                <label class="ui-field">สถานะ
                    <select name="suspended">
                        <option value="">ทั้งหมด</option>
                        <option value="1">ถูกระงับ</option>
                        <option value="0">ใช้งานได้</option>
                    </select>
                </label>
                <button type="submit" class="ui-button-primary">ค้นหา</button>
            </form>

            <p data-user-summary class="ui-help mt-4" role="status" aria-live="polite"></p>

            <div class="ui-scroll-x mt-3">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th scope="col">ชื่อ</th>
                            <th scope="col">อีเมล</th>
                            <th scope="col">บทบาท</th>
                            <th scope="col">อุปกรณ์ที่ยังเข้าสู่ระบบ</th>
                            <th scope="col">ใช้งานล่าสุด</th>
                            <th scope="col">สมัครเมื่อ</th>
                            <th scope="col"><span class="sr-only">จัดการ</span></th>
                        </tr>
                    </thead>
                    <tbody data-user-rows></tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <button type="button" data-user-previous class="ui-button-quiet" disabled>ก่อนหน้า</button>
                <span data-user-page class="ui-help"></span>
                <button type="button" data-user-next class="ui-button-quiet" disabled>ถัดไป</button>
            </div>

            <div class="ui-note ui-note-info mt-6">
                <span class="ui-note-icon bg-blue-600">i</span>
                <div>
                    <p class="font-bold">ข้อจำกัดที่ตั้งใจไว้</p>
                    <p>
                        คุณเปลี่ยนบทบาทของบัญชีตนเองไม่ได้ และระบบต้องมีผู้ดูแลระบบเหลืออย่างน้อยหนึ่งบัญชีเสมอ
                        ทั้งสองข้อกันไม่ให้ใครล็อกตัวเองออกจากหน้านี้ด้วยการกดเพียงครั้งเดียว และคุณระงับบัญชีของตนเองไม่ได้ด้วยเหตุผลเดียวกัน
                        การระงับบัญชีไม่ลบข้อมูลใด ๆ คืนสิทธิ์ได้ทุกเมื่อ
                        การเปลี่ยนบทบาทและการออกจากระบบให้บัญชีอื่นถูกบันทึกไว้ในบันทึกการตรวจสอบทุกครั้ง
                    </p>
                </div>
            </div>
        </div>

        <p data-admin-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
    </section>
</x-admin-layout>
