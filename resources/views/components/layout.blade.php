{{-- Milestone 09.1 — one shell for every public and member page, in the approved visual direction. --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @isset($description)
        <meta name="description" content="{{ $description }}">
        <meta property="og:description" content="{{ $description }}">
    @endisset
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="ภาษีได้ง่าย">
    @if ($noindex ?? false)<meta name="robots" content="noindex,nofollow">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-700 antialiased">
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-blue-600 focus:px-4 focus:py-2 focus:font-semibold focus:text-white">
        ข้ามไปยังเนื้อหา
    </a>
    <x-navbar />

    {{-- `wide` lets the home page run its own full-bleed hero band; every other page is contained. --}}
    <main id="main-content" class="{{ ($wide ?? false) ? 'pb-16' : 'ui-shell py-8 sm:py-10' }}">
        {{ $slot }}
    </main>

    <footer class="mt-8 border-t border-slate-200 bg-white">
        <div class="ui-shell flex flex-col gap-6 py-10 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-md">
                <div class="flex items-center gap-3">
                    <span class="ui-brand-mark"><x-icon name="calculator" class="size-6" /></span>
                    <span class="text-lg font-bold text-blue-800">ภาษีได้ง่าย</span>
                </div>
                <p class="mt-3 text-sm leading-7 text-slate-500">
                    ระบบจำลองเพื่อการเรียนรู้และวางแผนภาษีเงินได้บุคคลธรรมดา ไม่ใช่ระบบยื่นแบบภาษีอย่างเป็นทางการ
                    ผลลัพธ์ทั้งหมดเป็นการประมาณการจากข้อมูลที่ผู้ใช้กรอกเอง
                </p>
            </div>
            <nav aria-label="เมนูส่วนท้าย" class="grid gap-2 text-sm">
                <a href="{{ route('simulator.index') }}" class="text-slate-600 hover:text-blue-700">ทดลองคำนวณภาษี</a>
                <a href="{{ route('content.knowledge') }}" class="text-slate-600 hover:text-blue-700">ความรู้ภาษี</a>
                <a href="{{ route('content.news') }}" class="text-slate-600 hover:text-blue-700">ข่าวสาร</a>
                <a href="{{ route('content.faq') }}" class="text-slate-600 hover:text-blue-700">คำถามที่พบบ่อย</a>
            </nav>
        </div>
    </footer>

    <dialog data-prompt-dialog class="m-auto w-[min(92vw,28rem)] rounded-2xl border border-slate-200 p-0 shadow-xl backdrop:bg-slate-950/40">
        <form method="dialog" class="p-6">
            <h2 data-prompt-title class="ui-form-title">ระบุข้อมูล</h2>
            <label data-prompt-field class="ui-field mt-4">ข้อมูล<input data-prompt-input autocomplete="off"></label>
            <div class="mt-6 flex justify-end gap-3">
                <button value="cancel" class="ui-button-secondary">ยกเลิก</button>
                <button value="confirm" class="ui-button-primary">ยืนยัน</button>
            </div>
        </form>
    </dialog>
</body>
</html>
