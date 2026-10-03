import { icon } from './icons.js';
import { currency, escapeHtml } from './ui.js';

/**
 * Milestone 09.1 — the result and planning views, reconciled with panel 4 of the approved mockup.
 *
 * Before M9.1 the result read as one more input card: a tinted box, a flat list of rows, and two
 * collapsed disclosures. The mockup makes the result the most finished screen in the product — a
 * completion banner, a two-column body with the summary on the left and the bracket breakdown on
 * the right, then the cards that explain the outcome, then the actions.
 *
 * Three rules held throughout:
 *
 *   Nothing is calculated here. Every number rendered below is a field of the API response; this
 *   module formats and arranges, and computes no total, difference or saving of its own.
 *
 *   Status is never carried by colour alone. PAYABLE, REFUND and ZERO each get an icon and a
 *   Thai word, and the four card kinds (information, warning, action, result) are distinguished
 *   by their icon and heading as well as their tint.
 *
 *   Every string that came from the API is escaped before it reaches the DOM.
 */


/**
 * The three outcomes, each with the word that names it, the icon that shows it, and the tint the
 * final summary row and the banner amount take.
 */
const OUTCOME = {
    PAYABLE: { word: 'ภาษีที่ต้องชำระเพิ่ม', glyph: 'arrow-up', tone: 'text-rose-700', band: '', seal: 'bg-rose-600' },
    REFUND: { word: 'ประมาณการเงินคืนภาษี', glyph: 'arrow-down', tone: 'text-emerald-700', band: 'ui-summary-row-final-refund', seal: 'bg-emerald-600' },
    ZERO: { word: 'ไม่มีภาษีที่ต้องชำระเพิ่ม', glyph: 'equals', tone: 'text-blue-800', band: 'ui-summary-row-final-zero', seal: 'bg-blue-600' },
};

const row = (label, value, variant = '') =>
    `<div class="ui-summary-row ${variant}"><span class="ui-summary-label">${escapeHtml(label)}</span>
        <span class="ui-summary-value">${currency(value)}</span></div>`;

/** One of the four card kinds. The heading and icon say which; the tint only reinforces it. */
const note = (kind, heading, body) => {
    const kinds = {
        information: ['ui-note-info', 'bg-blue-600', 'glyph-i'],
        warning: ['ui-note-warning', 'bg-amber-600', 'glyph-exclamation'],
        action: ['ui-note-info', 'bg-violet-600', 'lightbulb'],
        result: ['ui-note-success', 'bg-emerald-600', 'check'],
    };
    const [tone, seal, glyph] = kinds[kind] || kinds.information;
    return `<div class="ui-note ${tone}"><span class="ui-note-icon ${seal}">${icon(glyph, 'size-3')}</span>
        <div><p class="font-bold">${escapeHtml(heading)}</p>${body}</div></div>`;
};

export function renderResult(result) {
    const status = result.result?.status || 'ZERO';
    const outcome = OUTCOME[status] || OUTCOME.ZERO;

    const brackets = (result.progressive_tax?.brackets || []).map((item) => `
        <tr><td>${currency(item.min_amount)} – ${item.max_amount ? currency(item.max_amount) : 'ขึ้นไป'}</td>
            <td>${escapeHtml(String(item.rate))}%</td>
            <td>${currency(item.taxable_amount)}</td>
            <td class="text-right font-semibold">${currency(item.tax)}</td></tr>`).join('');

    const trace = (result.trace || []).map((item) => `
        <li class="border-l-2 border-blue-200 pl-4">
            <p class="text-sm font-semibold text-[#0f2c5c]">${escapeHtml(item.label || item.code)}</p>
            <p class="mt-0.5 text-sm leading-6 text-slate-600">${escapeHtml(item.description || '')}</p>
        </li>`).join('');

    const warnings = (result.warnings || []).map((warning) =>
        `<li><strong class="font-semibold">${escapeHtml(warning.code)}</strong> — ${escapeHtml(warning.message)}</li>`).join('');

    const recommendations = (result.recommendations || []).map((item) => {
        const action = item.action ? [item.action.type, item.action.allowance_code].filter(Boolean).join(' · ') : '';
        return `<article class="ui-card">
            <div class="flex items-center gap-2">
                <span class="ui-note-icon bg-violet-600">${icon('lightbulb', 'size-3')}</span>
                <p class="text-xs font-bold tracking-wide text-violet-700 uppercase">${escapeHtml(item.priority || 'คำแนะนำ')}</p>
            </div>
            <h4 class="mt-2 font-bold text-[#0f2c5c]">${escapeHtml(item.title)}</h4>
            <p class="ui-body mt-1.5">${escapeHtml(item.message)}</p>
            ${action ? `<p class="mt-3 text-sm font-semibold text-blue-700">${escapeHtml(action)}</p>` : ''}
        </article>`;
    }).join('');

    const guidance = result.refund_guidance || result.payment_guidance;
    const guidanceHtml = guidance ? note(status === 'REFUND' ? 'result' : 'action',
        status === 'REFUND' ? 'แนวทางประมาณการเงินคืน' : 'แนวทางยอดที่ต้องชำระเพิ่ม',
        `<p class="mt-1">${escapeHtml(guidance.reason || guidance.message || '')}</p>
         ${guidance.checklist ? `<ul class="mt-3 list-disc space-y-1.5 pl-5">${guidance.checklist
            .map((item) => `<li>${escapeHtml(item.label || item.code || item)}</li>`).join('')}</ul>` : ''}
         ${guidance.disclaimer ? `<p class="mt-3 text-xs opacity-80">${escapeHtml(guidance.disclaimer)}</p>` : ''}`) : '';

    return `<section aria-labelledby="result-title" class="space-y-5">
    <div class="ui-result-banner border-emerald-200 bg-emerald-50">
        <span class="ui-result-seal bg-emerald-600">${icon('check', 'size-7')}</span>
        <p class="text-lg font-bold text-emerald-800">ผลการคำนวณเสร็จสิ้น</p>
        <p class="text-sm text-slate-600">ระบบคำนวณจากข้อมูลที่คุณกรอกตามกฎภาษีที่เผยแพร่อยู่</p>
        <p class="ui-meta mt-1 justify-center">
            <span class="ui-status ${outcome.seal} text-white">${icon(outcome.glyph, 'size-3')}${escapeHtml(outcome.word)}</span>
        </p>
        <p id="result-title" class="text-4xl font-extrabold tabular-nums ${outcome.tone}">${currency(result.result?.amount)}</p>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <article class="ui-card">
            <h3 class="ui-form-title">สรุปผลการคำนวณ</h3>
            ${row('เงินได้รวมทั้งปี', result.income?.gross_income)}
            ${row('หัก เงินได้ที่ได้รับยกเว้น', result.income?.exempt_income)}
            ${row('หัก ค่าใช้จ่าย', result.expenses?.total)}
            ${row('เงินได้หลังหักค่าใช้จ่าย', result.income_after_expense, 'ui-summary-row-subtotal')}
            ${Number(result.income_exemptions?.total || 0) > 0
                // ใบแนบ ข้อ 13 และ ข้อ 20 — a stage of its own after expenses. Shown only when
                // something was claimed, so an ordinary return keeps the summary it always had,
                // and never folded into the two neighbouring lines it is not part of.
                ? row('หัก เงินได้ที่ได้รับยกเว้นหลังหักค่าใช้จ่าย', result.income_exemptions.total)
                    + row('คงเหลือหลังหักเงินได้ที่ได้รับยกเว้น', result.income_after_income_exemptions, 'ui-summary-row-subtotal')
                : ''}
            ${row('หัก ค่าลดหย่อน', result.allowances?.total_eligible)}
            ${row('หัก เงินบริจาค', result.donations?.total_eligible)}
            ${row('เงินได้สุทธิ', result.net_income, 'ui-summary-row-subtotal')}
            ${row('ภาษีตามอัตราขั้นบันได', result.progressive_tax?.total)}
            ${result.form_code === 'PND90' && result.minimum_tax
                // The minimum-tax line is printed only on ภ.ง.ด.90. Showing it on a ภ.ง.ด.91
                // result would name a rule that form does not carry, so the label is guarded by
                // the form code rather than by the presence of the field alone.
                ? row('ภาษีขั้นต่ำ ภ.ง.ด.90', result.minimum_tax.tax ?? result.minimum_tax.amount) : ''}
            ${row('หัก ภาษีที่ถูกหักและเครดิตภาษี', result.credits?.total)}
            <div class="ui-summary-row ui-summary-row-final ${outcome.band}">
                <span class="flex min-w-0 flex-1 items-center gap-2">${icon(outcome.glyph, 'size-4 shrink-0')}${escapeHtml(outcome.word)}</span>
                <span class="shrink-0 tabular-nums whitespace-nowrap">${currency(result.result?.amount)}</span>
            </div>
        </article>

        <article class="ui-card">
            <h3 class="ui-form-title">รายละเอียดการคำนวณภาษี (แบบขั้นบันได)</h3>
            ${brackets ? `<div class="ui-scroll-x mt-4"><table class="ui-table">
                <thead><tr><th>ช่วงเงินได้สุทธิ</th><th>อัตรา</th><th>เงินได้ในขั้น</th><th class="text-right">ภาษี</th></tr></thead>
                <tbody>${brackets}</tbody></table></div>`
                : '<p class="ui-body mt-3">ไม่มีขั้นภาษีที่ต้องแสดงสำหรับผลนี้</p>'}
            <details class="mt-5 rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-sm font-semibold text-blue-700">ดูวิธีคำนวณทีละขั้น</summary>
                <ol class="mt-4 space-y-4">${trace || '<li class="text-sm text-slate-500">ไม่มีรายละเอียดเพิ่มเติม</li>'}</ol>
            </details>
        </article>
    </div>

    ${warnings ? note('warning', 'คำเตือนเกี่ยวกับผลนี้', `<ul class="mt-1 space-y-1.5">${warnings}</ul>`) : ''}
    ${guidanceHtml}
    ${recommendations ? `<div><h3 class="ui-form-title">คำแนะนำ</h3>
        <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">${recommendations}</div></div>` : ''}

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <button type="button" data-edit-inputs class="ui-button-secondary">${icon('pencil', 'size-4')}แก้ไขข้อมูล</button>
        <div class="flex flex-wrap gap-3">
            <button type="button" data-print-result class="ui-button-secondary">${icon('printer', 'size-4')}พิมพ์ / บันทึกเป็น PDF</button>
            <button type="button" data-open-planning class="ui-button-primary">${icon('chart', 'size-4')}ทดลองวางแผนภาษี</button>
            <button type="button" data-save-member class="ui-button-primary">${icon('save', 'size-4')}บันทึกผลนี้</button>
        </div>
    </div>

    ${note('information', 'ข้อควรทราบ',
        '<p class="mt-1">ผลลัพธ์เป็นการประมาณการจากข้อมูลที่กรอกในระบบจำลอง ไม่ใช่การยื่นแบบภาษีจริงหรือการรับรองผลโดยหน่วยงานราชการ</p>')}
    </section>`;
}

export function renderPlanning(plan) {
    const warnings = (plan.warnings || []).map((warning) =>
        `<li><strong class="font-semibold">${escapeHtml(warning.code)}</strong> — ${escapeHtml(warning.message)}</li>`).join('');
    const recommendations = (plan.recommendations || []).map((item) =>
        `<li><strong class="font-semibold">${escapeHtml(item.title)}</strong> — ${escapeHtml(item.message)}</li>`).join('');

    // Each figure below is a field of the planning response. The browser subtracts nothing.
    const metrics = [
        ['ก่อนปรับ', plan.before?.result?.amount],
        ['หลังปรับ', plan.after?.result?.amount],
        ['ส่วนต่าง', plan.difference?.result_amount],
        ['ประมาณการภาษีที่ลดลง', plan.estimated_tax_saving],
    ];

    return `<section class="ui-card" aria-labelledby="planning-title">
        <h3 id="planning-title" class="ui-form-title">เปรียบเทียบผลก่อนและหลังปรับ</h3>
        <p class="ui-help">ตัวเลขทั้งหมดคำนวณโดยระบบ และไม่กระทบข้อมูลต้นฉบับที่บันทึกไว้</p>
        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            ${metrics.map(([label, value]) => `<div class="ui-metric">
                <p class="ui-metric-label">${escapeHtml(label)}</p>
                <p class="ui-metric-value">${currency(value)}</p></div>`).join('')}
        </div>
        ${warnings ? `<div class="mt-5">${note('warning', 'คำเตือน', `<ul class="mt-1 space-y-1.5">${warnings}</ul>`)}</div>` : ''}
        ${recommendations ? `<div class="mt-5">${note('action', 'คำแนะนำจากการวางแผน', `<ul class="mt-1 space-y-1.5">${recommendations}</ul>`)}</div>` : ''}
    </section>`;
}
