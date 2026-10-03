<x-layout :title="$title.' — ภาษีได้ง่าย'" :noindex="true">
    {{-- The landing page for an emailed link. It reports an outcome and offers a way onward. --}}
    <section class="mx-auto max-w-xl text-center">
        <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm sm:p-12">
            <span @class(['mx-auto flex size-16 items-center justify-center rounded-2xl',
                          'bg-emerald-50 text-emerald-600' => $success,
                          'bg-amber-50 text-amber-600' => ! $success])>
                <x-icon :name="$success ? 'shield' : 'warning'" class="size-8" />
            </span>
            <h1 class="ui-section-title mt-6">{{ $title }}</h1>
            <p class="mt-3 leading-7 text-slate-600">{{ $message }}</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('dashboard') }}" class="ui-button-primary">ไปที่แดชบอร์ด</a>
                <a href="{{ route('home') }}" class="ui-button-secondary">กลับหน้าแรก</a>
            </div>
        </div>
    </section>
</x-layout>
