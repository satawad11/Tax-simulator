{{--
    Milestone 09.1 — the administrative audit trail.

    Every change an administrator makes to content, tax sources and rule versions has been
    recorded since M8, but the console surfaced only the last handful on the overview. That is
    enough to notice that something happened and not enough to answer a question about it.

    The log is read-only by design: it is evidence, and an interface that could edit it would
    make it worthless. It also never contains a credential, a token or a member's tax payload —
    only administrative entities.
--}}
@php
    $actions = [
        'CONTENT_CREATED', 'CONTENT_UPDATED', 'CONTENT_PUBLISHED', 'CONTENT_UNPUBLISHED',
        'CONTENT_ARCHIVED', 'CONTENT_DELETED',
        'RULE_VERSION_CREATED', 'RULE_VERSION_CLONED', 'RULE_VERSION_VALIDATED',
        'RULE_VERSION_PUBLISHED', 'RULE_VERSION_ARCHIVED',
        'TAX_RULE_CREATED', 'TAX_RULE_UPDATED', 'TAX_RULE_DELETED',
        'TAX_SOURCE_CREATED', 'TAX_SOURCE_UPDATED', 'TAX_SOURCE_ARCHIVED',
    ];
    $entities = ['content_post', 'content_category', 'content_tag', 'tax_rule_version',
        'tax_bracket', 'expense_rule', 'allowance_rule', 'allowance_cap_group',
        'donation_rule', 'recommendation_rule', 'tax_source'];
@endphp
<x-admin-layout title="บันทึกการตรวจสอบ">
    <section data-admin-page="audit-logs" aria-busy="true">
        <h1 class="ui-section-title">บันทึกการตรวจสอบ</h1>
        <p class="ui-body mt-2 max-w-3xl">
            ประวัติการเปลี่ยนแปลงทั้งหมดที่ผู้ดูแลระบบทำกับเนื้อหา เอกสารอ้างอิง และชุดกฎภาษี
            บันทึกนี้อ่านอย่างเดียวและแก้ไขไม่ได้ ไม่มีการเก็บรหัสผ่าน โทเคน หรือข้อมูลภาษีของสมาชิก
        </p>

        {{--
            Action and entity answer "what kind of thing happened". The questions this record
            exists for — what did this administrator change, and what happened around the time
            that rule was published — need the actor and the dates, which is why they are here.
            `actor_user_id` is filled from the query string when the accounts page links in.
        --}}
        <form data-audit-filter class="ui-card mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_auto_auto] lg:items-end">
            <input type="hidden" name="actor_user_id" value="{{ request('actor_user_id') }}">
            <label class="ui-field">การกระทำ<span class="ui-field-note">ไม่บังคับ</span>
                <select name="action">
                    <option value="">ทั้งหมด</option>
                    @foreach ($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>@endforeach
                </select>
            </label>
            <label class="ui-field">ประเภทข้อมูล<span class="ui-field-note">ไม่บังคับ</span>
                <select name="entity_type">
                    <option value="">ทั้งหมด</option>
                    @foreach ($entities as $entity)<option value="{{ $entity }}" @selected(request('entity_type') === $entity)>{{ $entity }}</option>@endforeach
                </select>
            </label>
            <label class="ui-field">ตั้งแต่วันที่<span class="ui-field-note">ไม่บังคับ</span>
                <input name="from" type="date">
            </label>
            <label class="ui-field">ถึงวันที่<span class="ui-field-note">ไม่บังคับ</span>
                <input name="to" type="date">
            </label>
            <button type="submit" class="ui-button-primary">กรอง</button>
        </form>

        {{-- Shown only when the page was opened from a specific account's row. --}}
        <p data-audit-actor class="ui-note ui-note-info mt-4 hidden" role="status">
            <span class="ui-note-icon bg-blue-600">i</span>
            <span>
                กำลังแสดงเฉพาะรายการที่ทำโดยบัญชี <strong data-audit-actor-name>—</strong>
                <a href="{{ route('admin.audit-logs') }}" class="font-semibold text-blue-700 hover:underline">แสดงทั้งหมด</a>
            </span>
        </p>

        <div data-admin-loading class="ui-loading mt-6" role="status">กำลังโหลดบันทึก…</div>

        <div data-admin-content class="hidden">
            <p data-audit-summary class="mt-6 text-sm text-slate-500"></p>

            <div class="ui-card mt-3 p-0 sm:p-0">
                <div class="ui-scroll-x">
                    <table class="ui-table min-w-[60rem]">
                        <thead>
                            <tr>
                                <th scope="col">เวลา</th>
                                <th scope="col">การกระทำ</th>
                                <th scope="col">ประเภทข้อมูล</th>
                                <th scope="col">รายละเอียด</th>
                                <th scope="col">ผู้ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody data-audit-rows></tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <button type="button" data-audit-prev class="ui-button-secondary">ก่อนหน้า</button>
                <button type="button" data-audit-next class="ui-button-secondary">ถัดไป</button>
            </div>
        </div>

        <p data-admin-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
    </section>
</x-admin-layout>
