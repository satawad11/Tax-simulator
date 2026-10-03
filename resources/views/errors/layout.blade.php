{{--
    Phase 4 — the shared body of every error page.

    `resources/views/errors/` did not exist, so a reader who mistyped an article slug left the
    product entirely: Laravel's own page, in English, in a different typeface, with no way back.
    These stay inside the product — same shell, same language, same navigation — and each one
    offers the route that is actually useful from where the reader is standing.

    Deliberately plain about what happened. An error page that explains nothing leaves the reader
    unsure whether to retry, wait, or go somewhere else, which is the only decision they have.
--}}
<x-layout :title="$title.' — ภาษีได้ง่าย'" :noindex="true">
    <section class="mx-auto max-w-2xl text-center">
        <p class="font-mono text-6xl font-bold text-blue-100 sm:text-7xl" aria-hidden="true">{{ $code }}</p>
        <h1 class="ui-section-title mt-2">{{ $title }}</h1>
        <p class="mt-4 leading-8 text-slate-600">{{ $message }}</p>

        @isset($hint)
            <p class="ui-note ui-note-info mt-6 text-left">
                <span class="ui-note-icon bg-blue-600">i</span>
                <span>{{ $hint }}</span>
            </p>
        @endisset

        <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="{{ route('home') }}" class="ui-button-primary">กลับหน้าแรก</a>
            <a href="{{ route('simulator.index') }}" class="ui-button-secondary">ทดลองคำนวณภาษี</a>
            <a href="{{ route('content.knowledge') }}" class="ui-button-secondary">ความรู้ภาษี</a>
        </div>
    </section>
</x-layout>
