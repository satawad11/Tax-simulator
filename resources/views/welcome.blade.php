{{--
    Milestone 09.1 — the home page, reconciled with panel 1 of the approved mockup.

    The mockup's home page is three bands: a tinted hero with a three-line headline, two CTAs and
    an illustration card carrying green-check bullets; a row of four feature cards with solid
    coloured icon tiles; and a "บทความแนะนำ" strip with a "ดูทั้งหมด" link. Before M9.1 the page
    had the headline but none of the structure — no feature row, no article strip, an oversized
    badge, and a large empty column where the illustration belongs.
--}}
<x-layout title="ภาษีได้ง่าย — ทดลองคำนวณภาษีบุคคลธรรมดา"
          description="ทดลองคำนวณภาษี ภ.ง.ด.90 และ ภ.ง.ด.91 ด้วยตัวเอง โดยไม่ต้องสมัครสมาชิก" :wide="true">

    {{-- ------------------------------------------------------------------ hero --}}
    <section class="border-b border-slate-200 bg-gradient-to-b from-blue-50 via-blue-50/60 to-slate-50">
        <div class="ui-shell grid grid-cols-1 items-center gap-10 py-12 lg:grid-cols-[1.1fr_.9fr] lg:py-16">
            <div>
                <p class="ui-badge"><x-icon name="sparkle" class="size-3.5" />เครื่องมือจำลองภาษีสำหรับบุคคลธรรมดา</p>
                <h1 class="ui-display mt-4">ภาษีไม่ใช่เรื่องยาก<br>ทดลองคำนวณภาษี<br>ได้ด้วยตัวเอง</h1>
                <p class="ui-lede mt-5 max-w-xl">
                    เรียนรู้ เข้าใจ และวางแผนภาษี ภ.ง.ด.90 และ ภ.ง.ด.91 จากข้อมูลของคุณเอง
                    ทดลองได้ทันทีโดยไม่ต้องสมัครสมาชิก
                </p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a class="ui-button-primary ui-button-lg" href="{{ route('simulator.index') }}">เริ่มทดลองเลย</a>
                    <a class="ui-button-secondary ui-button-lg" href="{{ route('content.knowledge') }}">ดูความรู้ภาษี</a>
                </div>
            </div>

            {{-- The mockup's illustration panel, drawn as a composed card rather than a bitmap. --}}
            <div class="relative">
                <div class="ui-panel bg-white/80">
                    <div class="flex items-center gap-4">
                        <span class="ui-icon-tile-soft bg-blue-600 text-white" aria-hidden="true"><x-icon name="calculator" class="size-8" /></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-500">ปีภาษี 2568</p>
                            <p class="ui-form-title">วางแผนวันนี้ เพื่ออนาคตที่ดีกว่า</p>
                        </div>
                    </div>
                    <ul class="mt-6 space-y-3 text-sm text-slate-700">
                        @foreach (['ฟรี ไม่มีค่าใช้จ่าย', 'ไม่ต้องสมัครสมาชิกเพื่อทดลอง', 'อ้างอิงกฎจากแหล่งข้อมูลที่อนุมัติ', 'มีคำอธิบายผลและคำเตือนทุกครั้ง'] as $point)
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
                                    <x-icon name="check" class="size-3.5" />
                                </span>
                                <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <a href="{{ route('simulator.form', 'pnd90') }}" class="ui-chip justify-center">ภ.ง.ด.90</a>
                        <a href="{{ route('simulator.form', 'pnd91') }}" class="ui-chip justify-center">ภ.ง.ด.91</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- --------------------------------------------------------- feature cards --}}
    <section class="ui-shell -mt-6 lg:-mt-8">
        <h2 class="sr-only">สิ่งที่ระบบนี้ช่วยคุณได้</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $features = [
                    ['icon' => 'calculator', 'tile' => 'bg-blue-600', 'title' => 'ทดลองคำนวณภาษี', 'text' => 'คำนวณได้ทันที ไม่ต้องสมัครสมาชิก', 'href' => route('simulator.index')],
                    ['icon' => 'book', 'tile' => 'bg-violet-600', 'title' => 'ความรู้ภาษี', 'text' => 'เนื้อหาเข้าใจง่าย อ่านก่อนเริ่มกรอก', 'href' => route('content.knowledge')],
                    ['icon' => 'news', 'tile' => 'bg-cyan-600', 'title' => 'ข่าวสารล่าสุด', 'text' => 'ความเคลื่อนไหวที่เกี่ยวข้องกับระบบ', 'href' => route('content.news')],
                    ['icon' => 'bookmark', 'tile' => 'bg-amber-500', 'title' => 'บันทึกข้อมูลได้', 'text' => 'เมื่อสมัครสมาชิก กลับมาทำต่อได้', 'href' => route('register')],
                ];
            @endphp
            @foreach ($features as $feature)
                <a href="{{ $feature['href'] }}" class="ui-card ui-card-interactive block">
                    <span class="ui-icon-tile {{ $feature['tile'] }}" aria-hidden="true"><x-icon :name="$feature['icon']" class="size-6" /></span>
                    <h3 class="mt-4 font-bold text-[#0f2c5c]">{{ $feature['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-6 text-slate-500">{{ $feature['text'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ------------------------------------------------------- form comparison --}}
    <section class="ui-shell mt-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="ui-eyebrow">เลือกแบบภาษี</p>
                <h2 class="ui-section-title mt-1">เริ่มจากประเภทเงินได้ของคุณ</h2>
            </div>
            <a class="ui-button-quiet" href="{{ route('simulator.index') }}">ช่วยฉันเลือก <x-icon name="chevron-right" class="size-4" /></a>
        </div>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-form-choice-card form="pnd90" />
            <x-form-choice-card form="pnd91" />
        </div>
    </section>

    {{-- --------------------------------------------------------- featured posts --}}
    <section class="ui-shell mt-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="ui-section-title">บทความแนะนำ</h2>
            <a class="ui-button-quiet" href="{{ route('content.knowledge') }}">ดูทั้งหมด <x-icon name="chevron-right" class="size-4" /></a>
        </div>
        @if ($featured->isEmpty())
            <x-empty-state class="mt-5" icon="book" title="ยังไม่มีบทความเผยแพร่"
                           message="เมื่อทีมงานเผยแพร่บทความหรือคู่มือ รายการจะปรากฏที่นี่ ระหว่างนี้คุณเริ่มทดลองคำนวณได้เลย"
                           actionLabel="เริ่มทดลองคำนวณ" :actionHref="route('simulator.index')" />
        @else
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach ($featured as $post)
                    <x-article-card :post="$post" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- ----------------------------------------------------------- latest news --}}
    @if ($latestNews->isNotEmpty())
        <section class="ui-shell mt-12">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="ui-section-title">ข่าวสารล่าสุด</h2>
                <a class="ui-button-quiet" href="{{ route('content.news') }}">ดูทั้งหมด <x-icon name="chevron-right" class="size-4" /></a>
            </div>
            <ul class="mt-5 grid gap-4">
                @foreach ($latestNews as $post)
                    <x-article-card :post="$post" layout="row" />
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ------------------------------------------------------------ disclaimer --}}
    <section class="ui-shell mt-12">
        <div class="ui-note ui-note-info">
            <span class="ui-note-icon bg-blue-600" aria-hidden="true"><x-icon name="info" class="size-3.5" /></span>
            <p><strong class="font-bold">ข้อควรทราบ:</strong>
                ผลลัพธ์เป็นการประมาณการจากข้อมูลที่กรอกในระบบจำลอง ไม่ใช่การยื่นแบบภาษีจริงหรือการรับรองผลโดยหน่วยงานราชการ</p>
        </div>
    </section>
</x-layout>
