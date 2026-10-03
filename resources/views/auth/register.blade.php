<x-layout title="สมัครสมาชิก — ภาษีได้ง่าย" :noindex="true">
    <section class="mx-auto grid grid-cols-1 max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:grid-cols-[.9fr_1.1fr]" data-auth-page="register">
        <div class="hidden bg-gradient-to-br from-blue-700 to-blue-500 p-10 text-white md:flex md:flex-col md:justify-between">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-white/15"><x-icon name="bookmark" class="size-8" /></span>
            <div><p class="text-sm font-semibold text-blue-100">บัญชีสมาชิก</p><h2 class="mt-2 text-3xl leading-snug font-bold">บันทึกแบบร่าง แล้วกลับมาวางแผนต่อ</h2><p class="mt-4 leading-7 text-blue-100">การทดลองแบบผู้เยี่ยมชมยังใช้งานได้โดยไม่ต้องสมัครสมาชิก</p></div>
        </div>
        <div class="p-6 sm:p-10">
            <a href="{{ route('simulator.index') }}" class="ui-button-quiet -ml-2 mb-5"><x-icon name="arrow-left" class="size-4" /> กลับไปหน้าทดลองคำนวณ</a>
            <p class="ui-eyebrow">สร้างบัญชี</p><h1 class="ui-section-title mt-1">สมัครสมาชิก</h1><p class="mt-3 text-slate-600">บันทึกแบบร่างและกลับมาทำต่อได้ภายหลัง</p>
            <div data-auth-alert class="mt-5 hidden ui-alert" role="alert" aria-live="polite"></div>
            <form class="mt-6 space-y-5" data-auth-form><label class="ui-field">ชื่อ <span class="ui-field-required">จำเป็น</span><input name="name" autocomplete="name" required></label><label class="ui-field">อีเมล <span class="ui-field-required">จำเป็น</span><input name="email" type="email" autocomplete="email" required></label><label class="ui-field">รหัสผ่าน <span class="ui-field-required">จำเป็น</span><input name="password" type="password" autocomplete="new-password" minlength="12" aria-describedby="password-help" required><span id="password-help" class="ui-help block">อย่างน้อย 12 ตัว มีตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และสัญลักษณ์</span></label><label class="ui-field">ยืนยันรหัสผ่าน <span class="ui-field-required">จำเป็น</span><input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></label><button class="ui-button-primary w-full" type="submit">สมัครสมาชิก</button></form>
            <p class="mt-6 text-center text-sm">มีบัญชีแล้ว? <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:underline">เข้าสู่ระบบ</a></p>
        </div>
    </section>
</x-layout>
