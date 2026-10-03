<x-layout title="ลืมรหัสผ่าน — ภาษีได้ง่าย" :noindex="true">
    {{--
        Phase 1. The same two-column card as sign-in, so recovery does not feel like a different
        product. The panel wording is reassurance, not instruction: someone arrives here because
        something has already gone wrong for them.
    --}}
    <section class="mx-auto grid grid-cols-1 max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:grid-cols-[.9fr_1.1fr]"
             data-password-page="forgot">
        <div class="hidden bg-gradient-to-br from-blue-700 to-blue-500 p-10 text-white md:flex md:flex-col md:justify-between">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-white/15"><x-icon name="calculator" class="size-8" /></span>
            <div>
                <p class="text-sm font-semibold text-blue-100">กู้คืนบัญชี</p>
                <h2 class="mt-2 text-3xl leading-snug font-bold">ลืมรหัสผ่านไม่ใช่เรื่องใหญ่</h2>
                <p class="mt-4 leading-7 text-blue-100">แบบร่างและประวัติการคำนวณของคุณยังอยู่ครบ เราจะส่งลิงก์ตั้งรหัสผ่านใหม่ไปให้ทางอีเมล</p>
            </div>
        </div>
        <div class="p-6 sm:p-10">
            <a href="{{ route('login') }}" class="ui-button-quiet -ml-2 mb-5"><x-icon name="arrow-left" class="size-4" /> กลับไปหน้าเข้าสู่ระบบ</a>
            <p class="ui-eyebrow">สมาชิก</p>
            <h1 class="ui-section-title mt-1">ลืมรหัสผ่าน</h1>
            <p class="mt-3 text-slate-600">กรอกอีเมลที่ใช้สมัครสมาชิก เราจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้</p>

            <div data-password-alert class="mt-5 hidden ui-alert" role="alert" aria-live="polite"></div>

            <form class="mt-6 space-y-5" data-password-form>
                <label class="ui-field">อีเมล <span class="ui-field-required">จำเป็น</span>
                    <input name="email" type="email" autocomplete="email" required>
                    <span class="ui-help">หากอีเมลนี้มีบัญชีอยู่ในระบบ ลิงก์จะถูกส่งไปที่นั่น ลิงก์มีอายุ {{ config('auth.passwords.users.expire', 60) }} นาที</span>
                </label>
                <button class="ui-button-primary w-full" type="submit">ส่งลิงก์ตั้งรหัสผ่านใหม่</button>
            </form>

            <p class="mt-6 text-center text-sm">นึกรหัสผ่านออกแล้ว? <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:underline">เข้าสู่ระบบ</a></p>
        </div>
    </section>
</x-layout>
