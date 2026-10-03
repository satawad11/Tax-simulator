import { api, errorText, setAuthToken } from './api.js';
import { currentMember, requireMember } from './auth.js';
import { icon } from './icons.js';
import { forgetViewer } from './session.js';
import { saveState } from './simulator-state.js';
import { renderPlanning, renderResult } from './tax-result.js';
import { confirmAction, currency, escapeHtml, hideAlert, promptValue, showAlert, thaiDate } from './ui.js';

/**
 * Milestone 09.1 — the member area, reconciled with the approved mockup's member screens.
 *
 * The behaviour is the one M9 shipped; what changed is the presentation. The overview leads with
 * summary tiles and quick actions rather than three bare counters; drafts and completed
 * simulations are cards rather than rows of an administrative table; every list that can be
 * empty now has a designed empty state naming the next action; and a completed return reads as
 * read-only instead of merely lacking buttons.
 *
 * No amount is derived here. Every figure comes from the API response as-is.
 */

const STATUS = {
    draft: ['แบบร่าง', 'bg-amber-100 text-amber-800'],
    completed: ['เสร็จสิ้น', 'bg-emerald-100 text-emerald-800'],
    archived: ['เก็บถาวร', 'bg-slate-200 text-slate-700'],
};

const RESULT_WORD = { PAYABLE: 'ต้องชำระเพิ่ม', REFUND: 'ได้คืน', ZERO: 'ไม่มียอด' };

const statusBadge = (status) => {
    const [label, tone] = STATUS[status] || [status, 'bg-slate-200 text-slate-700'];
    return `<span class="ui-status ${tone}">${escapeHtml(label)}</span>`;
};

const empty = (artwork, title, message, action = '') => `
    <div class="ui-empty" aria-hidden="false">
        <span class="ui-empty-icon" aria-hidden="true">${icon(artwork, 'size-7')}</span>
        <h3 class="ui-form-title">${escapeHtml(title)}</h3>
        <p class="ui-body max-w-md">${escapeHtml(message)}</p>
        ${action}
    </div>`;

/**
 * Empty-state artwork and button icons both come from `resources/icons.json` now.
 *
 * This module kept two private maps of SVG paths — one for empty states, one for buttons — and
 * three of the shapes were already drawn by `icon.blade.php` under different names.
 */
const EMPTY_ICON = { folder: 'document', clock: 'clock', chart: 'chart' };

const buttonIcon = (name) => icon(name, 'size-4 shrink-0');

const tile = (label, value, hint = '') => `
    <div class="ui-card">
        <p class="ui-metric-label">${escapeHtml(label)}</p>
        <p class="mt-2 text-3xl font-extrabold tabular-nums text-[#0f2c5c]">${value}</p>
        ${hint ? `<p class="mt-1 text-xs text-slate-500">${escapeHtml(hint)}</p>` : ''}
    </div>`;

const returnCard = (item) => `
    <article class="ui-card flex flex-col" data-return-card="${item.id}">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                ${statusBadge(item.status)}
                <h3 class="mt-2.5 text-lg leading-7 font-bold text-[#0f2c5c]">${escapeHtml(item.name)}</h3>
                <p class="ui-meta mt-1.5">
                    <span>${escapeHtml(item.form_code)}</span><span>ปีภาษี ${escapeHtml(String(item.tax_year))}</span>
                    ${item.status === 'draft' ? `<span>กรอกถึงขั้นตอนที่ ${escapeHtml(String(item.current_step))}</span>` : ''}
                </p>
            </div>
            <p class="text-xs whitespace-nowrap text-slate-500">แก้ไข ${thaiDate(item.updated_at)}</p>
        </div>
        <div class="mt-5 flex flex-wrap gap-2">
            <a class="ui-button-primary" href="/dashboard/tax-returns/${item.id}">${item.status === 'draft' ? 'กรอกต่อ' : 'ดูผลการคำนวณ'}</a>
            <button class="ui-button-secondary" data-duplicate="${item.id}">ทำสำเนา</button>
            ${item.status === 'draft' ? `<button class="ui-button-danger-quiet" data-delete="${item.id}">ลบแบบร่าง</button>` : ''}
        </div>
    </article>`;

export async function initializeMemberDashboard() {
    const root = document.querySelector('[data-member-page]');
    if (!root || !requireMember()) return;
    const alert = root.querySelector('[data-member-alert]');
    const loading = root.querySelector('[data-member-loading]');
    const content = root.querySelector('[data-member-content]');
    const page = root.dataset.memberPage;
    const id = root.dataset.taxReturnId;
    const member = await currentMember();
    if (!member) { requireMember(); return; }
    root.querySelector('[data-member-greeting]').textContent = `สวัสดี ${member.name}`;

    const load = async () => {
        hideAlert(alert);
        loading.classList.remove('hidden');
        try {
            if (page === 'dashboard') await dashboard();
            else if (page === 'returns') await returns();
            else if (page === 'return') await detail();
            else if (page === 'history') await history();
            else if (page === 'planning') await planning();
            else if (page === 'account') await account();
        } catch (error) {
            showAlert(alert, errorText(error), 'error');
            content.innerHTML = '';
        } finally {
            loading.classList.add('hidden');
            loading.setAttribute('aria-busy', 'false');
        }
    };

    /**
     * Phase 4 — the account page.
     *
     * `PUT /auth/me` has existed since M5 with nothing calling it, so a member could not correct
     * their own name or address. Three things live here and no more: the two editable fields,
     * the verification state with a way to ask for the link again, and sign-out-everywhere.
     *
     * Changing the password is not duplicated here — it has its own page from Phase 1, with its
     * own rate limit and its own current-password check, and two forms writing the same
     * credential is how they drift apart.
     */
    const account = async () => {
        root.querySelector('[data-page-title]').textContent = 'บัญชีของฉัน';
        const me = await api('/auth/me');
        content.innerHTML = `
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <section class="ui-card">
                    <h2 class="ui-form-title">ข้อมูลบัญชี</h2>
                    <p class="ui-help">แก้ไขชื่อที่แสดงและอีเมลที่ใช้เข้าสู่ระบบ</p>
                    <form data-account-form class="mt-5 space-y-4">
                        <label class="ui-field">ชื่อที่แสดง<span class="ui-field-required" aria-hidden="true">*</span>
                            <input name="name" required maxlength="255" value="${escapeHtml(me.name ?? '')}">
                        </label>
                        <label class="ui-field">อีเมล<span class="ui-field-required" aria-hidden="true">*</span>
                            <input name="email" type="email" required maxlength="255" value="${escapeHtml(me.email ?? '')}">
                            <span class="ui-help">หากเปลี่ยนอีเมล ระบบจะส่งลิงก์ยืนยันไปยังอีเมลใหม่ และสถานะการยืนยันจะเริ่มใหม่</span>
                        </label>
                        <button type="submit" class="ui-button-primary">บันทึกการเปลี่ยนแปลง</button>
                    </form>
                    <p data-account-message role="status" aria-live="polite" class="mt-4 text-sm"></p>
                </section>

                <div class="space-y-5">
                    <section class="ui-card">
                        <h2 class="ui-form-title">ความปลอดภัย</h2>
                        <p class="ui-help">รหัสผ่านและอุปกรณ์ที่เข้าสู่ระบบไว้</p>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <a href="/account/password" class="ui-button-secondary">${buttonIcon('key')}เปลี่ยนรหัสผ่าน</a>
                            <button type="button" data-account-logout-all class="ui-button-danger-quiet">
                                ${buttonIcon('logout')}ออกจากระบบทุกอุปกรณ์
                            </button>
                        </div>
                        <p class="ui-help mt-3">
                            “ออกจากระบบทุกอุปกรณ์” จะยุติทุกเซสชันรวมถึงหน้านี้ ใช้เมื่อคุณเคยเข้าสู่ระบบบนเครื่องที่ไม่ใช่ของคุณ
                        </p>
                    </section>

                    <section class="ui-card">
                        <h2 class="ui-form-title">การยืนยันอีเมล</h2>
                        <p class="ui-help">
                            ${me.email_verified
                                ? 'อีเมลนี้ยืนยันแล้ว'
                                : 'อีเมลนี้ยังไม่ได้ยืนยัน หากไม่ยืนยัน เราอาจติดต่อกลับไม่ได้เมื่อคุณขอตั้งรหัสผ่านใหม่'}
                        </p>
                        ${me.email_verified ? '' : `<button type="button" data-account-resend class="ui-button-secondary mt-4">
                            ${buttonIcon('mail')}ส่งลิงก์ยืนยันอีกครั้ง
                        </button>`}
                    </section>
                </div>
            </div>`;

        const form = content.querySelector('[data-account-form]');
        const message = content.querySelector('[data-account-message]');
        const say = (text, ok) => {
            message.textContent = text;
            message.className = `mt-4 text-sm font-semibold ${ok ? 'text-emerald-700' : 'text-rose-700'}`;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            try {
                const data = Object.fromEntries(new FormData(form));
                const updated = await api('/auth/me', { method: 'PUT', body: JSON.stringify(data) });
                root.querySelector('[data-member-greeting]').textContent = `สวัสดี ${updated.name}`;
                say('บันทึกแล้ว', true);
                // The verification card's state depends on what was just saved, so re-render it.
                if (updated.email !== me.email) await load();
            } catch (error) {
                say(errorText(error), false);
            } finally {
                button.disabled = false;
            }
        });

        content.querySelector('[data-account-resend]')?.addEventListener('click', async (event) => {
            event.target.disabled = true;
            try { await api('/auth/email/resend', { method: 'POST' }); say('ส่งลิงก์ยืนยันให้แล้ว กรุณาตรวจกล่องจดหมายของคุณ', true); }
            catch (error) { say(errorText(error), false); event.target.disabled = false; }
        });

        content.querySelector('[data-account-logout-all]')?.addEventListener('click', async () => {
            if (!window.confirm('ออกจากระบบทุกอุปกรณ์? หน้านี้จะถูกออกจากระบบด้วย')) return;
            try { await api('/auth/logout-all', { method: 'POST' }); } finally {
                setAuthToken(null);
                forgetViewer();
                window.location.assign('/login');
            }
        });
    };

    const dashboard = async () => {
        const list = await api('/tax-returns?per_page=6');
        const rows = list.data || list;
        const drafts = rows.filter((item) => item.status === 'draft');
        const completed = rows.filter((item) => item.status === 'completed');
        root.querySelector('[data-page-title]').textContent = 'แดชบอร์ด';
        content.innerHTML = `
            <section aria-label="สรุปภาพรวม" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                ${tile('แบบร่างที่ค้างอยู่', drafts.length, 'กรอกต่อได้ทุกเมื่อ')}
                ${tile('การทดลองที่เสร็จสิ้น', completed.length, 'อ่านอย่างเดียว')}
                ${tile('รายการทั้งหมดล่าสุด', rows.length, 'แสดงได้สูงสุด 6 รายการ')}
            </section>

            <section class="mt-7" aria-label="ทางลัด">
                <div class="flex flex-wrap gap-3">
                    <a class="ui-button-primary" href="/tax-simulator">เริ่มแบบจำลองใหม่</a>
                    <a class="ui-button-secondary" href="/dashboard/tax-returns">ดูแบบภาษีทั้งหมด</a>
                    <a class="ui-button-secondary" href="/knowledge">อ่านความรู้ภาษี</a>
                </div>
            </section>

            <section class="mt-8">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 class="ui-form-title">แบบจำลองล่าสุด</h2>
                    <a class="ui-button-quiet" href="/dashboard/tax-returns">ดูทั้งหมด</a>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    ${rows.length ? rows.map(returnCard).join('') : ''}
                </div>
                ${rows.length ? '' : empty(EMPTY_ICON.folder, 'ยังไม่มีแบบจำลอง',
                    'เริ่มทดลองคำนวณ แล้วบันทึกไว้เพื่อกลับมาแก้ไขหรือเปรียบเทียบภายหลัง',
                    '<a class="ui-button-primary mt-1" href="/tax-simulator">เริ่มทดลองคำนวณ</a>')}
            </section>`;
    };

    const returns = async () => {
        root.querySelector('[data-page-title]').textContent = 'แบบภาษีของฉัน';
        content.innerHTML = `
            <form data-return-filter class="ui-card grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_1fr_1.4fr_auto] md:items-end">
                <label class="ui-field">สถานะ<select name="status">
                    <option value="">ทั้งหมด</option><option value="draft">แบบร่าง</option><option value="completed">เสร็จสิ้น</option>
                </select></label>
                <label class="ui-field">ปีภาษี<input name="tax_year" inputmode="numeric" placeholder="เช่น 2568"></label>
                <label class="ui-field">แบบ<select name="form">
                    <option value="">ทั้งหมด</option><option>PND90</option><option>PND91</option>
                </select></label>
                <label class="ui-field">ค้นหา<input name="q" placeholder="ชื่อแบบจำลอง"></label>
                <button class="ui-button-primary" type="submit">ค้นหา</button>
            </form>
            <div data-return-list class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2"></div>`;
        await filterReturns();
    };

    const filterReturns = async () => {
        const form = content.querySelector('[data-return-filter]');
        const params = new URLSearchParams([...new FormData(form || undefined)].filter(([, value]) => value));
        const list = await api(`/tax-returns?${params}`);
        const rows = list.data || list;
        const target = content.querySelector('[data-return-list]');
        if (!target) return;
        target.innerHTML = rows.length ? rows.map(returnCard).join('')
            : `<div class="md:col-span-2">${empty(EMPTY_ICON.folder, 'ไม่พบแบบจำลองตามตัวกรอง',
                'ลองล้างตัวกรอง หรือเริ่มแบบจำลองใหม่สำหรับปีภาษีที่ต้องการ',
                '<a class="ui-button-primary mt-1" href="/tax-simulator">เริ่มแบบจำลองใหม่</a>')}</div>`;
    };

    const detail = async () => {
        const item = await api(`/tax-returns/${id}`);
        root.querySelector('[data-page-title]').textContent = item.name;
        const readonly = item.status !== 'draft';
        content.innerHTML = `
            <section class="ui-card">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        ${statusBadge(item.status)}
                        <p class="ui-meta mt-2.5">
                            <span>${escapeHtml(item.form_code)}</span>
                            <span>ปีภาษี ${escapeHtml(String(item.tax_year))}</span>
                            <span>รุ่นกฎ ${escapeHtml(String(item.rule_version))}</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        ${readonly ? '' : `
                            <button data-resume class="ui-button-primary">กรอกต่อ</button>
                            <button data-calculate-member class="ui-button-secondary">คำนวณภาษี</button>
                            <button data-complete class="ui-button-secondary">ทำเครื่องหมายว่าเสร็จสิ้น</button>`}
                        <button data-duplicate="${item.id}" class="ui-button-secondary">ทำสำเนาเป็นแบบร่างใหม่</button>
                    </div>
                </div>
            </section>

            ${readonly ? `<div class="mt-5 ui-note ui-note-info">
                <span class="ui-note-icon bg-blue-600">i</span>
                <div><p class="font-bold">รายการนี้เป็นแบบอ่านอย่างเดียว</p>
                <p class="mt-1">การทดลองที่เสร็จสิ้นแล้วจะไม่ถูกแก้ไข เพื่อให้ผลที่บันทึกไว้ยังตรงกับข้อมูลเดิม
                หากต้องการปรับตัวเลข ให้ทำสำเนาเป็นแบบร่างใหม่</p></div></div>` : ''}

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <article class="ui-card">
                    <h2 class="font-bold text-[#0f2c5c]">ข้อมูลผู้เสียภาษีและครอบครัว</h2>
                    <div class="mt-3">
                        <div class="ui-summary-row"><span class="ui-summary-label">สถานภาพ</span>
                            <span class="ui-summary-value">${escapeHtml(item.profile?.marital_status || '—')}</span></div>
                        <div class="ui-summary-row"><span class="ui-summary-label">คู่สมรส</span>
                            <span class="ui-summary-value">${item.spouse ? 'มี' : 'ไม่มี'}</span></div>
                        <div class="ui-summary-row"><span class="ui-summary-label">ผู้พึ่งพา</span>
                            <span class="ui-summary-value">${item.dependents?.length || 0} คน</span></div>
                    </div>
                </article>
                <article class="ui-card">
                    <h2 class="font-bold text-[#0f2c5c]">รายการที่กรอกไว้</h2>
                    <div class="mt-3">
                        <div class="ui-summary-row"><span class="ui-summary-label">แหล่งเงินได้</span>
                            <span class="ui-summary-value">${item.incomes?.length || 0} รายการ</span></div>
                        <div class="ui-summary-row"><span class="ui-summary-label">ค่าลดหย่อน</span>
                            <span class="ui-summary-value">${item.allowances?.length || 0} รายการ</span></div>
                        <div class="ui-summary-row"><span class="ui-summary-label">เงินบริจาค</span>
                            <span class="ui-summary-value">${item.donations?.length || 0} รายการ</span></div>
                        <div class="ui-summary-row"><span class="ui-summary-label">ภาษีที่ถูกหักไว้</span>
                            <span class="ui-summary-value">${item.withholdings?.length || 0} รายการ</span></div>
                    </div>
                </article>
            </div>

            <nav class="mt-5 flex flex-wrap gap-3" aria-label="หน้าย่อยของแบบภาษีนี้">
                <a class="ui-button-secondary" href="/dashboard/tax-returns/${id}/history">ประวัติการคำนวณ</a>
                <a class="ui-button-secondary" href="/dashboard/tax-returns/${id}/planning">สถานการณ์วางแผน</a>
            </nav>
            <div data-member-result class="mt-6"></div>`;

        content.querySelector('[data-resume]')?.addEventListener('click', () => {
            const collections = ['dependents', 'incomes', 'allowances', 'income_exemptions', 'donations', 'withholdings'];
            saveState({
                tax_year: item.tax_year, form_code: item.form_code, profile: item.profile || {}, spouse: item.spouse,
                dependents: item.dependents || [],
                incomes: (item.incomes || []).map((row) => ({ ...row, income_type: row.income_type?.code || row.income_type })),
                allowances: (item.allowances || []).map((row) => ({ id: row.id, code: row.code, amount: row.input_amount })),
                // The affirmation is restored with the amount; re-asking for it silently, or
                // assuming it, would both be wrong.
                income_exemptions: (item.income_exemptions || []).map((row) => ({ id: row.id, code: row.code,
                    amount: row.input_amount, declarations_confirmed: Boolean(row.declarations_confirmed) })),
                donations: (item.donations || []).map((row) => ({ id: row.id, code: row.donation_code, amount: row.input_amount })),
                withholdings: item.withholdings || [], simulation_name: item.name, member_return_id: item.id,
                _original: { ...Object.fromEntries(collections.map((collection) => [collection, (item[collection] || []).map((row) => row.id)])), spouse: Boolean(item.spouse) },
            });
            window.location.assign(`/tax-simulator/${item.form_code.toLowerCase()}`);
        });
    };

    const history = async () => {
        root.querySelector('[data-page-title]').textContent = 'ประวัติการคำนวณ';
        const result = await api(`/tax-returns/${id}/calculations`);
        const rows = result.data || result;
        content.innerHTML = rows.length ? `
            <section class="ui-card">
                <h2 class="ui-form-title">ผลที่บันทึกไว้</h2>
                <p class="ui-help">แต่ละแถวคือผลที่บันทึกไว้ ณ เวลานั้น ระบบไม่คำนวณใหม่เมื่อเปิดหน้านี้</p>
                <div class="ui-scroll-x mt-5">
                    <table class="ui-table">
                        <thead><tr><th>วันเวลา</th><th>ผลสรุป</th><th>เงินได้สุทธิ</th><th>ภาษีที่คำนวณได้</th>
                            <th class="text-right">จำนวนสุทธิ</th><th></th></tr></thead>
                        <tbody>${rows.map((row) => `<tr>
                            <td class="whitespace-nowrap">${thaiDate(row.calculated_at)}</td>
                            <td>${escapeHtml(RESULT_WORD[row.result?.status] || row.result?.status || '—')}</td>
                            <td>${currency(row.net_income)}</td>
                            <td>${currency(row.calculated_tax ?? row.progressive_tax?.total)}</td>
                            <td class="text-right font-semibold">${currency(row.result?.amount)}</td>
                            <td class="text-right"><button data-calculation="${row.id}" class="ui-button-quiet">ดูรายละเอียด</button></td>
                        </tr>`).join('')}</tbody>
                    </table>
                </div>
            </section>
            <div data-history-detail class="mt-6"></div>`
            : empty(EMPTY_ICON.clock, 'ยังไม่มีประวัติการคำนวณ',
                'เมื่อคุณกดคำนวณภาษีจากแบบนี้ ผลแต่ละครั้งจะถูกเก็บไว้ที่นี่เพื่อเปรียบเทียบย้อนหลัง',
                `<a class="ui-button-primary mt-1" href="/dashboard/tax-returns/${id}">กลับไปที่แบบภาษีนี้</a>`);
    };

    const planning = async () => {
        root.querySelector('[data-page-title]').textContent = 'สถานการณ์วางแผน';
        const result = await api(`/tax-returns/${id}/scenarios`);
        const rows = result.data || result;
        content.innerHTML = `
            <div class="ui-note ui-note-info">
                <span class="ui-note-icon bg-blue-600">i</span>
                <div><p class="font-bold">ข้อมูลต้นฉบับไม่เปลี่ยน</p>
                <p class="mt-1">การสร้างและคำนวณสถานการณ์จะไม่แก้ไขแบบภาษีต้นทาง และไม่เพิ่มรายการลงในประวัติการคำนวณของแบบนั้น</p></div>
            </div>
            <div class="mt-5"><button data-create-scenario class="ui-button-primary">สร้างสถานการณ์ใหม่</button></div>
            ${rows.length ? `<div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">${rows.map((row) => `
                <article class="ui-card">
                    <h2 class="font-bold text-[#0f2c5c]">${escapeHtml(row.name)}</h2>
                    <p class="ui-meta mt-1.5">${row.calculated_at ? `คำนวณเมื่อ ${thaiDate(row.calculated_at)}` : 'ยังไม่ได้คำนวณ'}</p>
                    ${row.calculated_at ? `<div class="mt-3 grid grid-cols-3 gap-2">
                        <div class="ui-metric"><p class="ui-metric-label">ก่อนปรับ</p><p class="ui-metric-value text-base">${currency(row.before_tax)}</p></div>
                        <div class="ui-metric"><p class="ui-metric-label">หลังปรับ</p><p class="ui-metric-value text-base">${currency(row.after_tax)}</p></div>
                        <div class="ui-metric"><p class="ui-metric-label">ลดลง</p><p class="ui-metric-value text-base">${currency(row.estimated_tax_saving)}</p></div>
                    </div>` : ''}
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button data-calculate-scenario="${row.id}" class="ui-button-primary">คำนวณ</button>
                        <button data-rename-scenario="${row.id}" class="ui-button-secondary">แก้ชื่อ</button>
                        <button data-delete-scenario="${row.id}" class="ui-button-danger-quiet">ลบ</button>
                    </div>
                </article>`).join('')}</div>`
            : `<div class="mt-5">${empty(EMPTY_ICON.chart, 'ยังไม่มีสถานการณ์วางแผน',
                'สร้างสถานการณ์เพื่อทดลองเปลี่ยนตัวเลขบางรายการ แล้วดูผลเทียบกับแบบที่บันทึกไว้')}</div>`}
            <div data-scenario-result class="mt-6"></div>`;
    };

    content.addEventListener('submit', async (event) => {
        if (event.target.matches('[data-return-filter]')) { event.preventDefault(); await filterReturns(); }
    });

    content.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        try {
            if (button.dataset.duplicate) {
                const name = await promptValue('ชื่อแบบจำลองใหม่');
                if (name) {
                    const copy = await api(`/tax-returns/${button.dataset.duplicate}/duplicate`, { method: 'POST', body: JSON.stringify({ name }) });
                    window.location.assign(`/dashboard/tax-returns/${copy.id}`);
                }
            }
            if (button.dataset.delete && await confirmAction('ลบแบบร่างนี้?')) {
                await api(`/tax-returns/${button.dataset.delete}`, { method: 'DELETE' });
                button.closest('[data-return-card]')?.remove();
            }
            if (button.matches('[data-calculate-member]')) {
                button.disabled = true;
                const result = await api(`/tax-returns/${id}/calculate`, { method: 'POST' });
                content.querySelector('[data-member-result]').innerHTML = renderResult(result.calculation);
                button.disabled = false;
            }
            if (button.matches('[data-complete]') && await confirmAction('ยืนยันว่าเสร็จสิ้นการทดลอง? รายการจะกลายเป็นแบบอ่านอย่างเดียว')) {
                await api(`/tax-returns/${id}/complete`, { method: 'POST' });
                await detail();
            }
            if (button.dataset.calculation) {
                const result = await api(`/tax-returns/${id}/calculations/${button.dataset.calculation}`);
                const target = content.querySelector('[data-history-detail]');
                target.innerHTML = renderResult(result.calculation);
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (button.matches('[data-create-scenario]')) {
                const name = await promptValue('ชื่อสถานการณ์');
                if (name) { await api(`/tax-returns/${id}/scenarios`, { method: 'POST', body: JSON.stringify({ name, payload: {} }) }); await planning(); }
            }
            if (button.dataset.renameScenario) {
                const name = await promptValue('ชื่อใหม่');
                if (name) { await api(`/tax-returns/${id}/scenarios/${button.dataset.renameScenario}`, { method: 'PATCH', body: JSON.stringify({ name }) }); await planning(); }
            }
            if (button.dataset.deleteScenario && await confirmAction('ลบสถานการณ์นี้?')) {
                await api(`/tax-returns/${id}/scenarios/${button.dataset.deleteScenario}`, { method: 'DELETE' });
                await planning();
            }
            if (button.dataset.calculateScenario) {
                const result = await api(`/tax-returns/${id}/scenarios/${button.dataset.calculateScenario}/calculate`, { method: 'POST' });
                content.querySelector('[data-scenario-result]').innerHTML = renderPlanning(result.calculation_result || result);
            }
        } catch (error) {
            showAlert(alert, errorText(error), 'error');
            button.disabled = false;
        }
    });

    await load();
}
