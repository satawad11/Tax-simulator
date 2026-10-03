{{--
    Milestone 09.1 — form selection and the wizard, reconciled with panels 2–4 of the mockup.

    Two pages share this view because they share the URL: without a form code it is the chooser,
    with one it is the wizard.

    The chooser now uses the paired x-form-choice-card instead of two plain boxes, and the
    subtitle no longer describes the API — the reader is told what the page does, not how the
    software does it.

    The wizard gains the mockup's six-step progress indicator and the numbered section headings.
    The steps are the ones the mockup names, so what used to be three separate screens (tax year,
    taxpayer, family) is now one step of three numbered cards, and บริจาค and ภาษีหัก ณ ที่จ่าย
    are separate steps rather than one shared screen.
--}}
@php
    $steps = ['ปีภาษีและแบบ', 'ข้อมูลผู้มีเงินได้', 'ครอบครัว', 'เงินได้และค่าใช้จ่าย', 'ค่าลดหย่อน', 'เงินบริจาค', 'ภาษีที่ชำระไว้', 'ตรวจสอบและผล'];
@endphp
<x-layout :title="($formCode ? $formCode : 'เลือกแบบภาษี') . ' — ทดลองคำนวณภาษี'"
          description="ทดลองคำนวณภาษีบุคคลธรรมดาโดยไม่ต้องสมัครสมาชิก">
    <div data-simulator data-form-code="{{ $formCode }}">
        <nav class="ui-meta mb-6" aria-label="เส้นทางหน้า">
            <a href="{{ route('home') }}" class="hover:text-blue-700">หน้าแรก</a>
            <x-icon name="chevron-right" class="size-3" />
            @if ($formCode)
                <a href="{{ route('simulator.index') }}" class="hover:text-blue-700">ทดลองคำนวณภาษี</a>
                <x-icon name="chevron-right" class="size-3" />
                <span class="font-semibold text-slate-700">{{ $formCode }}</span>
            @else
                <span class="font-semibold text-slate-700">ทดลองคำนวณภาษี</span>
            @endif
        </nav>

        <div data-api-alert class="hidden ui-note ui-note-danger" role="alert" aria-live="polite"></div>

        {{-- ------------------------------------------------------ form selection --}}
        <section data-form-picker @class(['hidden' => $formCode])>
            <div class="mx-auto max-w-2xl text-center">
                <p class="ui-eyebrow">ขั้นตอนแรก</p>
                <h1 class="ui-section-title mt-2">เลือกแบบภาษีที่ต้องการทดลองคำนวณ</h1>
                <p class="ui-lede mt-3">เลือกตามประเภทเงินได้ที่คุณมีในปีภาษีนี้ เปลี่ยนแบบภายหลังได้เสมอ เพราะนี่เป็นการทดลอง ไม่ใช่การยื่นจริง</p>
            </div>

            <div class="mx-auto mt-8 grid grid-cols-1 max-w-4xl gap-5 md:grid-cols-2">
                <x-form-choice-card form="pnd90" heading="h2" />
                <x-form-choice-card form="pnd91" heading="h2" />
            </div>

            <div class="mx-auto mt-6 max-w-4xl rounded-2xl border border-blue-200 bg-blue-50 p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="ui-note-icon bg-blue-600" aria-hidden="true"><x-icon name="info" class="size-3.5" /></span>
                        <div>
                            <h2 class="font-bold text-blue-950">ยังไม่แน่ใจว่าควรใช้แบบไหน?</h2>
                            <p class="mt-1 text-sm leading-6 text-blue-900/80">อ่านคำอธิบายสั้น ๆ ว่าแต่ละแบบต่างกันอย่างไร แล้วค่อยเลือก</p>
                        </div>
                    </div>
                    <a href="{{ route('content.article', 'article-pnd90-vs-pnd91') }}" class="ui-button-primary">เริ่มตอบคำถาม</a>
                </div>
            </div>
        </section>

        @if ($formCode)
            {{-- ------------------------------------------------------------ wizard --}}
            <section data-wizard aria-labelledby="wizard-title">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="ui-eyebrow">{{ $formCode }}</p>
                        <h1 id="wizard-title" class="ui-section-title mt-1">กรอกข้อมูลเพื่อทดลองคำนวณ</h1>
                    </div>
                    <button type="button" data-reset class="ui-button-secondary">
                        <x-icon name="refresh" class="size-4" />เริ่มใหม่
                    </button>
                </div>

                <div class="ui-card mt-5">
                    <ol data-stepper class="ui-scroll-x" aria-label="ความคืบหน้าของขั้นตอน"
                        data-step-labels="{{ implode('|', $steps) }}"></ol>
                </div>

                <details class="ui-card mt-5" data-form-help>
                    <summary class="cursor-pointer font-bold text-blue-950">คำอธิบายการกรอกและตำแหน่งในแบบ</summary>
                    <div class="mt-4 grid grid-cols-1 gap-3 text-sm leading-7 text-slate-600 sm:grid-cols-2">
                        <p><strong class="text-slate-800">ยอดเงินได้:</strong> กรอกยอดทั้งปีก่อนหักค่าใช้จ่ายและค่าลดหย่อน โดยดูจากหนังสือรับรองหรือหลักฐานของผู้จ่ายเงินได้</p>
                        <p><strong class="text-slate-800">ยอดยกเว้น:</strong> กรอกเฉพาะส่วนที่ได้รับยกเว้นตามหลักฐาน อย่านำค่าใช้จ่ายหรือค่าลดหย่อนมากรอกซ้ำ</p>
                        <p><strong class="text-slate-800">ภ.ง.ด.91:</strong> ใช้ข้อ ก สำหรับเงินได้ตามมาตรา 40(1) เท่านั้น</p>
                        <p><strong class="text-slate-800">ภ.ง.ด.90:</strong> เลือกประเภทตามข้อ 1–7 ระบบจะแสดงประเภทย่อยและวิธีหักค่าใช้จ่ายที่เกี่ยวข้อง</p>
                    </div>
                </details>

                <form data-simulator-form novalidate class="mt-5 space-y-5">
                    {{-- ---------------------------------------- 1 ข้อมูลผู้เสียภาษี --}}
                    <section data-step="0" data-step-label="{{ $steps[0] }}" class="ui-card">
                            <div class="flex items-start gap-3">
                                <span class="ui-section-number">1</span>
                                <div>
                                    <h2 class="ui-form-title">ปีภาษีและแบบที่เลือก</h2>
                                    <p class="ui-help">ระบบใช้กฎภาษีของปีที่เลือกในการคำนวณทั้งหมด</p>
                                </div>
                            </div>
                            <div data-metadata-loading class="ui-loading mt-5">กำลังโหลดข้อมูล…</div>
                            <div data-metadata class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2"></div>
                    </section>

                    <section data-step="1" data-step-label="{{ $steps[1] }}" class="ui-card hidden">
                            <div class="flex items-start gap-3">
                                <span class="ui-section-number">2</span>
                                <div>
                                    <h2 class="ui-form-title">ข้อมูลผู้เสียภาษี</h2>
                                    <p class="ui-help">ใช้สำหรับหาเงื่อนไขค่าลดหย่อนส่วนบุคคล คุณไม่ต้องคำนวณเอง</p>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="ui-field">วันเดือนปีเกิด<span class="ui-field-note">ไม่บังคับ</span>
                                    <input name="birth_date" type="date" aria-describedby="birth-date-help">
                                    <span id="birth-date-help" class="ui-help">ใช้เก็บประกอบแบบร่างเท่านั้น รุ่นปัจจุบันไม่ใช้คำนวณสิทธิหรือยอดภาษี</span>
                                </label>
                                <label class="ui-field">สถานภาพ<span class="ui-field-required" aria-hidden="true">*</span>
                                    <select name="marital_status">
                                        <option value="single">โสด</option>
                                        <option value="married">สมรส</option>
                                        <option value="widowed">หม้าย</option>
                                        <option value="divorced">หย่า</option>
                                    </select>
                                </label>
                                <label class="ui-field sm:col-span-2">ชื่อแบบจำลอง<span class="ui-field-note">ไม่บังคับ</span>
                                    <input name="simulation_name" maxlength="120" placeholder="เช่น ภาษีปี 2568 ของฉัน">
                                </label>
                            </div>
                    </section>

                    <section data-step="2" data-step-label="{{ $steps[2] }}" class="ui-card hidden">
                            <div class="flex items-start gap-3">
                                <span class="ui-section-number">3</span>
                                <div>
                                    <h2 class="ui-form-title">คู่สมรสและผู้พึ่งพา</h2>
                                    <p class="ui-help">กรอกเฉพาะกรณีที่มี ระบบคำนวณรายการครอบครัวจากข้อเท็จจริงที่คุณยืนยัน โดยไม่ได้ตรวจเอกสารสิทธิแทนคุณ</p>
                                </div>
                            </div>
                            <label class="mt-5 flex items-center gap-3 text-sm font-semibold text-slate-700">
                                <input data-has-spouse type="checkbox"> มีคู่สมรส
                            </label>
                            <div data-spouse-fields hidden class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="ui-field">คู่สมรสมีเงินได้หรือไม่
                                    <select name="spouse_has_income">
                                        <option value="0">ไม่มี</option>
                                        <option value="1">มี</option>
                                    </select>
                                </label>
                                <label class="ui-field">สถานะการยื่น
                                    <select name="filing_status">
                                        <option value="separate">แยกยื่น</option>
                                        <option value="combined">รวมยื่น</option>
                                    </select>
                                </label>
                            </div>
                            <div class="mt-7 flex flex-wrap items-center justify-between gap-3">
                                    <div><h3 class="font-bold text-[#0f2c5c]">ผู้พึ่งพา</h3><p class="ui-help">อ้างอิงรายการบุตร บิดามารดา และผู้พิการ/ทุพพลภาพในใบแนบค่าลดหย่อน</p></div>
                                <button type="button" data-add-dependent class="ui-button-secondary">
                                    <x-icon name="plus" class="size-4" />เพิ่มผู้พึ่งพา
                                </button>
                            </div>
                            <div data-dependent-list class="mt-4 space-y-3"></div>
                    </section>

                    {{-- ------------------------------------------------- 2 รายได้ --}}
                    <section data-step="3" data-step-label="{{ $steps[3] }}" class="ui-card hidden">
                        <div class="flex items-start gap-3">
                            <div class="flex items-start gap-3">
                                <span class="ui-section-number">1</span>
                                <div>
                                    <h2 class="ui-form-title">เงินได้และค่าใช้จ่ายตามประเภท</h2>
                                    <p class="ui-help">เลือกมาตราและหัวข้อในแบบก่อน ระบบจะแสดงประเภทย่อย กิจกรรม จำนวนปีถือครอง หรือวิธีหักค่าใช้จ่ายเฉพาะรายการ</p>
                                </div>
                            </div>
                            <button type="button" data-add-income class="ui-button-secondary">
                                <x-icon name="plus" class="size-4" />เพิ่มรายได้
                            </button>
                        </div>
                        <div data-income-list class="mt-5 space-y-4"></div>
                        {{--
                            The API accepts at most 100 income lines. Without this the reader could
                            add a 101st row, fill it in, and only learn it was refused at the last
                            step — after the whole return was entered.
                        --}}
                        <div data-income-capacity class="hidden mt-4" role="status" aria-live="polite"></div>
                        <div class="ui-note ui-note-info mt-5">
                            <span class="ui-note-icon bg-blue-600" aria-hidden="true"><x-icon name="info" class="size-3.5" /></span>
                            <p><strong class="font-bold">คำแนะนำ:</strong> กรอกจำนวนเงินได้ทั้งปีก่อนหักค่าใช้จ่าย ระบบจะหักค่าใช้จ่ายตามประเภทเงินได้ให้เอง</p>
                        </div>
                        @if ($formCode === 'PND90')
                            <div class="ui-note ui-note-warning mt-4">
                                <span class="ui-note-icon bg-amber-600" aria-hidden="true">!</span>
                                <div>
                                    <p class="font-bold">รายการที่มีในแบบ ภ.ง.ด.90 แต่รุ่นปัจจุบันยังไม่รองรับ</p>
                                    <p>เครดิตภาษีเงินปันผลในข้อ 3, เงินได้จากการขายอสังหาริมทรัพย์ที่เลือกเสียภาษีแยกในข้อ 8 และเงินได้ที่เลือกไม่นำมารวมคำนวณในข้อ 10 ยังไม่มีช่องกรอกและไม่ถูกนำไปคำนวณ</p>
                                </div>
                            </div>
                        @endif
                    </section>

                    {{-- --------------------------------------------- 3 ค่าลดหย่อน --}}
                    <section data-step="4" data-step-label="{{ $steps[4] }}" class="ui-card hidden">
                        <div class="flex items-start gap-3">
                            <span class="ui-section-number">1</span>
                            <div>
                                <h2 class="ui-form-title">ค่าลดหย่อน</h2>
                                <p class="ui-help">กรอกเฉพาะรายการที่คุณมีหลักฐานการจ่ายจริง เว้นว่างไว้ได้หากไม่มี</p>
                            </div>
                        </div>
                        <div class="ui-note ui-note-info mt-5">
                            <span class="ui-note-icon bg-blue-600" aria-hidden="true"><x-icon name="info" class="size-3.5" /></span>
                            <p><strong class="font-bold">ค่าลดหย่อนส่วนบุคคล ครอบครัว และบุตร</strong> ระบบคำนวณให้อัตโนมัติจากข้อมูลในขั้นตอนแรก
                                จึงไม่มีช่องให้กรอก รายการที่ยังไม่รองรับจะถูกปิดไว้พร้อมเหตุผล</p>
                        </div>
                        <div data-allowance-list class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2"></div>
                        {{--
                            เงินได้ที่ได้รับยกเว้น that ใบแนบ takes after expenses — ข้อ 13 and ข้อ 20.
                            Kept visually separate from the allowance grid above because they are a
                            different stage of the calculation, not a different kind of allowance.
                        --}}
                        <div data-income-exemption-list class="mt-6"></div>
                    </section>

                    {{-- --------------------------------------------- 4 เงินบริจาค --}}
                    <section data-step="5" data-step-label="{{ $steps[5] }}" class="ui-card hidden">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="ui-section-number">1</span>
                                <div>
                                    <h2 class="ui-form-title">เงินบริจาค</h2>
                                <p class="ui-help">แยกเงินบริจาคกรณีพิเศษและเงินบริจาคทั่วไปตามลำดับในแบบ ระบบใช้เพดานจาก backend</p>
                                </div>
                            </div>
                        </div>
                        <div data-donation-list class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2"></div>
                    </section>

                    {{-- ------------------------------------ 5 ภาษีหัก ณ ที่จ่าย --}}
                    <section data-step="6" data-step-label="{{ $steps[6] }}" class="ui-card hidden">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="ui-section-number">1</span>
                                <div>
                                    <h2 class="ui-form-title">ภาษีที่ถูกหักไว้แล้ว</h2>
                                    <p class="ui-help">กรอกภาษีหัก ณ ที่จ่าย หรือภาษีที่ชำระตาม ภ.ง.ด.93/94 จากหลักฐาน รายการเครดิตที่ยังไม่รองรับจะแสดงแต่เลือกไม่ได้</p>
                                </div>
                            </div>
                            <button type="button" data-add-withholding class="ui-button-secondary">
                                <x-icon name="plus" class="size-4" />เพิ่มรายการ
                            </button>
                        </div>
                        <div data-withholding-list class="mt-5 space-y-3"></div>
                    </section>

                    {{-- ------------------------------------------------ 6 สรุปผล --}}
                    <div data-step="7" data-step-label="{{ $steps[7] }}" class="hidden space-y-5">
                        <section data-review-card class="ui-card">
                            <div class="flex items-start gap-3">
                                <span class="ui-section-number">1</span>
                                <div>
                                    <h2 class="ui-form-title">ตรวจสอบข้อมูลก่อนคำนวณ</h2>
                                    <p class="ui-help">ตรวจตามลำดับของแบบ: เงินได้และค่าใช้จ่าย → ค่าลดหย่อน → เงินบริจาค → ภาษีที่ชำระไว้ แล้วกดคำนวณ</p>
                                </div>
                            </div>
                            <div data-review class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2"></div>
                            <div data-integrity-review class="mt-4 hidden"></div>
                        </section>
                        <div data-result></div>
                        <div data-planning class="hidden"></div>
                    </div>

                    <div data-validation-summary class="hidden ui-note ui-note-danger" role="alert" aria-live="assertive"></div>

                    <div data-wizard-actions class="flex flex-wrap items-center justify-between gap-3">
                        <button type="button" data-back class="ui-button-secondary hidden">
                            <x-icon name="arrow-left" class="size-4" />ย้อนกลับ
                        </button>
                        <div class="ml-auto flex flex-wrap gap-3">
                            <button type="button" data-save-draft class="ui-button-secondary hidden">บันทึกแบบร่าง</button>
                            <button type="button" data-next class="ui-button-primary">ถัดไป <x-icon name="arrow-right" class="size-4" /></button>
                            <button type="submit" data-calculate class="ui-button-primary hidden">คำนวณภาษี</button>
                        </div>
                    </div>
                </form>
            </section>
        @endif
    </div>
</x-layout>
