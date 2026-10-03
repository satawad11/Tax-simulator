<x-layout title="เข้าสู่ระบบ — ภาษีได้ง่าย" :noindex="true">
    <section class="mx-auto grid grid-cols-1 max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:grid-cols-[.9fr_1.1fr]" data-auth-page="login">
        <div class="hidden bg-gradient-to-br from-blue-700 to-blue-500 p-10 text-white md:flex md:flex-col md:justify-between">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-white/15"><x-icon name="calculator" class="size-8" /></span>
            <div><p class="text-sm font-semibold text-blue-100">พื้นที่ส่วนตัวของคุณ</p><h2 class="mt-2 text-3xl leading-snug font-bold">กลับมาทำแบบจำลองต่อได้ทุกเวลา</h2><p class="mt-4 leading-7 text-blue-100">ดูแบบร่าง ประวัติผลคำนวณ และสถานการณ์วางแผนที่บันทึกไว้ในที่เดียว</p></div>
        </div>
        <div class="p-6 sm:p-10">
            <a href="{{ route('simulator.index') }}" class="ui-button-quiet -ml-2 mb-5"><x-icon name="arrow-left" class="size-4" /> กลับไปหน้าทดลองคำนวณ</a>
            <p class="ui-eyebrow">สมาชิก</p><h1 class="ui-section-title mt-1">เข้าสู่ระบบ</h1><p class="mt-3 text-slate-600">กลับไปดูแบบร่าง ผลการคำนวณ และแผนภาษีของคุณ</p>
            <div data-auth-alert class="mt-5 hidden ui-alert" role="alert" aria-live="polite"></div>
            <form class="mt-6 space-y-5" data-auth-form><label class="ui-field">อีเมล <span class="ui-field-required">จำเป็น</span><input name="email" type="email" autocomplete="email" required></label><label class="ui-field">รหัสผ่าน <span class="ui-field-required">จำเป็น</span><input name="password" type="password" autocomplete="current-password" required></label><button class="ui-button-primary w-full" type="submit">เข้าสู่ระบบ</button></form>
            <p class="mt-4 text-center text-sm"><a href="{{ route('password.forgot') }}" class="font-semibold text-blue-700 hover:underline">ลืมรหัสผ่าน?</a></p>
            <p class="mt-6 text-center text-sm">ยังไม่มีบัญชี? <a href="{{ route('register') }}" class="font-semibold text-blue-700 hover:underline">สมัครสมาชิก</a></p>
        </div>
    </section>
</x-layout>
