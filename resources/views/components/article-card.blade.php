@props(['post', 'layout' => 'card', 'heading' => 'h3'])
{{--
    Milestone 09.1 — one card for every piece of content, in the two shapes the mockup shows.

    `card` is the stacked tile used in the home "บทความแนะนำ" strip: a bold two-line title with a
    category chip and a dated clock line beneath it. `row` is the wide listing item used on the
    knowledge and news pages: a square thumbnail on the left, then category, date, title, excerpt
    and an "อ่านต่อ" affordance.

    The title carries the only link, and it is stretched across the whole card, so the tile is
    clickable without nesting interactive elements inside one another.
--}}
@php
    $types = \App\Models\ContentPost::TYPE_LABELS;
    $icons = ['article' => 'book', 'guide' => 'lightbulb', 'news' => 'news', 'faq' => 'info'];
    $label = $post->category?->name ?? ($types[$post->type] ?? $post->type);
    $published = $post->published_at?->locale('th')->translatedFormat('j M Y');
@endphp

@if ($layout === 'row')
    <li class="ui-card ui-card-interactive relative flex gap-4 sm:gap-5">
        <span class="ui-thumb" aria-hidden="true"><x-icon :name="$icons[$post->type] ?? 'book'" class="size-8" /></span>
        <div class="min-w-0 flex-1">
            <div class="ui-meta">
                <span class="ui-badge">{{ $label }}</span>
                @if ($published)
                    <span class="inline-flex items-center gap-1"><x-icon name="clock" class="size-3.5" />{{ $published }}</span>
                @endif
                @if ($post->taxYear)
                    <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="size-3.5" />ปีภาษี {{ $post->taxYear->year }}</span>
                @endif
            </div>
            <{{ $heading }} class="mt-2 text-lg leading-7 font-bold text-[#0f2c5c]">
                <a href="{{ route('content.article', $post->slug) }}" class="after:absolute after:inset-0 hover:text-blue-700">{{ $post->title }}</a>
            </{{ $heading }}>
            @if ($post->excerpt)
                <p class="ui-body mt-1.5 line-clamp-2">{{ $post->excerpt }}</p>
            @endif
            <span class="ui-button-quiet mt-2 -ml-2.5">อ่านต่อ <x-icon name="arrow-right" class="size-4" /></span>
        </div>
    </li>
@else
    <article class="ui-card ui-card-interactive relative flex flex-col">
        <{{ $heading }} class="text-base leading-7 font-bold text-[#0f2c5c]">
            <a href="{{ route('content.article', $post->slug) }}" class="after:absolute after:inset-0 hover:text-blue-700">{{ $post->title }}</a>
        </{{ $heading }}>
        @if ($post->excerpt)
            <p class="ui-body mt-2 line-clamp-2 grow">{{ $post->excerpt }}</p>
        @endif
        <div class="ui-meta mt-4">
            <span class="ui-badge">{{ $label }}</span>
            @if ($published)
                <span class="inline-flex items-center gap-1"><x-icon name="clock" class="size-3.5" />{{ $published }}</span>
            @endif
        </div>
    </article>
@endif
