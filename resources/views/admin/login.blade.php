{{--
    Milestone 09.1 — admin sign-in.

    This page grants nothing by itself: it exchanges credentials for a token through the public
    auth endpoint, and the admin role is checked by the API on every protected call afterwards.

    It stands on its own rather than inside the console shell (`x-admin-layout`): the shell is a
    workspace with a sidebar and a breadcrumb, and a signed-out visitor has neither, so wrapping the
    login in it left a lonely card beside an empty rail under a breadcrumb to nowhere. This is the
    same two-column card the public sign-in uses, with the console's own branding.
--}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>เข้าสู่ระบบผู้ดูแล — ภาษีได้ง่าย</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-dvh items-center justify-center bg-slate-50 p-4 font-sans text-slate-700 antialiased">
    <main class="w-full max-w-4xl" data-admin-login-page>
        <section class="grid grid-cols-1 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:grid-cols-[.9fr_1.1fr]">
            {{-- The brand panel: hidden on a phone where it would only push the form below the fold. --}}
            <div class="hidden bg-gradient-to-br from-blue-700 to-blue-500 p-10 text-white md:flex md:flex-col md:justify-between">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20">
                    <x-icon name="shield" class="size-8" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-blue-100">คอนโซลผู้ดูแลระบบ</p>
                    <h2 class="mt-2 text-3xl leading-snug font-bold">จัดการเนื้อหาและชุดกฎภาษี</h2>
                    <p class="mt-4 leading-7 text-blue-100">ดูแลบทความ ปีภาษี ชุดกฎ บัญชีผู้ใช้ และบันทึกการตรวจสอบได้จากที่เดียว</p>
                </div>
            </div>

            <div class="p-6 sm:p-10">
                <p class="ui-eyebrow">ผู้ดูแลระบบ</p>
                <h1 class="ui-section-title mt-1">เข้าสู่ระบบ</h1>
                <p class="mt-3 text-slate-600">ใช้บัญชีเดิมของระบบ สิทธิ์ผู้ดูแลจะถูกตรวจสอบโดย API ทุกครั้งที่เรียกข้อมูล</p>

                <form data-admin-login class="mt-6 space-y-5">
                    <label class="ui-field">อีเมล<span class="ui-field-required" aria-hidden="true">*</span>
                        <input type="email" name="email" required autocomplete="username">
                    </label>
                    <label class="ui-field">รหัสผ่าน<span class="ui-field-required" aria-hidden="true">*</span>
                        <input type="password" name="password" required autocomplete="current-password">
                    </label>
                    <button type="submit" class="ui-button-primary w-full">เข้าสู่ระบบ</button>
                    <p data-admin-message role="status" aria-live="polite" class="text-sm"></p>
                </form>

                <p class="mt-6 text-center text-sm">
                    <a href="{{ route('home') }}" class="font-semibold text-blue-700 hover:underline">กลับไปหน้าเว็บ</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>
