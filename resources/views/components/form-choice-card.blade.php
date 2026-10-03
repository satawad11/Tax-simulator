@props(['form', 'heading' => 'h3'])
{{--
    Milestone 09.1 — the ภ.ง.ด.90 / ภ.ง.ด.91 choice card, reconciled with panel 2 of the mockup.

    Before M9.1 these were two plain white boxes with a monochrome glyph and a short line of text,
    which gave the reader nothing to compare. The mockup gives each card a tinted icon tile —
    rose for ภ.ง.ด.90, blue for ภ.ง.ด.91 — the form code set large, the มาตรา range in
    parentheses, a left-aligned bullet list of what the form covers, and a full-width primary
    action. Both cards use the same skeleton so they read as a pair, and the tint is the only
    difference: the form code and the bullets carry the actual distinction.
--}}
@php
    $cards = [
        'pnd90' => [
            'code' => 'ภ.ง.ด.90',
            'sections' => '(มาตรา 40(1) - 40(8))',
            'summary' => 'สำหรับผู้มีเงินได้หลายประเภท',
            'tile' => 'bg-rose-50 text-rose-600',
            'points' => [
                'มีเงินเดือนและรายได้ประเภทอื่นร่วมด้วย',
                'ค่าเช่า วิชาชีพอิสระ รับเหมา หรือธุรกิจ',
                'แยกกรอกได้หลายแหล่งเงินได้ในครั้งเดียว',
            ],
        ],
        'pnd91' => [
            'code' => 'ภ.ง.ด.91',
            'sections' => '(มาตรา 40(1) เท่านั้น)',
            'summary' => 'สำหรับผู้มีเงินได้จากการจ้างแรงงาน',
            'tile' => 'bg-blue-50 text-blue-600',
            'points' => [
                'มีเฉพาะเงินเดือนหรือค่าจ้างจากการทำงาน',
                'ขั้นตอนสั้นกว่า กรอกเสร็จได้ในไม่กี่นาที',
                'เหมาะกับผู้เริ่มต้นทดลองคำนวณครั้งแรก',
            ],
        ],
    ];
    $card = $cards[$form];
@endphp
<article class="ui-card ui-card-interactive flex flex-col">
    <div class="flex items-start gap-4">
        <span class="ui-icon-tile-soft {{ $card['tile'] }}" aria-hidden="true"><x-icon name="document" class="size-7" /></span>
        <div>
            <{{ $heading }} class="text-2xl font-extrabold text-[#0f2c5c]">{{ $card['code'] }}</{{ $heading }}>
            <p class="mt-0.5 text-sm text-slate-500">{{ $card['sections'] }}</p>
        </div>
    </div>
    <p class="ui-body mt-4 font-medium text-slate-700">{{ $card['summary'] }}</p>
    <ul class="mt-3 grow space-y-2 text-sm leading-6 text-slate-600">
        @foreach ($card['points'] as $point)
            <li class="flex items-start gap-2.5">
                <span class="mt-1 flex size-4 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700" aria-hidden="true">
                    <x-icon name="check" class="size-2.5" />
                </span>
                <span>{{ $point }}</span>
            </li>
        @endforeach
    </ul>
    <a href="{{ route('simulator.form', $form) }}" class="ui-button-primary mt-6 w-full">
        เลือก {{ $card['code'] }} <x-icon name="arrow-right" class="size-4" />
    </a>
</article>
