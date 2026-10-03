import { icon } from '../icons.js';
import { authToken, setAuthToken } from '../api.js';
import { escapeHtml, confirmAction, promptValue, formDialog } from '../ui.js';
import { applyRoleVisibility, forgetViewer, resolveViewer } from '../session.js';
import { ACTION_KINDS, actionLabel, auditTimestamp, entityLabel } from './activity.js';
import { createRuleEditor } from './rule-editor.js';
import { RESOURCE_LABELS } from './rule-fields.js';
const API = '/api/v1';
const RULE_RESOURCES = ['tax-brackets', 'expense-rules', 'allowance-rules', 'allowance-cap-groups', 'donation-rules', 'recommendation-rules'];

/*
 * M9.1 — the console shares the site's session rather than keeping one of its own.
 *
 * It previously stored its token under a separate key, so signing in on the public site left an
 * administrator signed out here and they had to enter their password a second time. There was no
 * security difference between the two stores; there is now one session for the whole product.
 */
const token = authToken;
const setToken = setAuthToken;
const textNode = (tag, value, className = '') => {
    const node = document.createElement(tag);
    node.className = className;
    node.textContent = value ?? '—';
    return node;
};
const date = (value) => value ? new Intl.DateTimeFormat('th-TH', { dateStyle: 'short' }).format(new Date(value)) : '—';

/**
 * Values from the API reach these templates as text, never as markup.
 *
 * One escaping function for the product, not two. This module had its own, differing from
 * `ui.js` only in whether an apostrophe became `&#39;` or `&#039;` — two implementations of an
 * escaping routine is exactly the kind of duplication worth removing, because the day they drift
 * is the day one of them is wrong.
 */
const escapeAttribute = escapeHtml;

/**
 * M9.1 — the message line states its kind in words as well as colour, so an administrator who
 * does not perceive the tint still learns whether the action succeeded.
 */
function say(message, ok = false) {
    document.querySelectorAll('[data-admin-message]').forEach((node) => {
        node.textContent = `${ok ? 'สำเร็จ: ' : 'ไม่สำเร็จ: '}${message}`;
        node.className = `mt-4 text-sm font-semibold ${ok ? 'text-emerald-700' : 'text-rose-700'}`;
    });
}

/** M9.1 — the shared status pill, matching the public side's `.ui-status`. */
const STATUS_TONE = {
    published: 'bg-emerald-100 text-emerald-800',
    draft: 'bg-amber-100 text-amber-800',
    archived: 'bg-slate-200 text-slate-700',
    active: 'bg-emerald-100 text-emerald-800',
    inactive: 'bg-slate-200 text-slate-700',
    suspended: 'bg-rose-100 text-rose-800',
};

function statusPill(value, label = value) {
    return textNode('span', label, `ui-status ${STATUS_TONE[value] ?? 'bg-slate-200 text-slate-700'}`);
}

/** A full-width row explaining that a table is empty, instead of leaving a blank tbody. */
function emptyRow(tbody, columns, message) {
    const row = document.createElement('tr');
    const cell = textNode('td', message, 'px-3 py-8 text-center text-slate-500');
    cell.colSpan = columns;
    row.append(cell);
    tbody.append(row);
}

function describe(payload, fallback) {
    const messages = payload?.errors && typeof payload.errors === 'object' ? Object.values(payload.errors).flat() : [];
    return messages.length ? messages.join(' ') : payload?.message || fallback;
}

async function call(path, { method = 'GET', body } = {}) {
    const response = await fetch(`${API}${path}`, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token() ? { Authorization: `Bearer ${token()}` } : {}) },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    const payload = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) throw new Error(describe(payload, `คำขอไม่สำเร็จ (${response.status})`));
    return payload;
}

function reveal(page) {
    page.setAttribute('aria-busy', 'false');
    page.querySelector('[data-admin-loading]')?.classList.add('hidden');
    page.querySelector('[data-admin-content]')?.classList.remove('hidden');
}

function initSignIn() {
    const form = document.querySelector('[data-admin-login]');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(form);
        try {
            const result = await call('/auth/login', { method: 'POST', body: { email: data.get('email'), password: data.get('password'), device_name: 'admin-console' } });
            setToken(result?.data?.token ?? null);
            forgetViewer();
            await call('/admin');
            window.location.href = '/admin';
        } catch (error) {
            setToken(null);
            say(error.message);
        }
    });
}

function initSignOut() {
    document.querySelectorAll('[data-admin-signout]').forEach((button) => button.addEventListener('click', async () => {
        try { await call('/auth/logout', { method: 'POST' }); } catch { /* Clearing an invalid token is sufficient. */ }
        setToken(null);
        window.location.href = '/admin/login';
    }));
}

function addActionButton(parent, label, action, id, classes) {
    const button = textNode('button', label, classes);
    button.type = 'button';
    button.dataset[action.startsWith('version:') ? 'versionAction' : 'contentAction'] = action.split(':').pop();
    button.dataset[action.startsWith('version:') ? 'versionId' : 'contentId'] = id;
    parent.append(button);
}

function initContentActions(root = document) {
    root.querySelectorAll('[data-content-action]').forEach((button) => button.addEventListener('click', async () => {
        try {
            await call(`/admin/content/${button.dataset.contentId}/${button.dataset.contentAction}`, { method: 'POST', body: {} });
            window.location.reload();
        } catch (error) { say(error.message); }
    }));
}

function initVersionActions(root = document) {
    root.querySelectorAll('[data-version-action]').forEach((button) => button.addEventListener('click', async () => {
        const { versionAction, versionId } = button.dataset;
        try {
            if (versionAction === 'clone') {
                /*
                 * A clone either revises this year's baseline or starts the next year's. The year
                 * is picked from the years that actually exist rather than typed, so a draft can no
                 * longer be cloned into a year with no forms because of a typo; the default keeps
                 * the source's own year, which is the "revise this baseline" case.
                 */
                const sourceYear = button.dataset.versionYear || '';
                let years = [];
                try { years = (await call('/admin/tax-years')).data ?? []; } catch { /* offer the source year alone */ }
                const values = await formDialog('ทำสำเนาเป็นฉบับร่าง', [
                    {
                        name: 'tax_year', label: 'ปีภาษีปลายทาง', type: 'select', value: '',
                        options: [
                            { value: '', label: `ปีเดียวกัน (${sourceYear})` },
                            ...years
                                .filter((year) => String(year.year) !== String(sourceYear))
                                .map((year) => ({ value: String(year.year), label: year.name ? `${year.year} — ${year.name}` : String(year.year) })),
                        ],
                        help: 'หากต้องการเริ่มปีถัดไป ต้องเปิดปีภาษีนั้นที่หน้า "ปีภาษี" ก่อน',
                    },
                    { name: 'version', label: 'ชื่อเวอร์ชันของฉบับร่างใหม่', placeholder: 'เช่น 2569.1' },
                ], { confirmLabel: 'ทำสำเนา' });
                if (!values) return;
                if (!values.version) { say('กรุณาระบุชื่อเวอร์ชันของฉบับร่างใหม่'); return; }
                const body = { version: values.version };
                if (values.tax_year !== '') body.tax_year = Number(values.tax_year);
                const result = await call(`/admin/tax-rule-versions/${versionId}/clone`, { method: 'POST', body });
                window.location.href = `/admin/tax-rule-versions/${result.data.id}`;
            } else if (versionAction === 'validate') {
                const result = await call(`/admin/tax-rule-versions/${versionId}/validate`, { method: 'POST', body: {} });
                say(result.data.valid ? 'ผ่านการตรวจสอบโครงสร้าง' : `พบข้อผิดพลาด ${result.data.errors.length} รายการ`, result.data.valid);
            } else if (versionAction === 'publish' && await confirmAction('เผยแพร่ฉบับร่างนี้? ชุดกฎที่เผยแพร่แล้วจะแก้ไขไม่ได้อีก และชุดกฎที่เผยแพร่อยู่เดิมของปีภาษีนี้จะถูกจัดเก็บ')) {
                await call(`/admin/tax-rule-versions/${versionId}/publish`, { method: 'POST', body: {} });
                window.location.reload();
            } else if (versionAction === 'archive' && await confirmAction('จัดเก็บชุดกฎนี้? จะไม่ถูกใช้คำนวณอีกและแก้ไขไม่ได้')) {
                await call(`/admin/tax-rule-versions/${versionId}/archive`, { method: 'POST', body: {} });
                window.location.reload();
            } else if (versionAction === 'rename') {
                const description = await promptValue('คำอธิบายชุดกฎนี้', button.dataset.versionDescription || '');
                if (description === null) return;
                await call(`/admin/tax-rule-versions/${versionId}`, { method: 'PATCH', body: { description } });
                window.location.reload();
            }
        } catch (error) { say(error.message); }
    }));
}

/**
 * Action names, mapped to the shared icon set.
 *
 * This was ten inline SVG paths, several of them byte-identical to shapes `icon.blade.php` already
 * drew. The indirection stays rather than being flattened away, because the console speaks in
 * actions — promote, demote, suspend — while the icon set speaks in shapes. Naming the mapping is
 * what lets one change without dragging the other with it.
 */
const ACTION_ICON = {
    edit: 'pencil', deactivate: 'power', delete: 'trash',
    promote: 'shield', demote: 'shield-off', logout: 'logout',
    preview: 'eye', suspend: 'ban', restore: 'refresh', history: 'clock',
};

/**
 * A row action: an icon **and** its words.
 *
 * The icon makes a column of actions scannable; the words are what make it safe. Several of these
 * have no settled Thai icon convention — ปิดใช้งาน is deliberately not ลบ — and the accounts table
 * puts ถอดสิทธิ์ผู้ดูแล beside ออกจากระบบทุกอุปกรณ์, where two similar glyphs alone would buy a little
 * space at the price of a misclick that grants or removes administrator rights.
 */
function actionButton(label, action, className = 'ui-button-quiet') {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.innerHTML = `${icon(ACTION_ICON[action], 'size-4 shrink-0')}<span></span>`;
    button.querySelector('span').textContent = label;

    return button;
}

const METRICS = [
    { key: 'published', label: 'เนื้อหาที่เผยแพร่', hint: 'มองเห็นได้บนหน้าเว็บ',
        tone: 'bg-emerald-50 text-emerald-600', icon: 'check' },
    { key: 'draft', label: 'ฉบับร่าง', hint: 'ยังไม่เผยแพร่',
        tone: 'bg-amber-50 text-amber-600', icon: 'pencil' },
    { key: 'archived', label: 'จัดเก็บแล้ว', hint: 'เก็บไว้ ไม่แสดงผล',
        tone: 'bg-slate-100 text-slate-500', icon: 'archive' },
];

/**
 * The overview's "things to deal with" panel.
 *
 * A dashboard of counts says what the system holds; this says what an operator should do next. The
 * one that matters is a tax year that is open but has no published rule set — a member cannot run a
 * simulation for it — so it is a warning, not a note. Draft rule sets and draft content are lesser
 * nudges. Every item links to the page that resolves it, and a clean system says so rather than
 * showing an empty box.
 */
function renderAttention(page, { yearGaps, draftRules, draftContent }) {
    const attention = page.querySelector('[data-dashboard-attention]');
    if (!attention) return;

    const alerts = [];
    if (yearGaps.length) alerts.push({
        tone: 'warning', badge: 'bg-amber-600', icon: 'warning',
        title: `ปีภาษีที่ยังไม่มีชุดกฎเผยแพร่ (${yearGaps.length})`,
        detail: `ปี ${yearGaps.map((year) => year.year).join(', ')} เปิดใช้งานแต่ยังคำนวณภาษีไม่ได้จนกว่าจะเผยแพร่ชุดกฎ`,
        href: '/admin/tax-years', cta: 'ไปที่ปีภาษี',
    });
    if (draftRules) alerts.push({
        tone: 'info', badge: 'bg-blue-600', icon: 'scale',
        title: `ชุดกฎภาษีฉบับร่างค้างอยู่ (${draftRules})`,
        detail: 'มีฉบับร่างที่ยังไม่ได้เผยแพร่หรือจัดเก็บ',
        href: '/admin/rule-versions', cta: 'ดูชุดกฎภาษี',
    });
    if (draftContent) alerts.push({
        tone: 'info', badge: 'bg-blue-600', icon: 'pencil',
        title: `เนื้อหาฉบับร่าง (${draftContent})`,
        detail: 'บทความหรือข่าวที่ยังไม่เผยแพร่',
        href: '/admin/content?status=draft', cta: 'ดูเนื้อหาฉบับร่าง',
    });

    attention.innerHTML = alerts.length
        ? alerts.map((alert) => `
            <div class="ui-note ui-note-${alert.tone}">
                <span class="ui-note-icon ${alert.badge}">${icon(alert.icon, 'size-3.5')}</span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold">${escapeAttribute(alert.title)}</p>
                    <p>${escapeAttribute(alert.detail)}</p>
                    <a href="${alert.href}" class="mt-1 inline-flex items-center gap-1 font-semibold text-blue-700 hover:underline">
                        ${escapeAttribute(alert.cta)}${icon('arrow-right', 'size-3.5')}
                    </a>
                </div>
            </div>`).join('')
        : `<div class="ui-note ui-note-success">
                <span class="ui-note-icon bg-emerald-600">${icon('check', 'size-3.5')}</span>
                <div><p class="font-bold">ไม่มีรายการที่ต้องจัดการ</p><p>ทุกปีภาษีที่เปิดใช้งานมีชุดกฎเผยแพร่ และไม่มีฉบับร่างค้าง</p></div>
            </div>`;
}

async function loadDashboard(page) {
    const payload = (await call('/admin')).data;
    // The year gaps come from the years endpoint rather than the overview payload, so the overview
    // API stays as it was; a failure here drops the year card and leaves the rest of the panel.
    let years = [];
    try { years = (await call('/admin/tax-years')).data ?? []; } catch { /* omit the year-gap card */ }

    renderAttention(page, {
        yearGaps: years.filter((year) => year.active && !year.published_version),
        draftRules: payload.rule_versions?.draft_count ?? 0,
        draftContent: payload.content?.draft ?? 0,
    });

    // Metric tiles: an icon, the number, and a line saying what the number means — the previous
    // tiles were a small grey word over a large figure with nothing to anchor either.
    page.querySelector('[data-dashboard-counts]').innerHTML = METRICS.map((metric) => `
        <div class="ui-card flex items-start gap-4">
            <span class="ui-icon-tile-soft ${metric.tone}">${icon(metric.icon, 'size-6')}</span>
            <div class="min-w-0">
                <p class="ui-metric-label">${metric.label}</p>
                <p class="mt-1 text-3xl leading-none font-extrabold tabular-nums text-[#0f2c5c]">${payload.content[metric.key] ?? 0}</p>
                <p class="mt-1.5 text-xs text-slate-500">${metric.hint}</p>
            </div>
        </div>`).join('');

    const versions = page.querySelector('[data-dashboard-versions]');
    versions.innerHTML = payload.rule_versions.published.length
        ? payload.rule_versions.published.map((item) => `
            <li class="flex items-center justify-between gap-3 border-b border-slate-100 py-3 last:border-b-0">
                <span class="flex items-center gap-3">
                    <span class="ui-status bg-emerald-100 text-emerald-800">เผยแพร่</span>
                    <span class="font-mono font-semibold text-[#0f2c5c]">${escapeAttribute(item.version)}</span>
                </span>
                <span class="ui-meta">
                    <span>ปีภาษี ${escapeAttribute(String(item.tax_year))}</span>
                    <span>${date(item.published_at)}</span>
                </span>
            </li>`).join('')
        : '<li class="py-6 text-center text-slate-500">ยังไม่มีชุดกฎที่เผยแพร่</li>';

    // The activity feed, written as sentences instead of constants.
    const activity = page.querySelector('[data-dashboard-activity]');
    activity.innerHTML = payload.recent_activity.length
        ? payload.recent_activity.map((item) => {
            const { text, kind } = actionLabel(item.action);
            const tone = ACTION_KINDS[kind] ?? ACTION_KINDS.update;

            // Each entry links into the full audit trail, filtered to its action where the log
            // page offers that action as a filter and unfiltered otherwise — so a glance on the
            // overview can always be opened into the record it summarises.
            const href = `/admin/audit-logs${item.action ? `?action=${encodeURIComponent(item.action)}` : ''}`;

            return `<li class="border-b border-slate-100 last:border-b-0">
                <a href="${href}" class="-mx-2 flex items-start gap-3 rounded-lg px-2 py-3 transition hover:bg-slate-50">
                    <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full ${tone.tone}">
                        ${icon(tone.glyph, 'size-3.5')}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-[#0f2c5c]">
                            ${escapeAttribute(text)}
                            <span class="font-normal text-slate-500">· ${escapeAttribute(entityLabel(item.entity_type))}</span>
                        </p>
                        <p class="ui-meta mt-1">
                            <span>${escapeAttribute(item.actor ?? 'ระบบ')}</span>
                            <span>${auditTimestamp(item.created_at)}</span>
                        </p>
                    </div>
                    <span class="mt-1 shrink-0 text-slate-300">${icon('chevron-right', 'size-4')}</span>
                </a>
            </li>`;
        }).join('')
        : '<li class="py-6 text-center text-slate-500">ยังไม่มีกิจกรรม</li>';

    reveal(page);
}

/**
 * Opens a draft the way a reader will see it.
 *
 * The link has to be fetched rather than built: the console authenticates with a bearer token and
 * a plain `<a href>` carries no headers, so the API mints a short-lived signed URL instead and the
 * signature is what authorises the page.
 *
 * `window.open` is called *before* the await in the caller would otherwise delay it, because a
 * popup opened after an async gap is blocked by every browser. The tab is opened empty and then
 * pointed at the URL once it arrives.
 */
async function openPreview(id) {
    const tab = window.open('', '_blank');
    try {
        const result = await call(`/admin/content/${id}/preview-url`);
        if (tab) tab.location.href = result.data.url;
        else say('เบราว์เซอร์บล็อกการเปิดแท็บใหม่ กรุณาอนุญาตป๊อปอัปสำหรับเว็บไซต์นี้');
    } catch (error) {
        tab?.close();
        say(error.message);
    }
}

async function runContentAction(id, action) {
    try {
        await call(`/admin/content/${id}/${action}`, { method: 'POST', body: {} });
        window.location.reload();
    } catch (error) { say(error.message); }
}

/*
 * A row overflow menu. One trigger, "จัดการ", stands in for the workflow actions that used to be
 * five or six buttons crowding every row. The menu is position:fixed and appended to <body>, placed
 * from the trigger's rectangle, because the tables live inside a horizontal-scroll container whose
 * overflow:auto clips a normally-positioned dropdown. One menu is open at a time; an outside click,
 * Escape, scroll or resize, or choosing an item closes it.
 */
let closeOpenMenu = () => {};

function overflowMenu(actions) {
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ui-button-quiet';
    trigger.setAttribute('aria-haspopup', 'menu');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = `<span>จัดการ</span>${icon('arrow-down', 'size-3.5 shrink-0')}`;

    const menu = document.createElement('div');
    menu.className = 'ui-menu';
    menu.setAttribute('role', 'menu');
    menu.hidden = true;

    const close = () => {
        if (menu.hidden) return;
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        document.removeEventListener('keydown', onKey, true);
        window.removeEventListener('scroll', close, true);
        window.removeEventListener('resize', close);
        document.removeEventListener('pointerdown', onOutside, true);
        menu.remove();
        closeOpenMenu = () => {};
    };
    const onKey = (event) => { if (event.key === 'Escape') { close(); trigger.focus(); } };
    const onOutside = (event) => { if (!menu.contains(event.target) && !trigger.contains(event.target)) close(); };

    actions.filter(Boolean).forEach((action) => {
        const item = document.createElement(action.href ? 'a' : 'button');
        item.className = `ui-menu-item${action.danger ? ' ui-menu-item-danger' : ''}`;
        item.setAttribute('role', 'menuitem');
        if (!action.href) item.type = 'button';
        item.innerHTML = `${icon(action.icon ?? 'chevron-right', 'size-4 shrink-0')}<span></span>`;
        item.querySelector('span').textContent = action.label;
        if (action.href) {
            item.href = action.href;
            if (action.target) { item.target = action.target; item.rel = 'noopener'; }
        }
        item.addEventListener('click', (event) => {
            if (!action.href) event.preventDefault();
            close();
            action.onSelect?.();
        });
        menu.append(item);
    });

    trigger.addEventListener('click', () => {
        if (!menu.hidden) { close(); return; }
        closeOpenMenu();
        document.body.append(menu);
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        const rect = trigger.getBoundingClientRect();
        menu.style.top = `${rect.bottom + 4}px`;
        menu.style.left = `${Math.max(8, rect.right - menu.offsetWidth)}px`;
        // Registered after this click finishes, so the opening click does not immediately close it.
        setTimeout(() => document.addEventListener('pointerdown', onOutside, true), 0);
        document.addEventListener('keydown', onKey, true);
        window.addEventListener('scroll', close, true);
        window.addEventListener('resize', close);
        closeOpenMenu = close;
    });

    return trigger;
}

function appendContentRow(item, rows) {
    const row = document.createElement('tr');
    const title = document.createElement('td'); title.className = 'px-3 py-2.5';
    title.append(textNode('span', item.title, 'block font-semibold text-[#0f2c5c]'), textNode('span', item.slug, 'block font-mono text-xs text-slate-500'));
    const status = document.createElement('td'); status.className = 'px-3 py-2.5';
    status.append(statusPill(item.status, item.status_label));
    // Labels come from the API, which reads them from `ContentPost::TYPE_LABELS` — the browser
    // keeps no copy of the vocabulary and so cannot disagree with the server-rendered pages.
    row.append(title, textNode('td', item.type_label, 'px-3 py-2.5'), status, textNode('td', item.category?.name, 'px-3 py-2.5'), textNode('td', date(item.published_at), 'px-3 py-2.5'));
    const actions = document.createElement('td'); actions.className = 'px-3 py-2.5 text-right whitespace-nowrap';

    // "แก้ไข" stays a visible primary action; everything else collapses behind one menu so a row is
    // one control plus a menu rather than the six buttons it used to carry.
    const edit = textNode('a', 'แก้ไข', 'ui-button-quiet');
    edit.href = `/admin/content/${item.id}/edit`;

    const published = item.status === 'published';
    const menu = overflowMenu([
        // Preview always; the live page only once there is one to look at.
        { label: 'ดูตัวอย่าง', icon: 'eye', onSelect: () => openPreview(item.id) },
        published ? { label: 'ดูหน้าจริง', icon: 'arrow-right', href: `/article/${item.slug}`, target: '_blank' } : null,
        { label: published ? 'ยกเลิกเผยแพร่' : 'เผยแพร่', icon: published ? 'ban' : 'check',
            onSelect: () => runContentAction(item.id, published ? 'unpublish' : 'publish') },
        { label: 'จัดเก็บ', icon: 'archive', onSelect: () => runContentAction(item.id, 'archive') },
        // Deleting content is a soft delete, but it removes the post from every listing, so it
        // confirms first and stays visually distinct as the one destructive item.
        { label: 'ลบ', icon: 'trash', danger: true, onSelect: async () => {
            if (!await confirmAction(`ลบเนื้อหา "${item.title}"? เนื้อหาจะหายจากทุกรายการ`)) return;
            try { await call(`/admin/content/${item.id}`, { method: 'DELETE' }); window.location.reload(); }
            catch (error) { say(error.message); }
        } },
    ]);

    const group = document.createElement('div');
    group.className = 'inline-flex items-center gap-1';
    group.append(edit, menu);
    actions.append(group);
    row.append(actions); rows.append(row);
}

async function loadContent(page) {
    const form = page.querySelector('[data-content-filters]');
    const rows = page.querySelector('[data-content-rows]');

    const render = async (pageNumber = 1) => {
        const params = new URLSearchParams([...new FormData(form)].filter(([, value]) => String(value).trim()));
        const filtered = [...params.keys()].length > 0;
        params.set('page', String(pageNumber));
        params.set('per_page', '20');
        const payload = await call(`/admin/content?${params}`);
        const items = payload.data ?? [];

        rows.replaceChildren();
        items.forEach((item) => appendContentRow(item, rows));
        if (!items.length) {
            emptyRow(rows, 6, filtered
                ? 'ไม่พบเนื้อหาตามเงื่อนไขนี้'
                : 'ยังไม่มีเนื้อหา — กด "สร้างเนื้อหาใหม่" เพื่อเริ่มร่างชิ้นแรก');
        }
        closeOpenMenu();

        const meta = payload.meta ?? {};
        page.querySelector('[data-content-summary]').textContent =
            meta.total !== undefined ? `พบ ${meta.total} รายการ` : '';
        page.querySelector('[data-content-page]').textContent =
            meta.total !== undefined ? `หน้า ${meta.current_page} จาก ${meta.last_page}` : '';
        const previous = page.querySelector('[data-content-previous]');
        const next = page.querySelector('[data-content-next]');
        previous.disabled = (meta.current_page ?? 1) <= 1;
        next.disabled = (meta.current_page ?? 1) >= (meta.last_page ?? 1);
        previous.onclick = () => render((meta.current_page ?? 1) - 1).catch((error) => say(error.message));
        next.onclick = () => render((meta.current_page ?? 1) + 1).catch((error) => say(error.message));
    };

    form.addEventListener('submit', (event) => { event.preventDefault(); render(1).catch((error) => say(error.message)); });
    await render();
    reveal(page);
}

async function loadContentForm(page) {
    const form = page.querySelector('[data-content-form]');
    const contentId = page.dataset.contentId;
    const [categories, tags, years, post] = await Promise.all([
        call('/admin/content/categories'), call('/admin/content/tags'), call('/admin/tax-years'),
        contentId ? call(`/admin/content/${contentId}`) : Promise.resolve(null),
    ]);
    categories.data.forEach((item) => { const option = textNode('option', item.name); option.value = item.id; form.elements.category_id.append(option); });
    // Phase 3 — the tax-year picker the year filter on the public listings needs someone to fill.
    years.data.forEach((item) => { const option = textNode('option', String(item.year)); option.value = item.id; form.elements.tax_year_id.append(option); });
    tags.data.forEach((item) => { const label = textNode('label', '', 'flex items-center gap-2 text-sm'); const input = document.createElement('input'); input.type = 'checkbox'; input.name = 'tag_ids'; input.value = item.id; label.append(input, document.createTextNode(item.name)); page.querySelector('[data-content-tags]').append(label); });
    if (post) {
        const item = post.data;
        ['title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description', 'source_name', 'source_url'].forEach((name) => { form.elements[name].value = item[name] ?? ''; });
        /*
         * Phase 3 — `type` needs its own line because the API emits it upper-cased for display
         * while the options, and `ContentPost::TYPES`, are lower case. Assigning 'GUIDE' to a
         * select whose options are 'guide' selects nothing, so the control went blank on every
         * edit and the save then failed validation on a field nobody had touched.
         */
        form.elements.type.value = String(item.type ?? '').toLowerCase();
        form.elements.sort_order.value = item.sort_order ?? '';
        form.elements.tax_year_id.value = item.tax_year_id ?? '';
        form.elements.category_id.value = item.category?.id ?? '';
        form.elements.featured.checked = item.featured;
        form.elements.slug.disabled = item.slug_locked;
        page.querySelector('[data-slug-help]').classList.toggle('hidden', !item.slug_locked);
        item.tags.forEach((tag) => { const input = form.querySelector(`[name="tag_ids"][value="${tag.id}"]`); if (input) input.checked = true; });
        page.querySelectorAll('[data-existing-action]').forEach((button) => { button.classList.remove('hidden'); button.dataset.contentAction = button.dataset.existingAction; button.dataset.contentId = contentId; });
        const previewButton = page.querySelector('[data-content-preview]');
        previewButton.classList.remove('hidden');
        previewButton.addEventListener('click', () => openPreview(contentId));
        initContentActions(page);
    }
    form.classList.remove('hidden'); page.querySelector('[data-admin-loading]').classList.add('hidden'); page.setAttribute('aria-busy', 'false');
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); const data = new FormData(form);
        const body = { type: data.get('type'), title: data.get('title'), body: data.get('body'), excerpt: data.get('excerpt') || null, meta_title: data.get('meta_title') || null, meta_description: data.get('meta_description') || null, featured: data.get('featured') === 'on', tag_ids: data.getAll('tag_ids').map(Number), category_id: data.get('category_id') ? Number(data.get('category_id')) : null, tax_year_id: data.get('tax_year_id') ? Number(data.get('tax_year_id')) : null, sort_order: Number(data.get('sort_order') || 0), source_name: data.get('source_name') || null, source_url: data.get('source_url') || null };
        if (data.get('slug')) body.slug = data.get('slug');
        try { const result = await call(contentId ? `/admin/content/${contentId}` : '/admin/content', { method: contentId ? 'PATCH' : 'POST', body }); if (!contentId) window.location.href = `/admin/content/${result.data.id}/edit`; else say('บันทึกแล้ว', true); } catch (error) { say(error.message); }
    });
}

async function loadRuleVersions(page) {
    const items = (await call('/admin/tax-rule-versions')).data; const rows = page.querySelector('[data-version-rows]');
    const versionLabels = { published: 'เผยแพร่แล้ว', draft: 'ฉบับร่าง', archived: 'จัดเก็บแล้ว' };
    items.forEach((item) => {
        const row = document.createElement('tr');
        const status = document.createElement('td'); status.className = 'px-3 py-2.5';
        status.append(statusPill(item.status, versionLabels[item.status] ?? item.status));
        row.append(textNode('td', item.version, 'px-3 py-2.5 font-mono font-semibold text-[#0f2c5c]'), textNode('td', item.tax_year, 'px-3 py-2.5'), status, textNode('td', date(item.published_at), 'px-3 py-2.5'));
        const actions = document.createElement('td'); actions.className = 'px-3 py-2.5 text-right whitespace-nowrap';
        const link = textNode('a', 'รายละเอียด', 'ui-button-quiet'); link.href = `/admin/tax-rule-versions/${item.id}`; actions.append(link);
        addActionButton(actions, 'ทำสำเนาเป็นฉบับร่าง', 'version:clone', item.id, 'ui-button-quiet');
        actions.querySelector('[data-version-action="clone"]:last-of-type').dataset.versionYear = item.tax_year;
        row.append(actions); rows.append(row);
    });
    if (!items.length) emptyRow(rows, 5, 'ยังไม่มีชุดกฎภาษีในระบบ');
    initVersionActions(rows); reveal(page);
}

async function loadRuleVersion(page) {
    const id = page.dataset.versionId; const item = (await call(`/admin/tax-rule-versions/${id}`)).data;
    page.querySelector('[data-version-title]').textContent = `ชุดกฎ ${item.version}`; page.querySelector('[data-version-meta]').textContent = `ปีภาษี ${item.tax_year} · สถานะ ${item.status}`; page.querySelector('[data-version-lock]').classList.toggle('hidden', item.status === 'draft');
    Object.entries(item.counts ?? {}).forEach(([name, count]) => { const row = document.createElement('div'); row.className = 'flex justify-between gap-3 border-b border-slate-100 py-1'; row.append(textNode('dt', name, 'text-slate-600'), textNode('dd', count, 'font-semibold')); page.querySelector('[data-version-counts]').append(row); });
    page.querySelector('[data-version-sources]').textContent = `การอ้างอิงเอกสารต้นทาง: ${item.source_coverage?.citations ?? 0} รายการ จาก ${item.source_coverage?.distinct_sources ?? 0} เอกสาร`; page.querySelector('[data-version-history]').classList.toggle('hidden', !item.referenced_by_history);
    const validation = page.querySelector('[data-version-validation]'); validation.textContent = item.validation?.valid === null ? 'ตรวจสอบเฉพาะฉบับร่าง' : item.validation?.valid ? 'ผ่านการตรวจสอบโครงสร้าง' : `พบข้อผิดพลาด ${item.validation?.errors?.length ?? 0} รายการ`;
    const actions = page.querySelector('[data-version-actions]');
    addActionButton(actions, 'ตรวจสอบความถูกต้อง', 'version:validate', id, 'ui-button-secondary');
    if (item.status === 'draft') {
        // A draft is the only thing that can be described, published or discarded.
        addActionButton(actions, 'แก้คำอธิบาย', 'version:rename', id, 'ui-button-secondary');
        addActionButton(actions, 'จัดเก็บฉบับร่าง', 'version:archive', id, 'ui-button-secondary');
        addActionButton(actions, 'เผยแพร่ฉบับร่างนี้', 'version:publish', id, 'ui-button-primary');
        actions.querySelector('[data-version-action="rename"]').dataset.versionDescription = item.description ?? '';
    } else {
        addActionButton(actions, 'ทำสำเนาเป็นฉบับร่าง', 'version:clone', id, 'ui-button-primary');
        actions.querySelector('[data-version-action="clone"]').dataset.versionYear = item.tax_year;
    }

    // The rule tabs. The first is opened immediately so the page never lands on an empty panel.
    const tabs = page.querySelector('[data-rule-resources]');
    RULE_RESOURCES.forEach((resource, index) => {
        const button = textNode('button', RESOURCE_LABELS[resource] ?? resource, 'ui-chip');
        button.type = 'button';
        button.addEventListener('click', () => {
            tabs.querySelectorAll('.ui-chip').forEach((chip) => chip.classList.remove('ui-chip-active'));
            button.classList.add('ui-chip-active');
            loadRuleTable(id, resource, page);
        });
        tabs.append(button);
        if (index === 0) queueMicrotask(() => button.click());
    });

    await ruleEditor.loadReferences();
    initVersionActions(actions);
    reveal(page);
}

const ruleEditor = createRuleEditor({ call, say });

async function loadRuleTable(id, resource, page) {
    const target = page.querySelector('[data-rule-table]');
    try {
        await ruleEditor.render(id, resource, target);
    } catch (error) {
        target.replaceChildren();
        say(error.message);
    }
}

/**
 * M9.1 — category and tag administration.
 *
 * The delete control names the outcome the API will actually produce: a label still in use is
 * deactivated so published content keeps its category, and only an unused one is removed. The
 * count that decides this is shown in the row, so the two different confirmations are never a
 * surprise.
 */
async function loadTaxonomy(page) {
    const kinds = { category: { path: 'categories', label: 'หมวดหมู่' }, tag: { path: 'tags', label: 'แท็ก' } };

    const render = async (kind) => {
        const { path, label } = kinds[kind];
        const items = (await call(`/admin/content/${path}`)).data;
        const tbody = page.querySelector(`[data-taxonomy-rows="${kind}"]`);
        tbody.replaceChildren();
        if (!items.length) {
            emptyRow(tbody, 5, `ยังไม่มี${label} เพิ่มรายการแรกได้จากแบบฟอร์มด้านบน`);
            return;
        }
        items.forEach((item) => {
            const inUse = Number(item.content_count ?? 0);
            const row = document.createElement('tr');
            const name = document.createElement('td');
            name.className = 'px-3 py-2.5';
            name.append(textNode('span', item.name, 'block font-semibold text-[#0f2c5c]'));
            const slug = document.createElement('td');
            slug.className = 'px-3 py-2.5';
            slug.append(textNode('span', item.slug, 'font-mono text-xs text-slate-500'));
            const usage = textNode('td', `${inUse} รายการ`, 'px-3 py-2.5');
            const status = document.createElement('td');
            status.className = 'px-3 py-2.5';
            status.append(statusPill(item.active ? 'active' : 'inactive', item.active ? 'ใช้งาน' : 'ปิดใช้งาน'));

            const actions = document.createElement('td');
            actions.className = 'px-3 py-2.5 text-right';
            const rename = actionButton('แก้ชื่อ', 'edit');
            rename.addEventListener('click', async () => {
                const name = await promptValue(`ชื่อใหม่ของ${label}`, item.name);
                if (!name) return;
                try {
                    await call(`/admin/content/${path}/${item.id}`, { method: 'PATCH', body: { name, slug: item.slug } });
                    say(`เปลี่ยนชื่อ${label}แล้ว`, true);
                    await render(kind);
                } catch (error) { say(error.message); }
            });
            // The label already tells the truth about which of the two this is; the icon follows
            // it rather than blurring the distinction the taxonomy page was built to make.
            const remove = inUse > 0
                ? actionButton('ปิดใช้งาน', 'deactivate', 'ui-button-danger-quiet')
                : actionButton('ลบ', 'delete', 'ui-button-danger-quiet');
            remove.addEventListener('click', async () => {
                const warning = inUse > 0
                    ? `${label} "${item.name}" ถูกใช้โดยเนื้อหา ${inUse} รายการ จึงจะถูกปิดใช้งานแทนการลบ ดำเนินการต่อ?`
                    : `ลบ${label} "${item.name}" ออกถาวร?`;
                if (!await confirmAction(warning)) return;
                try {
                    await call(`/admin/content/${path}/${item.id}`, { method: 'DELETE' });
                    say(inUse > 0 ? `ปิดใช้งาน${label}แล้ว` : `ลบ${label}แล้ว`, true);
                    await render(kind);
                } catch (error) { say(error.message); }
            });
            actions.append(rename, remove);

            row.append(name, slug, usage, status, actions);
            tbody.append(row);
        });
    };

    Object.keys(kinds).forEach((kind) => {
        page.querySelector(`[data-taxonomy-form="${kind}"]`).addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.target;
            const data = new FormData(form);
            try {
                await call(`/admin/content/${kinds[kind].path}`, {
                    method: 'POST',
                    body: { name: data.get('name'), slug: data.get('slug') },
                });
                say(`เพิ่ม${kinds[kind].label}แล้ว`, true);
                form.reset();
                await render(kind);
            } catch (error) { say(error.message); }
        });
    });

    await Promise.all(Object.keys(kinds).map(render));
    reveal(page);
}

/**
 * M9.1 — the approved source-document registry, now writable.
 *
 * The registry was read-only, so a document could only be added by editing a seeder. Registering
 * one is an ordinary administrative act and belongs here.
 *
 * Deletion is the part worth stating plainly: a source cited by a published rule version cannot
 * be removed at all — the API refuses, because a published rule must keep pointing at the
 * document it was read from. The row shows its citation count for that reason, and the control
 * says which of the two outcomes will happen.
 */
async function loadTaxSources(page) {
    const render = async () => {
        const items = (await call('/admin/tax-sources')).data;
        const rows = page.querySelector('[data-source-rows]');
        rows.replaceChildren();
        items.forEach((item) => {
            const row = document.createElement('tr');
            row.append(
                textNode('td', item.code, 'px-3 py-2.5 font-mono text-xs text-slate-500'),
                textNode('td', item.title, 'px-3 py-2.5 font-semibold text-[#0f2c5c]'),
                textNode('td', item.source_type, 'px-3 py-2.5'),
                textNode('td', item.file_path, 'px-3 py-2.5 font-mono text-xs text-slate-500'),
                textNode('td', `${item.citation_count ?? 0} รายการ`, 'px-3 py-2.5'),
            );
            const status = document.createElement('td');
            status.className = 'px-3 py-2.5';
            status.append(statusPill(item.active ? 'active' : 'inactive', item.active ? 'ใช้งาน' : 'ปิดใช้งาน'));
            row.append(status);

            const actions = document.createElement('td');
            actions.className = 'px-3 py-2.5 text-right whitespace-nowrap';
            const edit = actionButton('แก้ไข', 'edit');
            edit.addEventListener('click', () => fillForm(item));
            // A source is never removed from the registry — the API deactivates it, and refuses
            // entirely while a published rule cites it, so that a published rule always keeps
            // pointing at the document it was read from. The control says exactly that.
            const remove = actionButton('ปิดใช้งาน', 'deactivate', 'ui-button-danger-quiet');
            remove.disabled = !item.active;
            remove.addEventListener('click', async () => {
                const warning = item.citation_count
                    ? `เอกสาร "${item.code}" ถูกอ้างอิงโดยกฎ ${item.citation_count} รายการ `
                      + 'หากเป็นกฎที่เผยแพร่แล้ว ระบบจะปฏิเสธ ดำเนินการต่อ?'
                    : `ปิดใช้งานเอกสาร "${item.code}"? เอกสารจะยังอยู่ในทะเบียนแต่จะไม่ถูกเลือกใช้`;
                if (!await confirmAction(warning)) return;
                try {
                    await call(`/admin/tax-sources/${item.id}`, { method: 'DELETE' });
                    say('ปิดใช้งานเอกสารอ้างอิงแล้ว', true);
                    await render();
                } catch (error) { say(error.message); }
            });
            actions.append(edit, remove);
            row.append(actions);
            rows.append(row);
        });
        if (!items.length) emptyRow(rows, 7, 'ยังไม่มีเอกสารอ้างอิงในทะเบียน — เพิ่มรายการแรกได้จากแบบฟอร์มด้านบน');
    };

    const form = page.querySelector('[data-source-form]');
    const fillForm = (item) => {
        form.dataset.sourceId = item ? String(item.id) : '';
        ['code', 'title', 'source_type', 'file_path', 'description', 'document_date'].forEach((name) => {
            if (form.elements[name]) form.elements[name].value = item?.[name] ?? '';
        });
        form.elements.active.checked = item ? Boolean(item.active) : true;
        page.querySelector('[data-source-form-title]').textContent = item ? `แก้ไขเอกสาร ${item.code}` : 'เพิ่มเอกสารอ้างอิง';
        page.querySelector('[data-source-cancel]').classList.toggle('hidden', !item);
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    page.querySelector('[data-source-cancel]').addEventListener('click', () => fillForm(null));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const id = form.dataset.sourceId;
        const data = new FormData(form);
        const body = {
            code: data.get('code'), title: data.get('title'), source_type: data.get('source_type'),
            file_path: data.get('file_path') || null, description: data.get('description') || null,
            document_date: data.get('document_date') || null, active: data.get('active') === 'on',
        };
        try {
            await call(id ? `/admin/tax-sources/${id}` : '/admin/tax-sources', { method: id ? 'PATCH' : 'POST', body });
            say(id ? 'แก้ไขเอกสารอ้างอิงแล้ว' : 'เพิ่มเอกสารอ้างอิงแล้ว', true);
            form.reset();
            fillForm(null);
            await render();
        } catch (error) { say(error.message); }
    });

    await render();
    reveal(page);
}

/**
 * M9.1 — tax year administration.
 *
 * The console could edit rules but not open the year those rules belong to, so the one workflow
 * it exists for — next year's rates changed — ended at a seeder. The two facts the interface has
 * to convey are that a year without forms cannot start a simulation, and that retiring a year
 * hides it from new simulations without touching anything already calculated under it.
 */
async function loadTaxYears(page) {
    const form = page.querySelector('[data-year-form]');
    const rows = page.querySelector('[data-year-rows]');

    const render = async () => {
        const items = (await call('/admin/tax-years')).data;

        // The "copy forms from" picker only ever offers a year that actually has forms.
        const template = form.elements.copy_forms_from_year;
        const chosen = template.value;
        template.replaceChildren(textNode('option', '— ไม่คัดลอก —'));
        items.filter((item) => item.form_count > 0).forEach((item) => {
            const option = textNode('option', `${item.year} (${item.form_count} แบบ)`);
            option.value = String(item.year);
            template.append(option);
        });
        template.value = chosen;

        rows.replaceChildren();
        if (!items.length) {
            emptyRow(rows, 8, 'ยังไม่มีปีภาษีในระบบ');

            return;
        }

        items.forEach((item) => {
            const row = document.createElement('tr');
            row.append(
                textNode('td', item.year, 'px-3 py-2.5 font-semibold text-[#0f2c5c]'),
                textNode('td', item.name, 'px-3 py-2.5'),
                textNode('td', `${item.form_count} แบบ`, `px-3 py-2.5 ${item.form_count ? '' : 'text-rose-600 font-semibold'}`),
                textNode('td', `${item.rule_version_count} ชุด`, 'px-3 py-2.5'),
                textNode('td', item.published_version ?? '—', 'px-3 py-2.5 font-mono text-xs'),
                textNode('td', `${item.tax_return_count} รายการ`, 'px-3 py-2.5'),
            );
            const status = document.createElement('td');
            status.className = 'px-3 py-2.5';
            status.append(statusPill(item.active ? 'active' : 'inactive', item.active ? 'เปิดใช้งาน' : 'ปิดใช้งาน'));
            row.append(status);

            const actions = document.createElement('td');
            actions.className = 'px-3 py-2.5 text-right whitespace-nowrap';
            const edit = actionButton('แก้ไข', 'edit');
            edit.addEventListener('click', () => fillForm(item));
            actions.append(edit);

            if (item.active) {
                const retire = actionButton('ปิดใช้งาน', 'deactivate', 'ui-button-danger-quiet');
                retire.addEventListener('click', async () => {
                    const warning = item.tax_return_count
                        ? `ปิดใช้งานปีภาษี ${item.year}? จะไม่ถูกเสนอให้เลือกสำหรับแบบจำลองใหม่ `
                          + `ส่วนแบบจำลอง ${item.tax_return_count} รายการที่ใช้ปีนี้อยู่และผลที่คำนวณไว้แล้วจะไม่เปลี่ยนแปลง`
                        : `ปิดใช้งานปีภาษี ${item.year}? จะไม่ถูกเสนอให้เลือกสำหรับแบบจำลองใหม่`;
                    if (!await confirmAction(warning)) return;
                    try {
                        await call(`/admin/tax-years/${item.id}`, { method: 'DELETE' });
                        say(`ปิดใช้งานปีภาษี ${item.year} แล้ว`, true);
                        await render();
                    } catch (error) { say(error.message); }
                });
                actions.append(retire);
            }
            row.append(actions);
            rows.append(row);
        });
    };

    const fillForm = (item) => {
        form.dataset.yearId = item ? String(item.id) : '';
        form.elements.year.value = item?.year ?? '';
        // The year itself is fixed once anything points at it.
        form.elements.year.disabled = Boolean(item);
        form.elements.name.value = item?.name ?? '';
        form.elements.active.checked = item ? Boolean(item.active) : true;
        form.elements.filing_start_date.value = item?.filing_start_date ?? '';
        form.elements.filing_end_date.value = item?.filing_end_date ?? '';
        form.elements.copy_forms_from_year.closest('label').hidden = Boolean(item);
        page.querySelector('[data-year-form-title]').textContent = item ? `แก้ไขปีภาษี ${item.year}` : 'เปิดปีภาษีใหม่';
        page.querySelector('[data-year-cancel]').classList.toggle('hidden', !item);
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    page.querySelector('[data-year-cancel]').addEventListener('click', () => fillForm(null));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const id = form.dataset.yearId;
        const data = new FormData(form);
        const body = {
            name: data.get('name') || null,
            active: data.get('active') === 'on',
            filing_start_date: data.get('filing_start_date') || null,
            filing_end_date: data.get('filing_end_date') || null,
        };
        if (!id) {
            body.year = Number(data.get('year'));
            if (data.get('copy_forms_from_year')) body.copy_forms_from_year = Number(data.get('copy_forms_from_year'));
        }
        try {
            await call(id ? `/admin/tax-years/${id}` : '/admin/tax-years', { method: id ? 'PATCH' : 'POST', body });
            say(id ? 'แก้ไขปีภาษีแล้ว' : 'เปิดปีภาษีใหม่แล้ว', true);
            form.reset();
            fillForm(null);
            await render();
        } catch (error) { say(error.message); }
    });

    await render();
    fillForm(null);
    reveal(page);
}

/**
 * M9.1 — the audit trail as a page of its own.
 *
 * `admin_audit_logs` has recorded every administrative change since M8, but the console showed
 * only the last few entries on the overview, which is enough to notice activity and not enough
 * to answer a question about it. This is the same record with the filters the API already
 * supports.
 */
async function loadAuditLogs(page) {
    const form = page.querySelector('[data-audit-filter]');
    const rows = page.querySelector('[data-audit-rows]');

    /*
     * When the accounts page links in with `?actor_user_id=`, say whose trail this is.
     *
     * The id alone would leave the reader looking at a filtered list with no idea who filtered it,
     * so the name is fetched from the account the filter names — and a way back to everything sits
     * next to it, because a filter you cannot see is a filter you cannot clear.
     */
    const actorId = form.elements.actor_user_id.value;
    if (actorId) {
        const banner = page.querySelector('[data-audit-actor]');
        banner.classList.remove('hidden');
        try {
            const match = (await call(`/admin/users?per_page=100`)).data.find((user) => String(user.id) === actorId);
            page.querySelector('[data-audit-actor-name]').textContent = match ? match.name : `#${actorId}`;
        } catch { page.querySelector('[data-audit-actor-name]').textContent = `#${actorId}`; }
    }

    const render = async (pageNumber = 1) => {
        const data = new FormData(form);
        const params = new URLSearchParams([...data].filter(([, value]) => value));
        params.set('page', String(pageNumber));
        params.set('per_page', '50');
        const payload = await call(`/admin/audit-logs?${params}`);
        rows.replaceChildren();
        rows.innerHTML = (payload.data ?? []).map((item) => {
            const { text, kind } = actionLabel(item.action);
            const tone = ACTION_KINDS[kind] ?? ACTION_KINDS.update;

            return `<tr>
                <td class="px-3 py-2.5 whitespace-nowrap text-slate-500">${auditTimestamp(item.created_at)}</td>
                <td class="px-3 py-2.5">
                    <span class="flex items-center gap-2">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full ${tone.tone}">
                            ${icon(tone.glyph, 'size-3')}
                        </span>
                        <span class="font-semibold text-[#0f2c5c]">${escapeAttribute(text)}</span>
                    </span>
                </td>
                <td class="px-3 py-2.5">${escapeAttribute(entityLabel(item.entity_type))}</td>
                <td class="px-3 py-2.5 text-slate-700">${escapeAttribute(item.summary)}</td>
                <td class="px-3 py-2.5 whitespace-nowrap">${escapeAttribute(item.actor ?? 'ระบบ')}</td>
            </tr>`;
        }).join('');
        if (!(payload.data ?? []).length) emptyRow(rows, 5, 'ไม่พบบันทึกตามเงื่อนไขนี้');

        const meta = payload.meta ?? {};
        page.querySelector('[data-audit-summary]').textContent =
            meta.total !== undefined ? `พบ ${meta.total} รายการ · หน้า ${meta.current_page} จาก ${meta.last_page}` : '';
        const prev = page.querySelector('[data-audit-prev]');
        const next = page.querySelector('[data-audit-next]');
        prev.disabled = (meta.current_page ?? 1) <= 1;
        next.disabled = (meta.current_page ?? 1) >= (meta.last_page ?? 1);
        prev.onclick = () => render((meta.current_page ?? 1) - 1);
        next.onclick = () => render((meta.current_page ?? 1) + 1);
    };

    form.addEventListener('submit', (event) => { event.preventDefault(); render(1).catch((error) => say(error.message)); });
    await render();
    reveal(page);
}

/**
 * Phase 2 — account administration.
 *
 * Two actions on someone else's account, and both are consequential enough to confirm first:
 * granting administrator rights hands over the whole console, and revoking sessions signs
 * somebody out mid-task. Neither is offered on the administrator's own row — `can_change_role`
 * and `can_revoke_sessions` come from `UserPolicy`, which is also what actually decides, so a
 * button that appears here has already been judged by the same rule that will judge the request.
 */
async function loadUsers(page) {
    const form = page.querySelector('[data-user-filters]');
    const rows = page.querySelector('[data-user-rows]');

    const render = async (pageNumber = 1) => {
        const params = new URLSearchParams([...new FormData(form)].filter(([, value]) => String(value).trim()));
        params.set('page', String(pageNumber));
        const payload = await call(`/admin/users?${params}`);
        const items = payload.data ?? [];

        rows.replaceChildren();
        items.forEach((item) => {
            const row = document.createElement('tr');
            row.append(textNode('td', item.name, 'px-3 py-2.5 font-semibold text-[#0f2c5c]'));

            const email = document.createElement('td');
            email.className = 'px-3 py-2.5';
            email.append(textNode('span', item.email, 'block'));
            // An unverifiable address is the reason a reset link would never arrive, so it is
            // worth stating next to the address rather than in a column of its own.
            if (!item.email_verified) email.append(textNode('span', 'ยังไม่ยืนยันอีเมล', 'block text-xs text-amber-700'));
            row.append(email);

            const role = document.createElement('td');
            role.className = 'px-3 py-2.5 whitespace-nowrap';
            role.append(statusPill(item.is_admin ? 'published' : 'inactive', item.is_admin ? 'ผู้ดูแลระบบ' : 'สมาชิก'));
            // Suspension is the one state that changes what every other control means, so it sits
            // beside the role rather than in a column of its own that could scroll out of view.
            if (item.suspended) role.append(statusPill('suspended', 'ถูกระงับ'));
            row.append(role);

            row.append(textNode('td', String(item.active_session_count ?? 0), 'px-3 py-2.5 tabular-nums'));
            // Was computed and shipped by the API from the start and displayed nowhere. An admin
            // account unused for months is exactly what an operator looks for on this page.
            row.append(textNode('td', item.last_active_at ? date(item.last_active_at) : 'ยังไม่เคยใช้งาน',
                'px-3 py-2.5 whitespace-nowrap text-slate-500'));
            row.append(textNode('td', date(item.registered_at), 'px-3 py-2.5 whitespace-nowrap text-slate-500'));

            const actions = document.createElement('td');
            actions.className = 'px-3 py-2.5 text-right whitespace-nowrap';

            if (item.can_change_role) {
                const promote = item.is_admin;
                const button = actionButton(promote ? 'ถอดสิทธิ์ผู้ดูแล' : 'ตั้งเป็นผู้ดูแล',
                    promote ? 'demote' : 'promote',
                    promote ? 'ui-button-danger-quiet' : 'ui-button-quiet');
                button.addEventListener('click', async () => {
                    const warning = promote
                        ? `ถอดสิทธิ์ผู้ดูแลระบบของ ${item.name}? บัญชีนี้จะเข้าหน้าผู้ดูแลระบบไม่ได้อีก`
                        : `ตั้ง ${item.name} เป็นผู้ดูแลระบบ? บัญชีนี้จะแก้ไขกฎภาษี เนื้อหา และบัญชีผู้ใช้อื่นได้ทั้งหมด`;
                    if (!await confirmAction(warning)) return;
                    try {
                        await call(`/admin/users/${item.id}/role`, { method: 'PATCH',
                            body: { role: promote ? 'member' : 'admin' } });
                        say(promote ? `ถอดสิทธิ์ผู้ดูแลระบบของ ${item.name} แล้ว` : `ตั้ง ${item.name} เป็นผู้ดูแลระบบแล้ว`, true);
                        await render(pageNumber);
                    } catch (error) { say(error.message); }
                });
                actions.append(button);
            }

            if (item.can_suspend) {
                const suspending = !item.suspended;
                const button = actionButton(suspending ? 'ระงับบัญชี' : 'คืนสิทธิ์',
                    suspending ? 'suspend' : 'restore',
                    suspending ? 'ui-button-danger-quiet' : 'ui-button-quiet');
                button.addEventListener('click', async () => {
                    const warning = suspending
                        ? `ระงับบัญชี ${item.name}? บัญชีนี้จะเข้าสู่ระบบและตั้งรหัสผ่านใหม่ไม่ได้ `
                          + 'อุปกรณ์ที่เข้าสู่ระบบไว้จะถูกออกจากระบบทันที ข้อมูลทั้งหมดยังอยู่ครบและคืนสิทธิ์ได้ภายหลัง'
                        : `คืนสิทธิ์ให้ ${item.name}? บัญชีนี้จะเข้าสู่ระบบด้วยรหัสผ่านเดิมได้ตามปกติ`;
                    if (!await confirmAction(warning)) return;
                    try {
                        const result = await call(`/admin/users/${item.id}/${suspending ? 'suspend' : 'unsuspend'}`,
                            { method: 'POST' });
                        say(result?.message ?? 'ดำเนินการแล้ว', true);
                        await render(pageNumber);
                    } catch (error) { say(error.message); }
                });
                actions.append(button);
            }

            // Only for administrators: the audit trail records administrative changes, so a member
            // row would always link to an empty page.
            if (item.is_admin) {
                const history = textNode('a', 'ประวัติการแก้ไข', 'ui-button-quiet');
                history.href = `/admin/audit-logs?actor_user_id=${item.id}`;
                actions.append(history);
            }

            if (item.can_revoke_sessions && item.active_session_count > 0) {
                const revoke = actionButton('ออกจากระบบทุกอุปกรณ์', 'logout', 'ui-button-danger-quiet');
                revoke.addEventListener('click', async () => {
                    if (!await confirmAction(`ออกจากระบบให้ ${item.name} ทุกอุปกรณ์? `
                        + 'บัญชีนี้จะต้องเข้าสู่ระบบใหม่ รหัสผ่านไม่เปลี่ยน')) return;
                    try {
                        const result = await call(`/admin/users/${item.id}/revoke-sessions`, { method: 'POST' });
                        say(result?.message ?? 'ออกจากระบบให้บัญชีนี้แล้ว', true);
                        await render(pageNumber);
                    } catch (error) { say(error.message); }
                });
                actions.append(revoke);
            }
            row.append(actions);
            rows.append(row);
        });
        if (!items.length) emptyRow(rows, 7, 'ไม่พบบัญชีตามเงื่อนไขนี้');

        const meta = payload.meta ?? {};
        page.querySelector('[data-user-summary]').textContent =
            meta.total !== undefined ? `พบ ${meta.total} บัญชี` : '';
        page.querySelector('[data-user-page]').textContent =
            meta.total !== undefined ? `หน้า ${meta.current_page} จาก ${meta.last_page}` : '';
        const previous = page.querySelector('[data-user-previous]');
        const next = page.querySelector('[data-user-next]');
        previous.disabled = (meta.current_page ?? 1) <= 1;
        next.disabled = (meta.current_page ?? 1) >= (meta.last_page ?? 1);
        previous.onclick = () => render((meta.current_page ?? 1) - 1).catch((error) => say(error.message));
        next.onclick = () => render((meta.current_page ?? 1) + 1).catch((error) => say(error.message));
    };

    form.addEventListener('submit', (event) => { event.preventDefault(); render(1).catch((error) => say(error.message)); });
    await render();
    reveal(page);
}

/**
 * M9.1 — the console greets whoever is signed in, and says plainly why it cannot show anything.
 *
 * Three states, told apart rather than collapsed into one "could not load" line:
 *
 *   signed out            — sign in;
 *   signed in, no rights  — this account is not an administrator, with a way back to the site;
 *   signed in as an admin — the page loads.
 *
 * The API decides all three. This only chooses the wording.
 */
async function initializeProtectedPage() {
    const page = document.querySelector('[data-admin-page]');
    if (!page) return;

    const viewer = await resolveViewer();
    applyRoleVisibility(viewer);

    if (!viewer.authenticated || !viewer.isAdmin) {
        const guard = document.querySelector('[data-admin-guard]');
        const message = page.querySelector('[data-admin-loading]');
        if (guard) {
            guard.hidden = false;
            guard.querySelector('[data-guard-signed-out]').hidden = viewer.authenticated;
            guard.querySelector('[data-guard-not-admin]').hidden = !viewer.authenticated;
        }
        if (message) {
            message.textContent = viewer.authenticated
                ? 'บัญชีนี้ไม่มีสิทธิ์ผู้ดูแลระบบ'
                : 'กรุณาเข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบ';
        }

        return;
    }

    try {
        const loaders = { dashboard: loadDashboard, content: loadContent, 'content-form': loadContentForm, taxonomy: loadTaxonomy, 'rule-versions': loadRuleVersions, 'rule-version': loadRuleVersion, 'tax-sources': loadTaxSources, 'tax-years': loadTaxYears, 'audit-logs': loadAuditLogs, users: loadUsers };
        await loaders[page.dataset.adminPage](page);
    } catch (error) {
        page.querySelector('[data-admin-loading]').textContent = 'ไม่สามารถโหลดข้อมูลผู้ดูแลได้';
        say(error.message);
    }
}

export function initializeAdminConsole() {
    if (!document.querySelector('[data-admin-login], [data-admin-page]')) return;
    initSignIn();
    initSignOut();
    initializeProtectedPage();
}
