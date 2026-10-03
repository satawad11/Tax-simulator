{{--
    Milestone 09.1 — the FAQ page.

    The disclosures stay native <details> elements: they are keyboard-operable, announce their own
    expanded state, and work before JavaScript runs, which no hand-rolled accordion would. M9.1
    changes only their presentation — a chevron that rotates when open, a card that keeps its
    border, and a heading that reads as a question rather than a bold paragraph.
--}}
<x-layout title="คำถามที่พบบ่อย — ภาษีได้ง่าย"
          description="คำถามที่พบบ่อยเกี่ยวกับการใช้งานระบบจำลองภาษีเงินได้บุคคลธรรมดา">
    <header class="max-w-2xl">
        <h1 class="ui-section-title">คำถามที่พบบ่อย</h1>
        <p class="ui-lede mt-3">รวมคำถามที่ผู้ใช้ถามบ่อยเกี่ยวกับการเตรียมข้อมูล การทดลองคำนวณ และการอ่านผล</p>
    </header>

    @if ($faqs->isEmpty())
        <x-empty-state class="mt-8" icon="info" title="ยังไม่มีคำถามที่เผยแพร่"
                       message="ทีมงานกำลังเตรียมคำตอบ ระหว่างนี้อ่านคู่มือการใช้งานหรือเริ่มทดลองคำนวณได้ทันที"
                       actionLabel="ดูความรู้ภาษี" :actionHref="route('content.knowledge')" />
    @else
        <div class="mt-8 space-y-3">
            @foreach ($faqs as $faq)
                <details class="group ui-card">
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                        <h2 class="text-base font-bold text-[#0f2c5c] sm:text-lg">{{ $faq->title }}</h2>
                        <span class="mt-0.5 shrink-0 text-blue-600 transition group-open:rotate-90" aria-hidden="true">
                            <x-icon name="chevron-right" />
                        </span>
                    </summary>
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <x-content-body :blocks="$faq->blocks" />
                    </div>
                </details>
            @endforeach
        </div>

        <div class="ui-note ui-note-info mt-8">
            <span class="ui-note-icon bg-blue-600" aria-hidden="true"><x-icon name="info" class="size-3.5" /></span>
            <p><strong class="font-bold">ยังไม่พบคำตอบที่ต้องการ?</strong>
                ลองอ่าน<a href="{{ route('content.knowledge') }}" class="font-semibold underline">คู่มือการใช้งาน</a>
                ซึ่งอธิบายขั้นตอนตั้งแต่เตรียมข้อมูลจนถึงการอ่านผลการคำนวณ</p>
        </div>
    @endif
</x-layout>
