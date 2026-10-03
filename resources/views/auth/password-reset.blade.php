<x-layout title="ตั้งรหัสผ่านใหม่ — ภาษีได้ง่าย" :noindex="true">
    {{--
        The token comes from the path and the address from the query string, because the emailed
        link carries them that way and the broker verifies the pair. Both are hidden inputs rather
        than script-read values, so the form is complete before any JavaScript runs.
    --}}
    <section class="mx-auto grid grid-cols-1 max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:grid-cols-[.9fr_1.1fr]"
             data-password-page="reset">
        <div class="hidden bg-gradient-to-br from-blue-700 to-blue-500 p-10 text-white md:flex md:flex-col md:justify-between">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-white/15"><x-icon name="shield" class="size-8" /></span>
            <div>
                <p class="text-sm font-semibold text-blue-100">ขั้นตอนสุดท้าย</p>
                <h2 class="mt-2 text-3xl leading-snug font-bold">ตั้งรหัสผ่านใหม่</h2>
                <p class="mt-4 leading-7 text-blue-100">เมื่อตั้งรหัสผ่านใหม่แล้ว อุปกรณ์ทุกเครื่องที่เข้าสู่ระบบไว้จะถูกออกจากระบบ เพื่อความปลอดภัยของบัญชีคุณ</p>
            </div>
        </div>
        <div class="p-6 sm:p-10">
            <p class="ui-eyebrow">สมาชิก</p>
            <h1 class="ui-section-title mt-1">ตั้งรหัสผ่านใหม่</h1>
            <p class="mt-3 text-slate-600">ตั้งรหัสผ่านใหม่สำหรับบัญชีของคุณ</p>

            <div data-password-alert class="mt-5 hidden ui-alert" role="alert" aria-live="polite"></div>

            <form class="mt-6 space-y-5" data-password-form>
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="ui-field">อีเมล <span class="ui-field-required">จำเป็น</span>
                    <input name="email" type="email" autocomplete="email" value="{{ $email }}" required>
                </label>
                <label class="ui-field">รหัสผ่านใหม่ <span class="ui-field-required">จำเป็น</span>
                    <input name="password" type="password" autocomplete="new-password" required>
                    <span class="ui-help">อย่างน้อย 12 ตัวอักษร ประกอบด้วยตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และอักขระพิเศษ</span>
                </label>
                <label class="ui-field">ยืนยันรหัสผ่านใหม่ <span class="ui-field-required">จำเป็น</span>
                    <input name="password_confirmation" type="password" autocomplete="new-password" required>
                </label>
                <button class="ui-button-primary w-full" type="submit">ตั้งรหัสผ่านใหม่</button>
            </form>

            <p class="mt-6 text-center text-sm">ลิงก์หมดอายุแล้ว? <a href="{{ route('password.forgot') }}" class="font-semibold text-blue-700 hover:underline">ขอลิงก์ใหม่</a></p>
        </div>
    </section>
</x-layout>
