{{--
    Milestone 09.1 — the knowledge and news listings, reconciled with panels 5 and 6 of the mockup.

    Before M9.1 this was a four-field grey filter form above a grid of plain boxes whose only
    metadata was a lowercase type string. The mockup shows a titled header, a single search box
    with a magnifier button, a row of category chips with the active one filled blue, and wide
    list rows carrying a thumbnail, a category chip, a date and an excerpt.

    The chips are links rather than a select, so the current filter is visible without opening a
    menu and each filtered view has its own URL. The tag and tax-year filters stay available as a
    disclosure, because they are useful but not what the mockup leads with.
--}}
@php
    $activeCategory = request('category');
    $hasFilters = filled(request('q')) || filled($activeCategory) || filled(request('tag')) || filled(request('tax_year'));
@endphp
<x-layout :title="$heading . ' — ภาษีได้ง่าย'" :description="$intro">
    <header class="max-w-2xl">
        <h1 class="ui-section-title">{{ $heading }}</h1>
        <p class="ui-lede mt-3">{{ $intro }}</p>
    </header>

    <form method="get" id="content-search" class="mt-7">
        <div class="flex gap-2">
            <label class="sr-only" for="content-search-input">ค้นหาเนื้อหา</label>
            <input id="content-search-input" name="q" value="{{ request('q') }}"
                   placeholder="ค้นหาจากชื่อเรื่องหรือคำอธิบาย" class="!mt-0">
            @foreach (['tag', 'tax_year', 'category'] as $carried)
                @if (filled(request($carried)))<input type="hidden" name="{{ $carried }}" value="{{ request($carried) }}">@endif
            @endforeach
            <button class="ui-button-primary shrink-0" type="submit">
                <x-icon name="search" class="size-4" /><span class="sr-only sm:not-sr-only">ค้นหา</span>
            </button>
        </div>

        <div class="ui-scroll-x mt-4">
            <div class="flex min-w-max gap-2">
                <a href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}"
                   @class(['ui-chip', 'ui-chip-active' => blank($activeCategory)])
                   @if (blank($activeCategory)) aria-current="true" @endif>ทั้งหมด</a>
                @foreach ($categories as $category)
                    <a href="{{ request()->fullUrlWithQuery(['category' => $category->slug, 'page' => null]) }}"
                       @class(['ui-chip', 'ui-chip-active' => $activeCategory === $category->slug])
                       @if ($activeCategory === $category->slug) aria-current="true" @endif>{{ $category->name }}</a>
                @endforeach
            </div>
        </div>

        <details class="mt-4" @if (filled(request('tag')) || filled(request('tax_year'))) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-blue-700">ตัวกรองเพิ่มเติม</summary>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <label class="ui-field">แท็ก
                    <select name="tag">
                        <option value="">ทั้งหมด</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->slug }}" @selected(request('tag') === $tag->slug)>{{ $tag->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ui-field">ปีภาษี<span class="ui-field-note">ไม่บังคับ</span>
                    <input name="tax_year" inputmode="numeric" value="{{ request('tax_year') }}" placeholder="เช่น 2568">
                </label>
                <button class="ui-button-secondary" type="submit">ใช้ตัวกรอง</button>
            </div>
        </details>
    </form>

    @if ($posts->isEmpty())
        @if ($hasFilters)
            <x-empty-state class="mt-8" icon="search" title="ไม่พบเนื้อหาที่ตรงกับเงื่อนไข"
                           message="ลองใช้คำค้นที่สั้นลง หรือล้างตัวกรองเพื่อดูเนื้อหาทั้งหมด"
                           actionLabel="ล้างตัวกรองทั้งหมด" :actionHref="url()->current()" />
        @else
            <x-empty-state class="mt-8" icon="book" title="ยังไม่มีเนื้อหาเผยแพร่ในหมวดนี้"
                           message="เมื่อทีมงานเผยแพร่เนื้อหา รายการจะปรากฏที่นี่ ระหว่างนี้คุณเริ่มทดลองคำนวณได้เลย"
                           actionLabel="เริ่มทดลองคำนวณ" :actionHref="route('simulator.index')" />
        @endif
    @else
        <p class="mt-7 text-sm text-slate-500">พบ {{ $posts->total() }} รายการ</p>
        <ul class="mt-3 grid gap-4">
            @foreach ($posts as $post)
                <x-article-card :post="$post" layout="row" heading="h2" />
            @endforeach
        </ul>
        <div class="mt-8">{{ $posts->links() }}</div>
    @endif
</x-layout>
