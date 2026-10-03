<x-layout title="เปลี่ยนรหัสผ่าน — ภาษีได้ง่าย" :noindex="true">
    {{--
        Phase 1 delivers this one page rather than the whole account area, which is Phase 4: an
        endpoint nobody can reach closes nothing, and this is the only part of account settings
        that a member cannot work around. Name and email editing wait for the settings page.
    --}}
    <section class="mx-auto max-w-xl" data-password-page="change">
        <a href="{{ route('dashboard') }}" class="ui-button-quiet -ml-2 mb-5"><x-icon name="arrow-left" class="size-4" /> กลับไปแดชบอร์ด</a>
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
            <p class="ui-eyebrow">บัญชีของฉัน</p>
            <h1 class="ui-section-title mt-1">เปลี่ยนรหัสผ่าน</h1>
            <p class="mt-3 text-slate-600">ยืนยันรหัสผ่านปัจจุบันก่อน แล้วตั้งรหัสผ่านใหม่</p>

            <div data-password-alert class="mt-5 hidden ui-alert" role="alert" aria-live="polite"></div>

            <form class="mt-6 space-y-5" data-password-form>
                <label class="ui-field">รหัสผ่านปัจจุบัน <span class="ui-field-required">จำเป็น</span>
                    <input name="current_password" type="password" autocomplete="current-password" required>
                </label>
                <label class="ui-field">รหัสผ่านใหม่ <span class="ui-field-required">จำเป็น</span>
                    <input name="password" type="password" autocomplete="new-password" required>
                    <span class="ui-help">อย่างน้อย 12 ตัวอักษร ประกอบด้วยตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และอักขระพิเศษ</span>
                </label>
                <label class="ui-field">ยืนยันรหัสผ่านใหม่ <span class="ui-field-required">จำเป็น</span>
                    <input name="password_confirmation" type="password" autocomplete="new-password" required>
                </label>
                <button class="ui-button-primary w-full" type="submit">เปลี่ยนรหัสผ่าน</button>
            </form>

            <div class="ui-note ui-note-info mt-6">
                <span class="ui-note-icon bg-blue-600">i</span>
                <p>เมื่อเปลี่ยนรหัสผ่านสำเร็จ อุปกรณ์อื่นที่เข้าสู่ระบบไว้จะถูกออกจากระบบ ส่วนหน้าต่างนี้จะยังใช้งานต่อได้</p>
            </div>
        </div>
    </section>
</x-layout>
