{{--
    Milestone 09.1 — a single article, guide or news item.

    The body is rendered by x-content-body from structured blocks, never as markup, so nothing on
    this page can emit a stored string as HTML. M9.1 changes the frame around it: a breadcrumb, a
    metadata line that reads as a line rather than a run of bullet characters, a quoted lede, and
    a footer that offers the next step instead of a bare back link.
--}}
@php
    // One vocabulary for the whole product, owned by the model — see ContentPost::TYPE_LABELS.
    $types = \App\Models\ContentPost::TYPE_LABELS;
    $statuses = \App\Models\ContentPost::STATUS_LABELS;
    $listing = $post->type === 'news' ? 'content.news' : 'content.knowledge';
    // The same view serves the live page and the admin preview; only this flag differs.
    $preview = $preview ?? false;
@endphp
<x-layout :title="($preview ? 'ตัวอย่าง — ' : '') . ($post->meta_title ?: $post->title) . ' — ภาษีได้ง่าย'"
          :description="$post->meta_description ?: $post->excerpt"
          :noindex="$preview">
    @if ($preview)
        {{--
            A preview of a *published* post is pixel-identical to the live page, so without this an
            administrator could believe an edit was live when it was still a draft. The banner
            names the status and says the link expires, because a signed URL is a credential in a
            string and whoever it is forwarded to should know it has a shelf life.
        --}}
        <div class="ui-note ui-note-warning mb-6" role="status">
            <span class="ui-note-icon bg-amber-600">!</span>
            <div>
                <p class="font-bold">
                    ตัวอย่างก่อนเผยแพร่ · สถานะปัจจุบัน: {{ $statuses[$post->status] ?? $post->status }}
                </p>
                <p>
                    หน้านี้แสดงให้ผู้ดูแลระบบดูเท่านั้น ยังไม่ปรากฏบนเว็บไซต์จริงจนกว่าจะกดเผยแพร่
                    ลิงก์ตัวอย่างนี้มีอายุจำกัดและจะใช้ไม่ได้เมื่อหมดอายุ
                </p>
            </div>
        </div>
    @endif

    <nav class="ui-meta mb-6" aria-label="เส้นทางหน้า">
        <a href="{{ route('home') }}" class="hover:text-blue-700">หน้าแรก</a>
        <x-icon name="chevron-right" class="size-3" />
        <a href="{{ route($listing) }}" class="hover:text-blue-700">{{ $post->type === 'news' ? 'ข่าวสาร' : 'ความรู้ภาษี' }}</a>
    </nav>

    <article class="mx-auto max-w-3xl">
        <div class="ui-meta">
            <span class="ui-badge">{{ $post->category?->name ?? ($types[$post->type] ?? $post->type) }}</span>
            @if ($post->published_at)
                <span class="inline-flex items-center gap-1"><x-icon name="clock" class="size-3.5" />
                    เผยแพร่ {{ $post->published_at->locale('th')->translatedFormat('j F Y') }}</span>
            @endif
            @if ($post->taxYear)
                <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="size-3.5" />ปีภาษี {{ $post->taxYear->year }}</span>
            @endif
        </div>

        <h1 class="ui-section-title mt-3">{{ $post->title }}</h1>

        @if ($post->excerpt)
            <p class="mt-5 border-l-4 border-blue-500 bg-blue-50 p-5 text-sm leading-8 text-blue-950">{{ $post->excerpt }}</p>
        @endif

        <div class="mt-7">
            <x-content-body :blocks="$blocks" />
        </div>

        {{--
            Phase 3 — attribution. On a news item, naming the announcement it came from is what
            lets a reader check it against the original, which matters more here than on most
            sites: this product will not state a tax rule its own sources do not print.

            `rel="noopener noreferrer"` because the link leaves the site, and the URL is validated
            to http/https on the way in so it can be rendered as a link at all.
        --}}
        @if ($post->source_name || $post->source_url)
            <p class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                ที่มา:
                @if ($post->source_url)
                    <a href="{{ $post->source_url }}" target="_blank" rel="noopener noreferrer"
                       class="font-semibold text-blue-700 hover:underline">{{ $post->source_name ?: $post->source_url }}</a>
                    <span class="ui-meta ml-1">(เปิดในแท็บใหม่)</span>
                @else
                    <span class="font-semibold text-slate-700">{{ $post->source_name }}</span>
                @endif
            </p>
        @endif

        @if ($post->tags->isNotEmpty())
            <ul class="mt-8 flex flex-wrap gap-2" aria-label="แท็ก">
                @foreach ($post->tags as $tag)
                    <li><a href="{{ route($listing, ['tag' => $tag->slug]) }}" class="ui-chip">{{ $tag->name }}</a></li>
                @endforeach
            </ul>
        @endif

        <div class="mt-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5">
            <a href="{{ route($listing) }}" class="ui-button-secondary">
                <x-icon name="arrow-left" class="size-4" />กลับไปหน้ารายการ
            </a>
            <a href="{{ route('simulator.index') }}" class="ui-button-primary">
                เริ่มทดลองคำนวณ <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>
    </article>
</x-layout>
