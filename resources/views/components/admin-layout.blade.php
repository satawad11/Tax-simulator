{{--
    Milestone 09.1 — the admin console shell: a sidebar console rather than a site with a menu bar.

    A console is a workspace, and a workspace wants its navigation down the side where it can hold
    a grouped list without competing with the page for horizontal room. The sidebar has two states
    for two different questions:

      below lg   it is a drawer — off-canvas, opened over the page with a backdrop, closed by the
                 backdrop, Escape, or following a link;
      lg and up  it is part of the layout, and can be collapsed to its icons when the page below
                 needs the width. The choice is remembered.

    Both flags live on <html> and are set by a tiny inline script before first paint, so a
    collapsed sidebar is never drawn wide and then snapped narrow.

    The console holds no data: every value on every page is fetched from the authorised API, and
    `data-visible-to="admin"` decides only what is worth offering, never what is permitted.
--}}
@php
    $groups = [
        'ภาพรวม' => [
            ['route' => 'admin.dashboard', 'label' => 'ภาพรวม', 'icon' => 'chart', 'match' => 'admin.dashboard'],
        ],
        'เนื้อหา' => [
            ['route' => 'admin.content', 'label' => 'บทความและข่าว', 'icon' => 'news', 'match' => 'admin.content*'],
            ['route' => 'admin.taxonomy', 'label' => 'หมวดหมู่และแท็ก', 'icon' => 'tag', 'match' => 'admin.taxonomy'],
        ],
        'กฎภาษี' => [
            ['route' => 'admin.tax-years', 'label' => 'ปีภาษี', 'icon' => 'calendar', 'match' => 'admin.tax-years'],
            ['route' => 'admin.rule-versions', 'label' => 'ชุดกฎภาษี', 'icon' => 'scale', 'match' => 'admin.rule-version*'],
            ['route' => 'admin.tax-sources', 'label' => 'เอกสารอ้างอิง', 'icon' => 'book', 'match' => 'admin.tax-sources'],
        ],
        'ระบบ' => [
            ['route' => 'admin.users', 'label' => 'บัญชีผู้ใช้', 'icon' => 'users', 'match' => 'admin.users'],
            ['route' => 'admin.audit-logs', 'label' => 'บันทึกการตรวจสอบ', 'icon' => 'clock', 'match' => 'admin.audit-logs'],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="th" data-sidebar-open="false" data-sidebar-collapsed="false">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'ผู้ดูแลระบบ' }} — ภาษีได้ง่าย</title>
    <script>
        // Applied before first paint so a remembered collapsed sidebar never flashes open.
        try {
            const collapsed = window.localStorage.getItem('tax-simulator.admin-sidebar') === 'collapsed';
            document.documentElement.dataset.sidebarCollapsed = String(collapsed);
            document.addEventListener('DOMContentLoaded', () => {
                const sidebar = document.getElementById('admin-sidebar');
                if (sidebar) sidebar.dataset.collapsed = String(collapsed);
            });
        } catch (error) { /* Private browsing: the sidebar simply starts expanded. */ }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-slate-50 font-sans text-slate-700 antialiased">
    <a href="#admin-content"
       class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[60] focus:rounded-lg focus:bg-blue-600 focus:px-4 focus:py-2 focus:font-semibold focus:text-white">
        ข้ามไปยังเนื้อหา
    </a>

    <div class="flex min-h-dvh">
        {{-- The backdrop exists only while the drawer is open; it is inert to assistive tech. --}}
        <div data-sidebar-backdrop hidden class="admin-backdrop" aria-hidden="true"></div>

        <aside id="admin-sidebar" data-visible-to="admin" hidden data-collapsed="false" class="admin-sidebar"
               aria-label="เมนูผู้ดูแลระบบ">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-100 px-4">
                <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-3">
                    <span class="ui-brand-mark shrink-0"><x-icon name="shield" class="size-6" /></span>
                    <span class="admin-sidebar-label min-w-0 leading-tight">
                        <span class="block truncate font-bold text-blue-800">ภาษีได้ง่าย</span>
                        <span class="block truncate text-xs text-slate-500">ผู้ดูแลระบบ</span>
                    </span>
                </a>
                {{-- Closes the drawer; only ever on a small screen. --}}
                <button type="button" data-sidebar-close
                        class="ml-auto rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                    <x-icon name="close" class="size-5" />
                    <span class="sr-only">ปิดเมนู</span>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="รายการหน้าผู้ดูแลระบบ">
                @foreach ($groups as $groupLabel => $items)
                    <p class="admin-nav-group">{{ $groupLabel }}</p>
                    <ul class="space-y-1">
                        @foreach ($items as $item)
                            @php $active = request()->routeIs($item['match']); @endphp
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   @class(['admin-nav-item', 'admin-nav-item-active' => $active])
                                   @if ($active) aria-current="page" @endif
                                   title="{{ $item['label'] }}">
                                    <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                                    <span class="admin-sidebar-label truncate">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>

            <div class="shrink-0 border-t border-slate-100 p-3">
                {{-- Who is signed in, at the foot of the rail where a console usually puts it.
                     The initial stands in for an avatar; the product stores no picture. --}}
                <div data-visible-to="admin" hidden class="mb-2 flex items-center gap-3 rounded-xl bg-slate-50 p-2.5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white"
                          data-viewer-initial aria-hidden="true">ผ</span>
                    <span class="admin-sidebar-label min-w-0 leading-tight">
                        <span data-viewer-name class="block truncate text-sm font-semibold text-[#0f2c5c]">—</span>
                        <span class="block text-xs text-slate-500">ผู้ดูแลระบบ</span>
                    </span>
                </div>

                <a href="{{ route('home') }}" class="admin-nav-item" title="กลับไปหน้าเว็บ">
                    <x-icon name="arrow-left" class="size-5 shrink-0" />
                    <span class="admin-sidebar-label truncate">กลับไปหน้าเว็บ</span>
                </a>
                {{-- Collapse is a desktop affordance; on a phone the drawer simply closes. --}}
                <button type="button" data-sidebar-collapse
                        class="admin-nav-item hidden w-full lg:flex" aria-controls="admin-sidebar">
                    <x-icon name="panel-left" class="size-5 shrink-0" />
                    <span class="admin-sidebar-label truncate" data-sidebar-collapse-label>ย่อเมนู</span>
                </button>
            </div>
        </aside>

        <div class="admin-main flex flex-col">
            <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                <button type="button" data-sidebar-open aria-controls="admin-sidebar" aria-expanded="false"
                        class="rounded-lg p-2.5 text-slate-600 transition hover:bg-blue-50 hover:text-blue-700 lg:hidden">
                    <x-icon name="menu" class="size-6" />
                    <span class="sr-only">เปิดเมนูผู้ดูแลระบบ</span>
                </button>

                {{-- A location, not a repeat of the page's own heading below it. --}}
                <nav class="ui-meta min-w-0" aria-label="เส้นทางหน้า">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-700">ผู้ดูแลระบบ</a>
                    @if (($title ?? null) && $title !== 'ภาพรวม')
                        <x-icon name="chevron-right" class="size-3" />
                        <span class="truncate font-semibold text-slate-700">{{ $title }}</span>
                    @endif
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <button type="button" data-visible-to="member admin" hidden data-admin-signout
                            class="ui-button-secondary">ออกจากระบบ</button>
                    <a data-visible-to="guest" hidden href="{{ route('admin.login') }}" class="ui-button-primary">เข้าสู่ระบบ</a>
                </div>
            </header>

            {{-- A console reads tables and side-by-side panels, so it uses the width it is given;
                 the cap only stops a line of prose running the length of an ultrawide screen. --}}
            <main id="admin-content" class="w-full max-w-[100rem] flex-1 px-4 py-8 sm:px-6 lg:px-8">
                {{--
                    Two different reasons the console can show nothing, told apart rather than
                    collapsed into one message: nobody is signed in, or somebody is but their
                    account is not an administrator.
                --}}
                <div data-admin-guard hidden class="mb-6 ui-note ui-note-warning" role="alert">
                    <span class="ui-note-icon bg-amber-600" aria-hidden="true"><x-icon name="warning" class="size-3.5" /></span>
                    <div>
                        <p data-guard-signed-out hidden>
                            <strong class="font-bold">ยังไม่ได้เข้าสู่ระบบ</strong> —
                            <a href="{{ route('admin.login') }}" class="font-semibold underline">เข้าสู่ระบบ</a>
                            ด้วยบัญชีผู้ดูแลระบบเพื่อดูข้อมูลในหน้านี้
                        </p>
                        <p data-guard-not-admin hidden>
                            <strong class="font-bold">บัญชีนี้ไม่มีสิทธิ์ผู้ดูแลระบบ</strong> —
                            คุณเข้าสู่ระบบในชื่อ <span data-viewer-name class="font-semibold">—</span>
                            ซึ่งเป็นบัญชีผู้ใช้ทั่วไป
                            <a href="{{ route('dashboard') }}" class="font-semibold underline">ไปที่แดชบอร์ดของคุณ</a>
                            หรือออกจากระบบแล้วเข้าสู่ระบบด้วยบัญชีผู้ดูแล
                        </p>
                    </div>
                </div>

                {{ $slot }}
            </main>

            <footer class="px-4 pb-8 text-xs text-slate-400 sm:px-6 lg:px-8">
                คอนโซลผู้ดูแลระบบ · ข้อมูลทั้งหมดโหลดผ่าน API ที่ตรวจสอบสิทธิ์แล้ว
            </footer>
        </div>
    </div>

    {{-- Shared modals: the console asks for confirmation and single values through these rather
         than the browser's own prompt/confirm, which cannot be styled and are blocked in some
         environments. `ui.js` drives both by their data-attributes. --}}
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

    {{-- A multi-field modal for actions a single prompt cannot cover, e.g. cloning a rule set to a
         chosen year. `formDialog()` fills the body and reads the values back. --}}
    <dialog data-form-dialog class="m-auto w-[min(92vw,30rem)] rounded-2xl border border-slate-200 p-0 shadow-xl backdrop:bg-slate-950/40">
        <form method="dialog" class="p-6">
            <h2 data-form-dialog-title class="ui-form-title">ระบุข้อมูล</h2>
            <div data-form-dialog-body class="mt-4"></div>
            <div class="mt-6 flex justify-end gap-3">
                <button value="cancel" class="ui-button-secondary">ยกเลิก</button>
                <button value="confirm" data-form-dialog-confirm class="ui-button-primary">ยืนยัน</button>
            </div>
        </form>
    </dialog>
</body>
</html>
