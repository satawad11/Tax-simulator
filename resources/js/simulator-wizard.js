import { api, authToken, errorText, fieldNameFromPath } from './api.js';
import { requireMember } from './auth.js';
import { persistExistingState, persistGuestState } from './member-return.js';
import { clearState, loadState, saveState, setGuestResult } from './simulator-state.js';
import { renderPlanning, renderResult } from './tax-result.js';
import { confirmAction, escapeHtml, hideAlert, promptValue, showAlert } from './ui.js';

/**
 * Milestone 09.1 — the wizard, reconciled with panel 3 of the approved mockup.
 *
 * The visible structure follows the approved mockup while giving each source-form topic a clear
 * step. The step list is read from the DOM, so the markup stays the single description of what
 * the wizard contains.
 *
 * Nothing about the calculation changed. Every amount still comes from the backend; this module
 * collects input, posts it, and renders what comes back.
 */

const money = (value) => String(value || '0').replaceAll(',', '');
const derivedAllowanceCodes = new Set(['PERSONAL', 'SPOUSE', 'CHILD', 'PARENT', 'DISABLED_PERSON']);
const allowanceGroups = {
    insurance: 'ประกันชีวิตและสุขภาพ', provident_fund: 'เงินออมและกองทุน', nsf: 'เงินออมและกองทุน',
    rmf: 'เงินออมและกองทุน', thai_esg: 'เงินออมและกองทุน', thai_esgx: 'เงินออมและกองทุน',
    home_loan_interest: 'ที่อยู่อาศัย', maternity: 'ครอบครัวและสุขภาพ', annual_tax_measures: 'มาตรการภาษีประจำปี',
    political_party: 'เงินสนับสนุนพรรคการเมือง', social_security: 'ประกันสังคม', other: 'รายการอื่น',
};
const withholdingLabels = {
    withholding: 'ภาษีหัก ณ ที่จ่าย',
    pnd93: 'ภาษีชำระตาม ภ.ง.ด.93',
    pnd94: 'ภาษีชำระตาม ภ.ง.ด.94',
};
const uniquePrepaymentTypes = new Set(['pnd93', 'pnd94']);
const donationTypes = [
    ['SPECIAL_DONATION', 'เงินบริจาคที่หักได้เป็นกรณีพิเศษ'],
    ['GENERAL_DONATION', 'เงินบริจาคทั่วไป'],
];

const amountText = (value) => Number(value || 0).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** A repeatable-row shell: the numbered heading, the remove control and the fields. */
/**
 * The most income lines one calculation accepts, mirroring `incomes` => `max:100` in
 * CalculateTaxRequest. The server stays the authority; this only stops the reader entering a row
 * that would be refused, and says why at the moment it becomes true rather than at submit.
 */
const MAX_INCOME_ROWS = 100;

const rowShell = (attribute, id, title, fields) => `
    <div data-${attribute} data-id="${id || ''}" class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-sm font-bold text-[#0f2c5c]">${escapeHtml(title)}</p>
            <button type="button" data-remove-row class="ui-button-danger-quiet">ลบรายการ</button>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">${fields}</div>
    </div>`;

export async function initializeSimulator() {
    const root = document.querySelector('[data-simulator]');
    const formCode = root?.dataset.formCode;
    if (!root || !formCode) return;

    const stepper = root.querySelector('[data-stepper]');
    const steps = (stepper?.dataset.stepLabels || '').split('|').filter(Boolean);
    const last = steps.length - 1;

    let state = loadState(formCode);
    let step = 0;
    let metadata = { years: [], incomes: [], allowances: [], incomeExemptions: [] };
    const form = root.querySelector('[data-simulator-form]');
    const alert = root.querySelector('[data-api-alert]');
    const validation = root.querySelector('[data-validation-summary]');
    const reviewCard = root.querySelector('[data-review-card]');

    const focusFirstError = (error) => {
        const path = Object.keys(error.errors || {})[0];
        if (!path) return;
        const name = fieldNameFromPath(path);
        const index = Number(path.match(/\.(\d+)\./)?.[1] || 0);
        const candidates = [...root.querySelectorAll(`[name="${CSS.escape(name)}"]`)];
        const input = candidates[index] || candidates[0];
        input?.focus();
        input?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const isStepComplete = (index) => {
        if (index === 3) return state.incomes.length > 0
            && state.incomes.every((item) => item.income_type && Number(item.gross_amount) >= 0)
            && !metadata.incomes.some((item) => item.metadata_error);
        return true;
    };

    const renderStepper = () => {
        stepper.innerHTML = `<div class="ui-stepper">${steps.map((label, index) => {
            const status = index === step ? 'current' : index < step && isStepComplete(index) ? 'done' : index < step ? 'attention' : 'todo';
            const line = index === 0 ? '' :
                `<span class="ui-step-line ${index <= step ? 'ui-step-line-done' : ''} right-1/2" aria-hidden="true"></span>`;
            return `<li class="ui-step">${line}
                <span class="ui-step-bullet ui-step-bullet-${status}">${status === 'done' ? '✓' : status === 'attention' ? '!' : index + 1}</span>
                <span class="ui-step-label ${status === 'current' ? 'ui-step-label-current' : ''}">${escapeHtml(label)}</span>
                <span class="sr-only">${status === 'current' ? 'ขั้นตอนปัจจุบัน' : status === 'done' ? 'ข้อมูลครบ' : status === 'attention' ? 'ข้อมูลยังไม่ครบ' : 'ยังไม่ถึง'}</span>
            </li>`;
        }).join('')}</div>`;
    };

    const showStep = (next) => {
        step = Math.max(0, Math.min(last, next));
        root.querySelectorAll('[data-step]').forEach((section) => section.classList.toggle('hidden', Number(section.dataset.step) !== step));
        root.querySelector('[data-back]').classList.toggle('hidden', step === 0);
        root.querySelector('[data-next]').classList.toggle('hidden', step >= last);
        root.querySelector('[data-calculate]').classList.toggle('hidden', step !== last);
        if (step === last) renderReview();
        renderStepper();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const collect = () => {
        const data = new FormData(form);
        state.profile = { birth_date: data.get('birth_date') || undefined, marital_status: data.get('marital_status') || 'single' };
        state.simulation_name = data.get('simulation_name') || '';
        state.filing_status = data.get('filing_status') || null;
        state.spouse = root.querySelector('[data-has-spouse]').checked ? { has_income: data.get('spouse_has_income') === '1' } : null;
        state.dependents = [...root.querySelectorAll('[data-dependent]')].map((row) => ({ ...(row.dataset.id ? { id: Number(row.dataset.id) } : {}), relation_type: row.querySelector('[name="relation_type"]')?.value, disabled_person_relationship: row.querySelector('[name="disabled_person_relationship"]')?.value || null, birth_date: row.querySelector('[name="dependent_birth_date"]')?.value || null, child_type: row.querySelector('[name="child_type"]')?.value || null, birth_order: row.querySelector('[name="birth_order"]')?.value ? Number(row.querySelector('[name="birth_order"]').value) : null, eligible: row.querySelector('[name="eligible"]')?.checked ?? true }));
        state.incomes = [...root.querySelectorAll('[data-income]')].map((row) => {
            const item = { ...(row.dataset.id ? { id: Number(row.dataset.id) } : {}), income_type: row.querySelector('[name="income_type"]').value, description: row.querySelector('[name="description"]').value, gross_amount: money(row.querySelector('[name="gross_amount"]').value), exempt_amount: money(row.querySelector('[name="exempt_amount"]').value) };
            ['income_subtype', 'expense_activity', 'expense_method_selection', 'tax_treatment'].forEach((field) => {
                const value = row.querySelector(`[name="${field}"]`)?.value;
                if (value) item[field] = value;
            });
            const holdingYears = row.querySelector('[name="holding_years"]')?.value;
            if (holdingYears) item.holding_years = Number(holdingYears);
            const actualExpense = row.querySelector('[name="actual_expense"]');
            if (actualExpense && (item.expense_method_selection === 'actual' || !row.querySelector('[name="expense_method_selection"]'))) item.actual_expense = money(actualExpense.value);
            return item;
        });
        state.allowances = [...root.querySelectorAll('[data-allowance]')].map((input) => ({ ...(input.dataset.id ? { id: Number(input.dataset.id) } : {}), code: input.dataset.allowance, amount: money(input.value) })).filter((item) => Number(item.amount) > 0);
        /*
         * A claimed exemption carries the filer's affirmation of the printed conditions the
         * engine cannot check. It is read from the checkbox rather than defaulted, because the
         * API refuses a positive amount without it — deliberately, so the product never asserts
         * an entitlement on the reader's behalf.
         */
        state.income_exemptions = [...root.querySelectorAll('[data-income-exemption]')].map((row) => ({
            ...(row.dataset.id ? { id: Number(row.dataset.id) } : {}),
            code: row.dataset.incomeExemption,
            amount: money(row.querySelector('[name="exemption_amount"]').value),
            declarations_confirmed: row.querySelector('[name="exemption_declarations"]').checked,
        })).filter((item) => Number(item.amount) > 0);
        state.donations = [...root.querySelectorAll('[data-donation]')].map((row) => ({ ...(row.dataset.id ? { id: Number(row.dataset.id) } : {}), code: row.dataset.donation, amount: money(row.querySelector('[name="donation_amount"]').value) })).filter((item) => item.code && Number(item.amount) > 0);
        state.withholdings = [...root.querySelectorAll('[data-withholding]')].map((row) => ({ ...(row.dataset.id ? { id: Number(row.dataset.id) } : {}), type: row.querySelector('[name="withholding_type"]').value, amount: money(row.querySelector('[name="withholding_amount"]').value), payer_name: row.querySelector('[name="payer_name"]').value || undefined })).filter((item) => Number(item.amount) > 0);
        saveState(state);
    };

    const addDependent = (item = {}) => {
        const count = root.querySelectorAll('[data-dependent]').length + 1;
        const disabledRelationshipEnabled = metadata.allowances.some((allowance) => allowance.code === 'DISABLED_PERSON'
            && allowance.rule?.conditions?.relationship_field === 'disabled_person_relationship');
        root.querySelector('[data-dependent-list]').insertAdjacentHTML('beforeend', rowShell('dependent', item.id, `ผู้พึ่งพาคนที่ ${count}`, `
            <label class="ui-field">ความสัมพันธ์<span class="ui-field-required" aria-hidden="true">*</span>
                <select name="relation_type">
                    <option value="child" ${(!item.relation_type || item.relation_type === 'child') ? 'selected' : ''}>บุตร</option><option value="father" ${item.relation_type === 'father' ? 'selected' : ''}>บิดา</option>
                    <option value="mother" ${item.relation_type === 'mother' ? 'selected' : ''}>มารดา</option><option value="spouse_father" ${item.relation_type === 'spouse_father' ? 'selected' : ''}>บิดาของคู่สมรส</option>
                    <option value="spouse_mother" ${item.relation_type === 'spouse_mother' ? 'selected' : ''}>มารดาของคู่สมรส</option><option value="disabled_person" ${item.relation_type === 'disabled_person' ? 'selected' : ''}>ผู้พิการ/ทุพพลภาพ</option>
                </select></label>
            <label class="ui-field" data-dependent-birth>วันเดือนปีเกิด<span class="ui-field-note">จำเป็นสำหรับบุตรชอบด้วยกฎหมาย</span>
                <input name="dependent_birth_date" type="date" value="${escapeHtml(item.birth_date || '')}"></label>
            <label class="ui-field" data-child-field>ลำดับบุตร<span class="ui-field-note">เฉพาะกรณีบุตร</span>
                <input name="birth_order" type="number" min="1" value="${escapeHtml(item.birth_order || '')}"></label>
            <label class="ui-field" data-child-field>ประเภทบุตร<span class="ui-field-note">ใช้พิจารณาลำดับและจำนวนบุตร</span>
                <select name="child_type"><option value="">เลือกประเภท</option><option value="legitimate" ${item.child_type === 'legitimate' ? 'selected' : ''}>บุตรชอบด้วยกฎหมาย</option><option value="adopted" ${item.child_type === 'adopted' ? 'selected' : ''}>บุตรบุญธรรม</option></select></label>
            ${disabledRelationshipEnabled ? `<label class="ui-field" data-disabled-field>ประเภทความสัมพันธ์กับผู้พิการ/ทุพพลภาพ<span class="ui-field-required">จำเป็น</span>
                <select name="disabled_person_relationship"><option value="">เลือกความสัมพันธ์</option><option value="family_member" ${item.disabled_person_relationship === 'family_member' ? 'selected' : ''}>บุคคลในครอบครัวตามใบแนบ</option><option value="other_person" ${item.disabled_person_relationship === 'other_person' ? 'selected' : ''}>บุคคลอื่นที่อยู่ในความดูแลตามกฎหมาย</option></select>
                <span class="ui-help">ใบแนบ ข้อ 5 จำกัดบุคคลอื่นไว้ไม่เกิน 1 คน ระบบใช้ข้อมูลนี้บน rule version ที่รองรับเท่านั้น</span></label>` : ''}
            <label class="flex items-center gap-2.5 text-sm text-slate-700 sm:col-span-2">
                <input name="eligible" type="checkbox" ${item.eligible === false ? '' : 'checked'}> ข้าพเจ้ายืนยันว่าผู้พึ่งพารายนี้เข้าเงื่อนไขตามใบแนบและมีหลักฐานประกอบ</label>`));
        const row = root.querySelector('[data-dependent-list]').lastElementChild;
        row.querySelectorAll('[data-child-field]').forEach((field) => field.classList.toggle('hidden', row.querySelector('[name="relation_type"]').value !== 'child'));
        row.querySelectorAll('[data-disabled-field]').forEach((field) => field.classList.toggle('hidden', row.querySelector('[name="relation_type"]').value !== 'disabled_person'));
        const legitimate = row.querySelector('[name="relation_type"]').value === 'child'
            && row.querySelector('[name="child_type"]').value === 'legitimate';
        row.querySelector('[name="child_type"]').toggleAttribute('required', row.querySelector('[name="relation_type"]').value === 'child');
        row.querySelector('[name="birth_order"]').toggleAttribute('required', legitimate);
        row.querySelector('[name="dependent_birth_date"]').toggleAttribute('required', legitimate);
    };

    /**
     * Whether another income line may be added, and the reason when it may not.
     *
     * The add button is the only way to reach a 101st row, so the limit is stated here rather than
     * left for the server to refuse after the reader has entered the whole return.
     */
    const refreshIncomeCapacity = () => {
        const count = root.querySelectorAll('[data-income]').length;
        const full = count >= MAX_INCOME_ROWS;
        const button = root.querySelector('[data-add-income]');
        const note = root.querySelector('[data-income-capacity]');
        if (button) button.disabled = full;
        if (!note) return;
        note.classList.toggle('hidden', !full);
        note.innerHTML = full
            ? `<div class="ui-note ui-note-warning"><span class="ui-note-icon bg-amber-600">!</span><div>
                <p class="font-bold">ครบ ${MAX_INCOME_ROWS} แหล่งเงินได้แล้ว</p>
                <p>การคำนวณหนึ่งครั้งรับได้สูงสุด ${MAX_INCOME_ROWS} แหล่ง หากยังมีอีก ให้รวมยอดของแหล่งที่เป็นประเภทเดียวกันเข้าด้วยกัน ผลการคำนวณเท่ากัน เพราะระบบรวมยอดตามประเภทอยู่แล้วก่อนหักค่าใช้จ่าย</p>
            </div></div>` : '';
    };

    const addIncome = (item = {}) => {
        const count = root.querySelectorAll('[data-income]').length + 1;
        /*
         * No category is preselected. A new row used to arrive as 40(1) already chosen, so a
         * reader who added a row for rent or a fee and went straight to the amount had it taxed
         * under a rule they never picked — 40(1) deducts 50% capped at 100,000, while 40(5) rent
         * deducts 30% uncapped — and nothing said so. An empty required field asks the question
         * instead of answering it on the reader's behalf.
         */
        const placeholder = `<option value="" disabled ${item.income_type ? '' : 'selected'}>— เลือกประเภทเงินได้ —</option>`;
        const options = metadata.incomes.map((income) => `<option value="${income.code}" ${income.code === item.income_type ? 'selected' : ''}>${escapeHtml(`${income.section_code || ''} — ${income.plain_language_name || income.name || income.code}`)}</option>`).join('');
        root.querySelector('[data-income-list]').insertAdjacentHTML('beforeend', rowShell('income', item.id, `แหล่งเงินได้ที่ ${count}`, `
            <label class="ui-field">ประเภทเงินได้<span class="ui-field-required" aria-hidden="true">*</span>
                <select name="income_type" required>${placeholder}${options}</select>
                <span class="ui-help">เลือกให้ตรงกับลักษณะเงินได้จริง อัตราหักค่าใช้จ่ายต่างกันตามประเภท เลือกประเภทเดียวกันได้หลายแหล่ง</span></label>
            <label class="ui-field">ผู้จ่ายเงินได้ / แหล่งเงินได้<span class="ui-field-note">ไม่บังคับ</span>
                <input name="description" maxlength="255" placeholder="เช่น นายจ้าง ผู้เช่า หรือลูกค้า" value="${escapeHtml(item.description || '')}">
                <span class="ui-help">ใช้ช่วยแยกหนังสือรับรองหรือหลักฐานเมื่อมีหลายแหล่ง</span></label>
            <label class="ui-field">เงินได้ทั้งปี (บาท)<span class="ui-field-required" aria-hidden="true">*</span>
                <input name="gross_amount" inputmode="decimal" required placeholder="0.00" value="${escapeHtml(item.gross_amount || '')}">
                <span class="ui-help">กรอกยอดก่อนหักค่าใช้จ่ายและค่าลดหย่อน</span></label>
            <label class="ui-field">ส่วนที่ได้รับยกเว้น (บาท)<span class="ui-field-note">ไม่บังคับ</span>
                <input name="exempt_amount" inputmode="decimal" value="${escapeHtml(item.exempt_amount || '0')}">
                <span class="ui-help">กรอกเฉพาะยอดที่ได้รับยกเว้นและมีหลักฐาน อย่ากรอกค่าใช้จ่ายในช่องนี้</span></label>
            <div data-subtype-slot class="sm:col-span-2"></div>`));
        updateSubtype(root.querySelector('[data-income-list]').lastElementChild, item);
        refreshIncomeCapacity();
    };

    const updateExpenseFields = (row, saved = {}) => {
        const income = metadata.incomes.find((item) => item.code === row.querySelector('[name="income_type"]').value);
        const subtype = row.querySelector('[name="income_subtype"]')?.value || null;
        const rule = income?.expense_rules?.find((item) => item.income_subtype === subtype);
        const slot = row.querySelector('[data-expense-fields]');
        if (!slot) return;
        if (!subtype && income?.expense_rules?.some((item) => item.income_subtype)) {
            slot.innerHTML = '<div class="ui-note ui-note-info"><span class="ui-note-icon bg-blue-600">i</span><p>เลือกประเภทย่อยก่อน เพื่อให้ระบบแสดงกิจกรรมและวิธีหักค่าใช้จ่ายที่ตรงกับบรรทัดในแบบ</p></div>';
            return;
        }
        if (!rule || rule.status !== 'VERIFIED') {
            slot.innerHTML = '<div class="ui-note ui-note-warning"><span class="ui-note-icon bg-amber-600">!</span><p>รายการนี้มีในแบบ แต่ระบบจำลองรุ่นปัจจุบันยังไม่รองรับการคำนวณอัตโนมัติ</p></div>';
            return;
        }
        let fields = '';
        let selectedRule = rule;
        if (rule.keyed_by === 'expense_activity') {
            fields += `<label class="ui-field sm:col-span-2">กิจกรรมตามตารางที่ 2<span class="ui-field-required" aria-hidden="true">*</span>
                <select name="expense_activity" required><option value="">เลือกกิจกรรม</option>${rule.keys.map((key) => `<option value="${key.code}" ${key.code === saved.expense_activity ? 'selected' : ''}>${key.row}. ${escapeHtml(key.label)}</option>`).join('')}</select>
                <span class="ui-help">อ้างอิง: คำแนะนำ ภ.ง.ด.90 ตารางที่ 2 หน้า 17 ระบบเลือกอัตราจากกิจกรรมบนเซิร์ฟเวอร์</span></label>`;
            selectedRule = rule.keys.find((key) => key.code === saved.expense_activity) || null;
        }
        if (rule.keyed_by === 'holding_years') {
            fields += `<label class="ui-field">จำนวนปีที่ถือครอง<span class="ui-field-required" aria-hidden="true">*</span>
                <input name="holding_years" type="number" min="1" max="200" required value="${escapeHtml(saved.holding_years || '')}">
                <span class="ui-help">นับตามปีปฏิทินตามคำแนะนำ ภ.ง.ด.90 ข้อ 7 ข้อย่อย 3 (2)</span></label>`;
        }
        if (selectedRule?.expense_method_selection_required) {
            const selection = saved.expense_method_selection || '';
            fields += `<label class="ui-field">วิธีหักค่าใช้จ่าย<span class="ui-field-required" aria-hidden="true">*</span>
                <select name="expense_method_selection" required>
                    <option value="">เลือกวิธี</option><option value="percentage" ${selection === 'percentage' ? 'selected' : ''}>หักค่าใช้จ่ายแบบเหมาตามอัตราในแบบ</option>
                    <option value="actual" ${selection === 'actual' ? 'selected' : ''}>หักค่าใช้จ่ายจริงตามความจำเป็นและสมควร</option>
                </select><span class="ui-help">ระบบคำนวณอัตราเหมาให้เอง หากเลือกหักจริงให้กรอกยอดจากหลักฐาน</span></label>
                <label class="ui-field ${selection === 'actual' ? '' : 'hidden'}" data-actual-expense-field>ค่าใช้จ่ายจริง (บาท)<span class="ui-field-required" aria-hidden="true">*</span>
                    <input name="actual_expense" inputmode="decimal" value="${escapeHtml(saved.actual_expense || '')}" ${selection === 'actual' ? 'required' : ''}>
                    <span class="ui-help">กรอกยอดค่าใช้จ่ายจริง ไม่ใช่ยอดเงินได้หรือยอดที่ระบบคำนวณ</span></label>`;
        } else if (selectedRule?.method === 'actual') {
            fields += `<label class="ui-field sm:col-span-2">ค่าใช้จ่ายจริง (บาท)<span class="ui-field-required" aria-hidden="true">*</span><input name="actual_expense" inputmode="decimal" required value="${escapeHtml(saved.actual_expense || '')}"><span class="ui-help">กิจกรรมลำดับ 44 ให้กรอกค่าใช้จ่ายจริงตามความจำเป็นและสมควร</span></label>`;
        } else if (selectedRule) {
            const descriptions = { fixed: 'รายการนี้ไม่มีค่าใช้จ่ายให้หักในบรรทัดของแบบ', percentage: 'ระบบหักค่าใช้จ่ายตามอัตราในแบบให้อัตโนมัติ', percentage_limit: 'ระบบหักค่าใช้จ่ายตามอัตราและเพดานในแบบให้อัตโนมัติ' };
            fields += `<div class="ui-note ui-note-info sm:col-span-2"><span class="ui-note-icon bg-blue-600">i</span><p>${escapeHtml(descriptions[selectedRule.method] || 'ระบบเลือกวิธีหักค่าใช้จ่ายจากข้อมูลที่กรอกและกฎที่เผยแพร่')}</p></div>`;
        }
        if (income.code === 'SECTION_40_8' && subtype === 'GIFT_OR_SUPPORT_RECEIVED') {
            fields += `<label class="ui-field sm:col-span-2">วิธีนำเงินได้รายการนี้ไปคำนวณ<span class="ui-field-required" aria-hidden="true">*</span>
                <select name="tax_treatment" required><option value="PROGRESSIVE" ${saved.tax_treatment !== 'SEPARATE_RATE' ? 'selected' : ''}>รวมคำนวณกับเงินได้อื่นตามอัตราก้าวหน้า</option><option value="SEPARATE_RATE" ${saved.tax_treatment === 'SEPARATE_RATE' ? 'selected' : ''}>เลือกเสียภาษีแยกตาม ภ.ง.ด.90 ข้อ 9</option></select>
                <span class="ui-help">ยอดที่กรอกต้องเป็นส่วนที่ไม่ได้รับยกเว้นตามที่แบบข้อ 9 ให้ผู้มีเงินได้ระบุ</span></label>`;
        }
        slot.innerHTML = fields;
    };

    const updateSubtype = (row, saved = {}) => {
        const selected = row.querySelector('[name="income_type"]').value;
        const slot = row.querySelector('[data-subtype-slot]');
        /*
         * A row whose category has not been chosen yet is not an unsupported row. Falling through
         * would have found no rule and told the reader "ยังไม่รองรับการคำนวณอัตโนมัติ", which names a
         * limitation that does not exist and hides the one thing they actually need to do.
         */
        if (!selected) {
            slot.innerHTML = `<div class="ui-note ui-note-info"><span class="ui-note-icon bg-blue-600">i</span>
                <p>เลือกประเภทเงินได้ก่อน ระบบจึงจะแสดงประเภทย่อย กิจกรรม และวิธีหักค่าใช้จ่ายที่ตรงกับบรรทัดในแบบ</p></div>
                <div data-expense-fields class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2"></div>`;

            return;
        }
        const income = metadata.incomes.find((item) => item.code === selected);
        const choices = income?.expense_rules?.filter((rule) => rule.income_subtype) || [];
        if (income?.metadata_error) {
            // Unsupported is stated in words beside a warning icon, never by colour alone.
            slot.innerHTML = `<div class="ui-note ui-note-warning"><span class="ui-note-icon bg-amber-600">!</span>
                <p><strong class="font-bold">ยังไม่รองรับในการคำนวณอัตโนมัติ:</strong> ${escapeHtml(income.metadata_error)}</p></div>`;
            return;
        }
        const guidance = `<div class="ui-note ui-note-info"><span class="ui-note-icon bg-blue-600">i</span><div><p class="font-bold">${escapeHtml(income?.form_sections?.[formCode] || income?.name || '')}</p><p>${escapeHtml(income?.examples || '')}</p></div></div>`;
        slot.innerHTML = `${guidance}${choices.length ? `<label class="ui-field mt-3">ประเภทย่อยของเงินได้<span class="ui-field-required" aria-hidden="true">*</span>
            <select name="income_subtype" required><option value="">เลือกประเภทย่อย</option>${choices.map((choice) =>
                `<option value="${choice.income_subtype}" ${choice.income_subtype === saved.income_subtype ? 'selected' : ''} ${choice.status !== 'VERIFIED' ? 'disabled' : ''}>${escapeHtml(choice.label || choice.income_subtype)} — ${choice.status === 'VERIFIED' ? 'รองรับ' : 'ยังไม่รองรับ'}</option>`).join('')}</select>
            <span class="ui-help">เลือกบรรทัดที่ตรงกับลักษณะเงินได้ในแบบ ภ.ง.ด.90</span></label>` : ''}<div data-expense-fields class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2"></div>`;
        updateExpenseFields(row, saved);
    };

    const addDonation = (code, label, item = {}) => {
        root.querySelector('[data-donation-list]').insertAdjacentHTML('beforeend', `
            <div data-donation="${code}" data-id="${item.id || ''}" class="rounded-xl border border-slate-200 p-4">
                <label class="ui-field">${escapeHtml(label)}<span class="ui-field-note">ยอดรวมทั้งปี</span>
                    <input name="donation_amount" inputmode="decimal" placeholder="0.00" value="${escapeHtml(item.amount || '')}">
                    <span class="ui-help">กรอกยอดรวมของประเภทนี้เพียงครั้งเดียว ระบบคำนวณเพดานให้เอง</span>
                </label>
            </div>`);
    };

    const refreshUniqueWithholdingOptions = () => {
        const rows = [...root.querySelectorAll('[data-withholding]')];
        const selected = rows.map((row) => row.querySelector('[name="withholding_type"]').value);
        rows.forEach((row) => {
            const own = row.querySelector('[name="withholding_type"]').value;
            row.querySelectorAll('option').forEach((option) => {
                if (uniquePrepaymentTypes.has(option.value)) {
                    option.disabled = option.value !== own && selected.includes(option.value);
                }
            });
        });
    };

    const addWithholding = (item = {}) => {
        const count = root.querySelectorAll('[data-withholding]').length + 1;
        root.querySelector('[data-withholding-list]').insertAdjacentHTML('beforeend', rowShell('withholding', item.id, `รายการที่ ${count}`, `
            <label class="ui-field">ประเภท<span class="ui-field-required" aria-hidden="true">*</span>
                <select name="withholding_type">
                    <option value="withholding">ภาษีหัก ณ ที่จ่าย</option>
                    <option value="pnd93">ภาษีชำระตาม ภ.ง.ด.93</option>
                    <option value="pnd94">ภาษีชำระตาม ภ.ง.ด.94</option>
                    <option value="foreign_tax_credit" disabled>เครดิตภาษีต่างประเทศ — รุ่นปัจจุบันยังไม่รองรับ</option>
                    <option value="other_credit" disabled>เครดิตภาษีอื่น — รุ่นปัจจุบันยังไม่รองรับ</option>
                </select></label>
            <label class="ui-field">จำนวนเงิน (บาท)<span class="ui-field-required" aria-hidden="true">*</span>
                <input name="withholding_amount" inputmode="decimal" value="${escapeHtml(item.amount || '0')}"></label>
            <label class="ui-field sm:col-span-2">ชื่อผู้จ่ายเงินได้<span class="ui-field-note">ไม่บังคับ</span>
                <input name="payer_name" value="${escapeHtml(item.payer_name || '')}"></label>`));
        if (item.type) root.querySelector('[data-withholding-list]').lastElementChild.querySelector('[name="withholding_type"]').value = item.type;
        refreshUniqueWithholdingOptions();
    };

    const duplicateIssues = () => {
        const blocking = [];
        const warnings = [];
        const repeated = (items, key) => {
            const seen = new Set();
            return items.filter((item) => {
                const value = key(item);
                if (!value) return false;
                if (seen.has(value)) return true;
                seen.add(value);
                return false;
            });
        };
        if (repeated(state.allowances, (item) => item.code).length) blocking.push({ step: 4, text: 'ค่าลดหย่อนประเภทเดียวกัน' });
        if (repeated(state.donations, (item) => item.code).length) blocking.push({ step: 5, text: 'เงินบริจาคประเภทเดียวกัน' });
        if (repeated(state.withholdings, (item) => uniquePrepaymentTypes.has(item.type) ? item.type : null).length) blocking.push({ step: 6, text: 'ยอด ภ.ง.ด.93/94 ประเภทเดียวกัน' });
        if (repeated(state.dependents, (item) => ['father', 'mother', 'spouse_father', 'spouse_mother'].includes(item.relation_type) ? item.relation_type : item.relation_type === 'child' && item.child_type && item.birth_order ? `child:${item.child_type}:${item.birth_order}` : null).length) blocking.push({ step: 2, text: 'ผู้พึ่งพาที่มีบทบาทหรือลำดับเดียวกัน' });
        const incomeFingerprint = (item) => JSON.stringify(Object.fromEntries(Object.entries(item).filter(([key]) => !['id', 'description'].includes(key))));
        if (repeated(state.incomes, incomeFingerprint).length) warnings.push({ step: 3, text: 'ข้อมูลรายได้และจำนวนเงินเหมือนกันทุกช่อง โปรดตรวจว่าเป็นคนละแหล่งจริง' });
        const dependentFingerprint = (item) => item.relation_type === 'disabled_person' || (item.relation_type === 'child' && !item.birth_order)
            ? JSON.stringify([item.relation_type, item.child_type, item.birth_date, item.disabled_person_relationship]) : null;
        if (repeated(state.dependents, dependentFingerprint).length) warnings.push({ step: 2, text: 'ข้อเท็จจริงของผู้พึ่งพาเหมือนกัน แต่ข้อมูลปัจจุบันยืนยันตัวบุคคลไม่ได้' });
        return { blocking, warnings };
    };

    const renderReview = () => {
        collect();
        const incomeRows = state.incomes.map((item) => {
            const meta = metadata.incomes.find((income) => income.code === item.income_type);
            const details = [meta?.section_code, meta?.plain_language_name, `${amountText(item.gross_amount)} บาท`, item.expense_method_selection === 'actual' ? `หักจริง ${amountText(item.actual_expense)} บาท` : item.expense_method_selection === 'percentage' ? 'หักแบบเหมา' : 'ระบบหักตามแบบ'].filter(Boolean).join(' · ');
            return details;
        }).join('<br>');
        const sections = [
            ['แบบและปีภาษี', `${state.form_code} · ปีภาษี ${state.tax_year}`, 0],
            ['ข้อมูลผู้เสียภาษีและครอบครัว', `${state.spouse ? 'มีคู่สมรส' : 'ไม่มีคู่สมรส'} · ผู้พึ่งพา ${state.dependents.length} คน`, 1],
            [formCode === 'PND90' ? 'เงินได้และค่าใช้จ่าย แยกตามมาตรา 40(1)–40(8)' : 'เงินได้มาตรา 40(1) และค่าใช้จ่าย', incomeRows || 'ยังไม่มีรายการ', 3],
            ['ค่าลดหย่อนที่กรอกเอง', state.allowances.map((item) => `${metadata.allowances.find((entry) => entry.code === item.code)?.name || item.code} ${amountText(item.amount)} บาท`).join('<br>') || 'ไม่มี', 4],
            ['เงินบริจาค', state.donations.map((item) => `${item.code === 'SPECIAL_DONATION' ? 'กรณีพิเศษ' : 'ทั่วไป'} ${amountText(item.amount)} บาท`).join('<br>') || 'ไม่มี', 5],
            ['ภาษีหัก ณ ที่จ่าย / ภาษีชำระไว้', state.withholdings.map((item) => `${withholdingLabels[item.type] || item.type} ${amountText(item.amount)} บาท`).join('<br>') || 'ไม่มี', 6],
        ];
        root.querySelector('[data-review]').innerHTML = sections.map(([title, detail, targetStep]) =>
            `<div class="ui-metric"><div class="flex items-start justify-between gap-3"><p class="ui-metric-label">${escapeHtml(title)}</p><button type="button" data-jump-step="${targetStep}" class="text-sm font-bold text-blue-700 underline decoration-blue-200 underline-offset-4 hover:text-blue-900">แก้ไข</button></div><p class="mt-1 font-semibold leading-7 text-[#0f2c5c]">${String(detail).split('<br>').map(escapeHtml).join('<br>')}</p></div>`).join('');
        const issues = duplicateIssues();
        const integrity = root.querySelector('[data-integrity-review]');
        const allIssues = [...issues.blocking, ...issues.warnings];
        integrity.classList.toggle('hidden', allIssues.length === 0);
        integrity.innerHTML = allIssues.length ? `<div class="ui-note ui-note-warning"><span class="ui-note-icon bg-amber-600">!</span><div><p class="font-bold">พบรายการซ้ำ</p>${allIssues.map((issue) => `<p class="mt-1">${escapeHtml(issue.text)} <button type="button" data-jump-step="${issue.step}" class="font-bold text-blue-700 underline">ไปตรวจสอบ</button></p>`).join('')}</div></div>` : '';
    };

    try {
        metadata.years = await api('/tax-years');
        state.tax_year ||= metadata.years[0]?.year;
        const formInfo = await api(`/tax-years/${state.tax_year}/forms/${formCode}`);
        const mappedIncomes = formInfo.supported_income_types || [];
        metadata.incomes = await Promise.all(mappedIncomes.map(async (income) => {
            try { return await api(`/tax-years/${state.tax_year}/income-types/${income.code}`); }
            catch (error) { return { ...income, expense_rules: [], metadata_error: error.message }; }
        }));
        metadata.allowances = await api(`/tax-years/${state.tax_year}/allowances`);
        // ใบแนบ ข้อ 13 and ข้อ 20 — a different stage of the calculation, so a different list.
        metadata.incomeExemptions = await api(`/tax-years/${state.tax_year}/income-exemptions`);
        root.querySelector('[data-metadata-loading]').classList.add('hidden');
        root.querySelector('[data-metadata]').innerHTML = `
            <label class="ui-field">ปีภาษี<span class="ui-field-required" aria-hidden="true">*</span>
                <select name="tax_year">${metadata.years.map((year) => `<option value="${year.year}" ${year.year === state.tax_year ? 'selected' : ''}>${escapeHtml(String(year.name || year.year))}</option>`).join('')}</select></label>
            <label class="ui-field">แบบภาษี<span class="ui-field-note">เลือกไว้แล้ว</span>
                <input value="${escapeHtml(`${formCode} — ${formInfo.name || ''}`)}" disabled></label>`;
        root.querySelector('[name="birth_date"]').value = state.profile?.birth_date || '';
        root.querySelector('[name="marital_status"]').value = state.profile?.marital_status || 'single';
        root.querySelector('[name="simulation_name"]').value = state.simulation_name || '';
        root.querySelector('[data-has-spouse]').checked = Boolean(state.spouse);
        root.querySelector('[data-spouse-fields]').hidden = !state.spouse;
        root.querySelector('[name="spouse_has_income"]').value = state.spouse?.has_income ? '1' : '0';
        root.querySelector('[name="filing_status"]').value = state.filing_status || state.spouse?.filing_status || 'separate';
        root.querySelector('[data-save-draft]').classList.toggle('hidden', !state.member_return_id);
        state.dependents.forEach(addDependent);
        // The first row starts empty for the same reason every later one does: the reader chooses
        // the category, the product never chooses it for them.
        (state.incomes.length ? state.incomes : [{}]).forEach((item) => addIncome(item));
        donationTypes.forEach(([code, label]) => addDonation(code, label, state.donations.find((item) => item.code === code)));
        state.withholdings.forEach(addWithholding);
        const manual = metadata.allowances.filter((item) => item.rule);
        const grouped = manual.reduce((groups, item) => {
            const group = allowanceGroups[item.category] || 'ค่าลดหย่อนอื่น';
            groups[group] ||= [];
            groups[group].push(item);
            return groups;
        }, {});
        const manualHtml = Object.entries(grouped).map(([group, items]) => `<section class="sm:col-span-2"><h3 class="mb-3 font-bold text-blue-950">${escapeHtml(group)}</h3><div class="grid grid-cols-1 gap-4 sm:grid-cols-2">${items.map((item) => {
            const saved = state.allowances.find((entry) => entry.code === item.code);
            return `<div class="rounded-xl border border-slate-200 p-4"><label class="ui-field">${escapeHtml(item.name || item.code)}<span class="ui-field-note">ยอดที่จ่ายจริง</span><input data-allowance="${item.code}" data-id="${saved?.id || ''}" inputmode="decimal" placeholder="0.00" value="${escapeHtml(saved?.amount || '')}"></label></div>`;
        }).join('')}</div></section>`).join('');
        const derived = metadata.allowances.filter((item) => derivedAllowanceCodes.has(item.code));
        /*
         * Every line ใบแนบ prints that this baseline cannot calculate — and nothing else.
         *
         * The test is `printed_on_form`, not the status. Filtering on PARTIAL_BLOCKED alone showed
         * two of six: ใบแนบ items 13, 16, 20 and 22 are printed lines the engine cannot compute,
         * and they were invisible, so a filer entitled to one was quoted a higher tax than the form
         * gives and was never told why. A master category the attachment does not print —
         * INSURANCE, OTHER, ANNUAL_TAX_MEASURES — still stays out, because listing it would send
         * the reader looking for a line that does not exist.
         *
         * Each card carries the reason its own source gives.
         */
        /*
         * ใบแนบ ข้อ 13 and ข้อ 20. Each line states the printed conditions and asks the reader to
         * affirm them, because none of them can be established from the amount: a geographic
         * zone, a contract date, a contractor's registration. The engine applies the printed
         * arithmetic to what the reader states and asserts no entitlement of its own — the same
         * footing as exempt income and every allowance in this product.
         */
        root.querySelector('[data-income-exemption-list]').innerHTML = metadata.incomeExemptions.length
            ? `<section><h3 class="mb-1 font-bold text-blue-950">เงินได้ที่ได้รับยกเว้นหลังหักค่าใช้จ่าย</h3>
                <p class="ui-help mb-3">ใบแนบ ข้อ 13 และ ข้อ 20 ระบุให้หักรายการเหล่านี้ออกจากเงินได้พึงประเมินหลังหักค่าใช้จ่ายแล้ว จึงเป็นคนละขั้นกับค่าลดหย่อนด้านบน</p>
                <div class="grid grid-cols-1 gap-4">${metadata.incomeExemptions.map((item) => {
        const saved = state.income_exemptions.find((entry) => entry.code === item.code);
        const rule = item.method === 'stepped_grant'
            ? `ระบบคำนวณให้ ${amountText(item.grant_per_step)} บาท ต่อทุกจำนวน ${amountText(item.step_amount)} บาทที่จ่ายจริง${item.maximum_amount ? ` รวมไม่เกิน ${amountText(item.maximum_amount)} บาท` : ''}`
            : `ระบบคำนวณให้ร้อยละ ${Number(item.percentage)} ของที่จ่ายจริง${item.maximum_amount ? ` แต่ไม่เกิน ${amountText(item.maximum_amount)} บาท` : ''}`;
        return `<div data-income-exemption="${item.code}" data-id="${saved?.id || ''}" class="rounded-xl border border-slate-200 p-4">
                        <label class="ui-field">${escapeHtml(item.name || item.code)}<span class="ui-field-note">ยอดที่จ่ายจริง</span>
                            <input name="exemption_amount" inputmode="decimal" placeholder="0.00" value="${escapeHtml(saved?.amount || '')}">
                            <span class="ui-help">${escapeHtml(rule)}</span></label>
                        <div class="mt-3 rounded-lg bg-slate-50 p-3">
                            <p class="ui-help mb-2 font-semibold text-slate-700">เงื่อนไขตามคำแนะนำการกรอกแบบ ซึ่งระบบตรวจสอบจากจำนวนเงินไม่ได้</p>
                            <ul class="ui-help list-disc space-y-1 pl-5">${item.declarations.map((line) => `<li>${escapeHtml(line)}</li>`).join('')}</ul>
                            <label class="mt-3 flex items-start gap-2 text-sm font-semibold text-blue-950">
                                <input type="checkbox" name="exemption_declarations" class="mt-1" ${saved?.declarations_confirmed ? 'checked' : ''}>
                                <span>ข้าพเจ้ายืนยันว่าเข้าเงื่อนไขข้างต้นครบถ้วน</span></label>
                        </div></div>`;
    }).join('')}</div></section>`
            : '';
        const blocked = metadata.allowances.filter((item) =>
            item.coverage?.printed_on_form && item.coverage?.status !== 'SUPPORTED');
        root.querySelector('[data-allowance-list]').innerHTML = `${manualHtml}<section class="sm:col-span-2 ui-note ui-note-info"><span class="ui-note-icon bg-blue-600">i</span><div><p class="font-bold">รายการที่ระบบคำนวณจากข้อมูลครอบครัว</p><p>${derived.map((item) => escapeHtml(item.name)).join(' · ')}</p></div></section>${blocked.length ? `<section class="sm:col-span-2"><h3 class="mb-3 font-bold text-blue-950">รายการที่มีในแบบแต่ยังไม่รองรับอัตโนมัติ</h3><div class="grid grid-cols-1 gap-3 sm:grid-cols-2">${blocked.map((item) => `<div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="font-semibold text-slate-700">${escapeHtml(item.name || item.code)}</p><p class="ui-help" data-coverage-reason="${item.code}">${escapeHtml(item.coverage.reason)}</p></div>`).join('')}</div></section>` : ''}`;
    } catch (error) {
        root.querySelector('[data-metadata-loading]').classList.add('hidden');
        showAlert(alert, errorText(error), 'error');
    }

    root.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.matches('[data-next]')) {
            collect();
            /*
             * Now that a category is no longer chosen for the reader, a row can leave this step
             * without one. Catching it here keeps the message beside the field it belongs to;
             * the submit handler's reportValidity() would otherwise raise it seven steps later.
             */
            const unchosen = [...root.querySelectorAll('[name="income_type"]')].find((select) => select.value === '');
            if (step === 3 && unchosen) {
                showAlert(validation, 'เลือกประเภทเงินได้ให้ครบทุกแหล่งก่อนไปขั้นตอนถัดไป', 'error');
                unchosen.focus();
                unchosen.scrollIntoView({ behavior: 'smooth', block: 'center' });

                return;
            }
            hideAlert(validation);
            showStep(step + 1);
        }
        if (button.matches('[data-back]')) showStep(step - 1);
        if (button.matches('[data-add-dependent]')) addDependent();
        if (button.matches('[data-add-income]')) addIncome();
        if (button.matches('[data-add-withholding]')) addWithholding();
        if (button.matches('[data-remove-row]')) {
            button.closest('[data-dependent], [data-income], [data-withholding]')?.remove();
            refreshUniqueWithholdingOptions();
            refreshIncomeCapacity();
        }
        if (button.matches('[data-jump-step]')) showStep(Number(button.dataset.jumpStep));
        if (button.matches('[data-reset]') && await confirmAction('ล้างข้อมูลแบบจำลองในเบราว์เซอร์?')) { clearState(formCode); window.location.reload(); }
        if (button.matches('[data-save-draft]')) {
            try { collect(); button.disabled = true; await persistExistingState(state); showAlert(alert, 'บันทึกแบบร่างแล้ว'); }
            catch (error) { showAlert(alert, errorText(error), 'error'); }
            finally { button.disabled = false; }
        }
        if (button.matches('[data-open-planning]')) {
            const allowance = state.allowances[0];
            const amount = await promptValue('ระบุจำนวนค่าลดหย่อนที่ต้องการทดลองปรับ', allowance?.amount || '0');
            if (amount === null) return;
            const scenario = allowance ? { allowances: { upsert: [{ code: allowance.code, amount: money(amount) }] } } : {};
            try {
                const plan = await api('/tax/plan', { method: 'POST', body: JSON.stringify({ tax_year: state.tax_year, form_code: state.form_code, base: { profile: state.profile, ...(state.spouse ? { spouse: state.spouse } : {}), dependents: state.dependents, incomes: state.incomes, allowances: state.allowances, donations: state.donations, withholdings: state.withholdings }, scenario }) });
                const panel = root.querySelector('[data-planning]');
                panel.innerHTML = renderPlanning(plan);
                panel.classList.remove('hidden');
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (error) { showAlert(alert, errorText(error), 'error'); }
        }
        if (button.matches('[data-save-member]')) {
            if (!authToken()) { window.sessionStorage.setItem('tax-simulator.after-auth', window.location.pathname); requireMember(); return; }
            try { button.disabled = true; const id = await persistGuestState(state); window.location.assign(`/dashboard/tax-returns/${id}`); }
            catch (error) { showAlert(alert, errorText(error), 'error'); button.disabled = false; }
        }
        if (button.matches('[data-edit-inputs]')) { root.querySelector('[data-result]').innerHTML = ''; reviewCard?.classList.remove('hidden'); showStep(0); }
        /*
         * Phase 4 — the reader can finally keep their answer.
         *
         * Until this, ten minutes of entering a household produced a screen to photograph. The
         * trace is the most defensible thing this product makes — sixteen named stages, each
         * citing the ใบแนบ line behind it — and it existed only in the DOM.
         *
         * Browser print, not a PDF library: the page is already laid out, `@media print` in
         * app.css handles the rest, and every desktop browser can save that to PDF. A server-side
         * renderer would add a dependency to reproduce a page we already have.
         */
        if (button.matches('[data-print-result]')) window.print();
    });

    /*
     * `<details>` cannot be opened by CSS, and the step-by-step trace lives inside one. These
     * listeners open every collapsed section in the result for the duration of the print and put
     * it back afterwards, so what is printed matches what the reader would see if they expanded
     * everything — and the screen is unchanged when they come back to it.
     */
    let reopened = [];
    window.addEventListener('beforeprint', () => {
        reopened = [...root.querySelectorAll('[data-result] details:not([open]), [data-planning] details:not([open])')];
        reopened.forEach((node) => { node.open = true; });
    });
    window.addEventListener('afterprint', () => {
        reopened.forEach((node) => { node.open = false; });
        reopened = [];
    });

    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-has-spouse]')) {
            root.querySelector('[data-spouse-fields]').hidden = !event.target.checked;
            if (event.target.checked) root.querySelector('[name="marital_status"]').value = 'married';
        }
        if (event.target.matches('[name="relation_type"]')) {
            const dependent = event.target.closest('[data-dependent]');
            dependent.querySelectorAll('[data-child-field]').forEach((field) => field.classList.toggle('hidden', event.target.value !== 'child'));
            dependent.querySelectorAll('[data-disabled-field]').forEach((field) => field.classList.toggle('hidden', event.target.value !== 'disabled_person'));
        }
        if (event.target.matches('[name="relation_type"], [name="child_type"]')) {
            const dependent = event.target.closest('[data-dependent]');
            const legitimate = dependent.querySelector('[name="relation_type"]').value === 'child'
                && dependent.querySelector('[name="child_type"]').value === 'legitimate';
            dependent.querySelector('[name="child_type"]').toggleAttribute('required', dependent.querySelector('[name="relation_type"]').value === 'child');
            dependent.querySelector('[name="birth_order"]').toggleAttribute('required', legitimate);
            dependent.querySelector('[name="dependent_birth_date"]').toggleAttribute('required', legitimate);
        }
        if (event.target.matches('[name="withholding_type"]')) refreshUniqueWithholdingOptions();
        if (event.target.matches('[name="income_type"]')) updateSubtype(event.target.closest('[data-income]'));
        if (event.target.matches('[name="income_subtype"]')) updateExpenseFields(event.target.closest('[data-income]'));
        if (event.target.matches('[name="expense_method_selection"]')) {
            const field = event.target.closest('[data-income]').querySelector('[data-actual-expense-field]');
            field?.classList.toggle('hidden', event.target.value !== 'actual');
            field?.querySelector('input')?.toggleAttribute('required', event.target.value === 'actual');
        }
        if (event.target.matches('[name="expense_activity"]')) {
            collect();
            const row = event.target.closest('[data-income]');
            const index = [...root.querySelectorAll('[data-income]')].indexOf(row);
            updateExpenseFields(row, state.incomes[index] || {});
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideAlert(validation);
        collect();
        if (!form.reportValidity() || [...root.querySelectorAll('[name="gross_amount"]')].some((input) => input.value.trim() === '')) {
            showAlert(validation, 'กรุณากรอกข้อมูลที่มีเครื่องหมายจำเป็นให้ครบก่อนคำนวณ', 'error');
            return;
        }
        const issues = duplicateIssues();
        if (issues.blocking.length) {
            showAlert(validation, `พบรายการซ้ำ: ${issues.blocking.map((issue) => issue.text).join(', ')}`, 'error');
            showStep(issues.blocking[0].step);
            return;
        }
        const button = root.querySelector('[data-calculate]');
        button.disabled = true;
        try {
            const payload = { tax_year: state.tax_year, form_code: state.form_code, profile: state.profile, ...(state.spouse ? { spouse: state.spouse } : {}), dependents: state.dependents.map(({ id, ...item }) => item), incomes: state.incomes.map(({ id, ...item }) => item), allowances: state.allowances.map(({ id, ...item }) => item), income_exemptions: state.income_exemptions, donations: state.donations.map(({ id, ...item }) => item), withholdings: state.withholdings.map(({ id, payer_name, ...item }) => item) };
            const response = state.member_return_id
                ? (await persistExistingState(state), await api(`/tax-returns/${state.member_return_id}/calculate`, { method: 'POST' }))
                : await api('/tax/calculate', { method: 'POST', body: JSON.stringify(payload) });
            const result = response.calculation || response;
            setGuestResult(result);
            // The approved result state replaces the pre-calculation review after a successful response.
            reviewCard?.classList.add('hidden');
            root.querySelector('[data-result]').innerHTML = renderResult(result);
            button.classList.add('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error) {
            showAlert(validation, errorText(error), 'error');
            focusFirstError(error);
        } finally {
            button.disabled = false;
        }
    });

    showStep(0);
}
