{{--
    Milestone 09.1 — the member area shell.

    The member pages are rendered by member-dashboard.js from the Sanctum API, so this file is the
    frame: the welcome header, the section tabs, and the three regions the renderer writes into.
    M9.1 gives the header the mockup's proportions, marks the current tab rather than leaving all
    tabs identical, and replaces the bare "กำลังโหลด" line with a skeleton that holds the page's
    shape while the request is in flight.
--}}
@php
    $tabs = [
        ['route' => 'dashboard', 'label' => 'ภาพรวม', 'icon' => 'chart', 'pages' => ['dashboard']],
        ['route' => 'dashboard.returns', 'label' => 'แบบภาษีของฉัน', 'icon' => 'document', 'pages' => ['returns', 'return', 'history', 'planning']],
        // Phase 4 — PUT /auth/me has existed since M5 with no page; a member could not correct
        // their own name. The password form lives at its own route from Phase 1 and is linked
        // from here rather than duplicated.
        ['route' => 'dashboard.account', 'label' => 'บัญชีของฉัน', 'icon' => 'user', 'pages' => ['account']],
    ];
@endphp
<x-layout title="แดชบอร์ด — ภาษีได้ง่าย" :noindex="true">
    <div data-member-page="{{ $page }}" data-tax-return-id="{{ $taxReturn }}">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="ui-eyebrow">พื้นที่สมาชิก</p>
                <h1 class="ui-section-title mt-1" data-page-title>แดชบอร์ด</h1>
                <p class="mt-2 text-slate-600" data-member-greeting>กำลังตรวจสอบบัญชี…</p>
            </div>
            <div class="flex flex-wrap gap-2">
                {{-- Shown only to an administrator; session.js applies data-visible-to. --}}
                <a data-visible-to="admin" hidden href="{{ route('admin.dashboard') }}" class="ui-button-secondary">
                    <x-icon name="shield" class="size-4" />ผู้ดูแลระบบ
                </a>
                <a href="{{ route('simulator.index') }}" class="ui-button-primary">
                    <x-icon name="plus" class="size-4" />เริ่มแบบจำลองใหม่
                </a>
            </div>
        </div>

        <nav class="ui-scroll-x mt-6" aria-label="เมนูสมาชิก">
            <div class="flex min-w-max gap-2 rounded-2xl border border-slate-200 bg-white p-1.5">
                @foreach ($tabs as $tab)
                    @php $active = in_array($page, $tab['pages'], true); @endphp
                    {{-- The icon makes the row scannable; the word is what makes it unambiguous. --}}
                    <a href="{{ route($tab['route']) }}" @class(['ui-tab gap-2', 'ui-tab-active' => $active])
                       @if ($active) aria-current="page" @endif>
                        <x-icon :name="$tab['icon']" class="size-4 shrink-0" />{{ $tab['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>

        <div data-member-alert class="mt-6 hidden ui-note ui-note-danger" role="alert" aria-live="polite"></div>

        <div data-member-loading class="mt-6 space-y-4" aria-live="polite" aria-busy="true">
            <span class="sr-only">กำลังโหลดข้อมูล</span>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="ui-skeleton h-24"></div><div class="ui-skeleton h-24"></div><div class="ui-skeleton h-24"></div>
            </div>
            <div class="ui-skeleton h-40"></div>
        </div>

        <main data-member-content class="mt-6"></main>
    </div>
</x-layout>
