{{--
    Milestone 09.1 — the header, reconciled with the approved mockup and aware of who is looking.

    Every audience-specific control declares its audience with `data-visible-to`, and session.js
    resolves the viewer once from /auth/me and applies it. Guests are offered sign-in; members get
    their own area; an administrator is additionally offered the console, which they previously
    had no link to anywhere.

    Elements start with the `hidden` attribute rather than a `hidden` class, because a responsive
    class such as `lg:inline-flex` overrides the class at that breakpoint and would put a hidden
    control back on screen. None of this authorises anything: the API checks every request.
--}}
@php
    $links = [
        ['route' => 'home', 'match' => 'home', 'label' => 'หน้าแรก'],
        ['route' => 'simulator.index', 'match' => 'simulator.*', 'label' => 'ทดลองคำนวณภาษี'],
        ['route' => 'content.knowledge', 'match' => 'content.knowledge', 'label' => 'ความรู้ภาษี'],
        ['route' => 'content.news', 'match' => 'content.news', 'label' => 'ข่าวสาร'],
        ['route' => 'content.faq', 'match' => 'content.faq', 'label' => 'คำถามที่พบบ่อย'],
    ];
@endphp
<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 shadow-[0_1px_3px_rgb(15_44_92/0.04)] backdrop-blur-lg supports-[backdrop-filter]:bg-white/70">
    <div class="ui-shell flex h-16 items-center justify-between gap-4 lg:h-20">
        <a href="{{ route('home') }}" class="group flex items-center gap-3">
            <span class="ui-brand-mark"><x-icon name="calculator" class="size-6" /></span>
            <span class="leading-tight">
                <span class="block text-lg font-bold text-blue-800">ภาษีได้ง่าย</span>
                <span class="hidden text-xs font-normal text-slate-500 sm:block">เรียนรู้ ทดลอง วางแผนภาษี</span>
            </span>
        </a>

        <nav id="primary-navigation" aria-label="เมนูหลัก"
             class="hidden lg:flex lg:items-center lg:gap-1 lg:rounded-full lg:bg-slate-50/80 lg:p-1 lg:ring-1 lg:ring-slate-200/70">
            @foreach ($links as $link)
                @php $active = request()->routeIs($link['match']); @endphp
                <a href="{{ route($link['route']) }}" @class(['ui-nav-link', 'ui-nav-link-active' => $active])
                   @if ($active) aria-current="page" @endif>{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ route('content.knowledge') }}#content-search"
               class="hidden rounded-full p-2.5 text-slate-500 transition hover:bg-blue-50 hover:text-blue-700 sm:inline-flex">
                <x-icon name="search" />
                <span class="sr-only">ค้นหาเนื้อหา</span>
            </a>

            {{-- A hairline keeps the account actions visually distinct from the utility icons. --}}
            <span data-visible-to="member admin" hidden class="mx-1 hidden h-6 w-px bg-slate-200 lg:block"></span>

            <a data-visible-to="guest" hidden href="{{ route('login') }}" class="ui-button-primary sm:inline-flex">เข้าสู่ระบบ</a>

            {{-- An administrator's home is the console; a member's is their own dashboard. --}}
            <a data-visible-to="admin" hidden href="{{ route('admin.dashboard') }}" class="ui-button-primary lg:inline-flex">
                <x-icon name="shield" class="size-4" />ผู้ดูแลระบบ
            </a>
            <a data-visible-to="member" hidden href="{{ route('dashboard') }}" class="ui-button-secondary lg:inline-flex">แดชบอร์ด</a>
            <a data-visible-to="member" hidden href="{{ route('dashboard.returns') }}" class="ui-button-secondary lg:inline-flex">แบบภาษีของฉัน</a>
            <button type="button" data-visible-to="member admin" hidden data-member-logout
                    class="ui-button-quiet lg:inline-flex">ออกจากระบบ</button>

            <button type="button" data-navigation-toggle aria-controls="mobile-navigation" aria-expanded="false"
                    class="rounded-lg p-2.5 text-slate-600 transition hover:bg-blue-50 hover:text-blue-700 lg:hidden">
                <x-icon name="menu" class="size-6" />
                <span class="sr-only">เปิดเมนู</span>
            </button>
        </div>
    </div>

    {{-- The mobile sheet. Hidden by default and toggled by navigation.js through aria-expanded. --}}
    <nav id="mobile-navigation" aria-label="เมนูหลัก (อุปกรณ์พกพา)" hidden
         class="border-t border-slate-200 bg-white lg:hidden">
        <div class="ui-shell flex flex-col gap-1 py-3">
            @foreach ($links as $link)
                @php $active = request()->routeIs($link['match']); @endphp
                <a href="{{ route($link['route']) }}"
                   @class(['rounded-lg px-3 py-3 text-sm font-medium text-slate-600 hover:bg-blue-50', 'bg-blue-50 font-semibold text-blue-700' => $active])
                   @if ($active) aria-current="page" @endif>{{ $link['label'] }}</a>
            @endforeach

            <p data-visible-to="member admin" hidden class="mt-3 border-t border-slate-100 px-3 pt-3 text-xs text-slate-500">
                เข้าสู่ระบบในชื่อ <span data-viewer-name class="font-semibold text-slate-700">—</span>
            </p>

            <a data-visible-to="guest" hidden href="{{ route('login') }}" class="ui-button-primary mt-2">เข้าสู่ระบบ</a>
            <a data-visible-to="admin" hidden href="{{ route('admin.dashboard') }}"
               class="rounded-lg px-3 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">ผู้ดูแลระบบ</a>
            <a data-visible-to="member" hidden href="{{ route('dashboard') }}"
               class="rounded-lg px-3 py-3 text-sm font-medium text-slate-600 hover:bg-blue-50">แดชบอร์ด</a>
            <a data-visible-to="member" hidden href="{{ route('dashboard.returns') }}"
               class="rounded-lg px-3 py-3 text-sm font-medium text-slate-600 hover:bg-blue-50">แบบภาษีของฉัน</a>
            <button type="button" data-visible-to="member admin" hidden data-member-logout
                    class="ui-button-secondary mt-2">ออกจากระบบ</button>
        </div>
    </nav>
</header>
