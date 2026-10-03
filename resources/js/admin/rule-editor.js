import { RESOURCE_LABELS, RULE_COLUMNS, RULE_FIELDS } from './rule-fields.js';

/**
 * Editing the rule rows of a draft version.
 *
 * Milestone 09.1. The console could already clone a version, validate it and publish it — but not
 * change a single rule inside it, which is the one thing a draft version exists for. An
 * administrator had to reach the API by hand between the clone and the publish.
 *
 * Two safety properties are expressed in the interface rather than left to the API to refuse:
 *
 *   A published or archived version is never editable here. The form and the row controls are
 *   simply absent, because `editable` comes from the API's own answer for that version — not from
 *   a guess in the browser. The API and the model layer still refuse independently.
 *
 *   `source_reference` is a first-class field on every entity that has one, and required where the
 *   registry requires it, because a rule without a stated source is exactly what this project
 *   does not allow.
 *
 * This module builds form controls and sends what was typed. It computes no tax value.
 */

const el = (tag, className = '', text = null) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== null) node.textContent = text;
    return node;
};

/** Renders one field of the rule form, pre-filled from `value`. */
function control(field, value, references) {
    const wrapper = el('label', 'ui-field');
    wrapper.append(document.createTextNode(field.label));
    if (field.required) wrapper.append(el('span', 'ui-field-required', '*'));
    else wrapper.append(el('span', 'ui-field-note', 'ไม่บังคับ'));

    let input;
    if (field.type === 'boolean') {
        input = el('input');
        input.type = 'checkbox';
        input.checked = value === undefined ? true : Boolean(value);
        // A checkbox reads better before its label than under it.
        wrapper.className = 'flex items-center gap-2.5 text-sm font-semibold text-slate-700';
        wrapper.replaceChildren(input, document.createTextNode(` ${field.label}`));
    } else if (field.type === 'select') {
        input = el('select');
        input.append(el('option', '', '— ไม่ระบุ —'));
        field.options.forEach((option) => {
            const node = el('option', '', option);
            node.value = option;
            if (String(value ?? '') === option) node.selected = true;
            input.append(node);
        });
        wrapper.append(input);
    } else if (field.type === 'reference' || field.type === 'reference_many') {
        input = el('select');
        if (field.type === 'reference_many') input.multiple = true;
        else input.append(el('option', '', '— ไม่ระบุ —'));
        const selected = field.type === 'reference_many'
            ? (Array.isArray(value) ? value.map(Number) : [])
            : [Number(value)];
        (references[field.reference] || []).forEach((row) => {
            const node = el('option', '', `${row.code} — ${row.name}`);
            node.value = String(row.id);
            if (selected.includes(row.id)) node.selected = true;
            input.append(node);
        });
        if (field.type === 'reference_many') input.size = Math.min(6, (references[field.reference] || []).length || 2);
        wrapper.append(input);
    } else if (field.type === 'textarea' || field.type === 'json') {
        input = el('textarea');
        input.rows = field.type === 'json' ? 3 : 4;
        if (field.type === 'json') input.className = 'font-mono text-sm';
        input.value = field.type === 'json'
            ? (value === null || value === undefined ? '' : JSON.stringify(value))
            : (value ?? '');
        wrapper.append(input);
    } else {
        input = el('input');
        input.type = 'text';
        if (field.type === 'integer' || field.type === 'decimal') input.inputMode = 'decimal';
        input.value = value === null || value === undefined ? '' : String(value);
        wrapper.append(input);
    }

    input.name = field.name;
    if (field.required && field.type !== 'boolean') input.required = true;
    if (field.help) wrapper.append(el('span', 'ui-help', field.help));

    return wrapper;
}

/** Reads the form back into the payload shape the API expects. */
function readForm(form, fields) {
    const payload = {};
    fields.forEach((field) => {
        const input = form.elements[field.name];
        if (!input) return;
        if (field.type === 'boolean') {
            payload[field.name] = input.checked;
            return;
        }
        if (field.type === 'reference_many') {
            payload[field.name] = [...input.selectedOptions].map((option) => Number(option.value));
            return;
        }
        const raw = String(input.value ?? '').trim();
        if (raw === '') {
            // An optional field left blank is sent as null so a value can be cleared; a required
            // one is left out entirely and the API states what is missing.
            if (!field.required) payload[field.name] = null;
            return;
        }
        if (field.type === 'json') {
            try { payload[field.name] = JSON.parse(raw); } catch { throw new Error(`${field.label}: ต้องเป็น JSON ที่ถูกต้อง`); }
            return;
        }
        payload[field.name] = ['integer', 'decimal', 'reference'].includes(field.type) ? Number(raw) : raw;
    });

    return payload;
}

const cell = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'ใช้งาน' : 'ปิดใช้งาน';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
};

/**
 * Renders the table for one rule resource, with editing when the version is a draft.
 *
 * @param {object} options  call/say helpers supplied by the console, so this module owns no
 *                          transport of its own.
 */
export function createRuleEditor({ call, say, confirm = window.confirm }) {
    let references = { 'income-types': [], 'allowance-types': [] };

    const loadReferences = async () => {
        try {
            const data = (await call('/admin/rule-references')).data;
            references = { 'income-types': data.income_types ?? [], 'allowance-types': data.allowance_types ?? [] };
        } catch {
            // A picker without its list still renders; the API remains the authority on validity.
            references = { 'income-types': [], 'allowance-types': [] };
        }
    };

    const render = async (versionId, resource, target) => {
        const payload = (await call(`/admin/tax-rule-versions/${versionId}/${resource}`)).data;
        const items = payload.items ?? [];
        const editable = Boolean(payload.editable);
        const fields = RULE_FIELDS[resource] ?? [];
        const columns = RULE_COLUMNS[resource] ?? Object.keys(items[0] ?? {}).slice(0, 5);
        const label = RESOURCE_LABELS[resource] ?? resource;

        target.replaceChildren();

        const heading = el('div', 'flex flex-wrap items-center justify-between gap-3');
        heading.append(el('h3', 'ui-form-title', label));
        if (editable) {
            const add = el('button', 'ui-button-primary', `เพิ่ม${label}`);
            add.type = 'button';
            add.addEventListener('click', () => openForm(versionId, resource, null, target));
            heading.append(add);
        } else {
            heading.append(el('p', 'ui-status bg-slate-200 text-slate-700', 'อ่านอย่างเดียว — ชุดกฎนี้ไม่ใช่ฉบับร่าง'));
        }
        target.append(heading);

        const scroll = el('div', 'ui-scroll-x mt-4');
        const table = el('table', 'ui-table min-w-[44rem]');
        const head = table.createTHead().insertRow();
        columns.forEach((column) => {
            const field = fields.find((f) => f.name === column);
            head.append(el('th', '', field?.label ?? column));
        });
        head.append(el('th', 'text-right', ''));

        const body = table.createTBody();
        if (!items.length) {
            const row = body.insertRow();
            const empty = el('td', 'px-3 py-8 text-center text-slate-500', `ยังไม่มี${label}ในชุดกฎนี้`);
            empty.colSpan = columns.length + 1;
            row.append(empty);
        }
        items.forEach((item) => {
            const row = body.insertRow();
            columns.forEach((column) => row.append(el('td', '', cell(item[column]))));
            const actions = el('td', 'px-3 py-2.5 text-right whitespace-nowrap');
            if (editable) {
                const edit = el('button', 'ui-button-quiet', 'แก้ไข');
                edit.type = 'button';
                edit.addEventListener('click', () => openForm(versionId, resource, item, target));
                const remove = el('button', 'ui-button-danger-quiet', 'ลบ');
                remove.type = 'button';
                remove.addEventListener('click', async () => {
                    if (!confirm(`ลบ${label}รายการนี้ออกจากฉบับร่าง?`)) return;
                    try {
                        await call(`/admin/tax-rule-versions/${versionId}/${resource}/${item.id}`, { method: 'DELETE' });
                        say(`ลบ${label}แล้ว`, true);
                        await render(versionId, resource, target);
                    } catch (error) { say(error.message); }
                });
                actions.append(edit, remove);
            }
            row.append(actions);
        });

        scroll.append(table);
        target.append(scroll);
        target.append(el('div', 'mt-4', ''));
    };

    const openForm = (versionId, resource, item, target) => {
        const fields = RULE_FIELDS[resource] ?? [];
        const label = RESOURCE_LABELS[resource] ?? resource;
        const existing = target.querySelector('[data-rule-form]');
        if (existing) existing.remove();

        const form = el('form', 'ui-card mt-4');
        form.dataset.ruleForm = item ? String(item.id) : 'new';
        form.append(el('h4', 'ui-form-title', item ? `แก้ไข${label}` : `เพิ่ม${label}`));

        const grid = el('div', 'mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2');
        fields.forEach((field) => grid.append(control(field, item?.[field.name], references)));
        form.append(grid);

        const actions = el('div', 'mt-5 flex flex-wrap gap-3 border-t border-slate-100 pt-5');
        const submit = el('button', 'ui-button-primary', 'บันทึก');
        submit.type = 'submit';
        const cancel = el('button', 'ui-button-secondary', 'ยกเลิก');
        cancel.type = 'button';
        cancel.addEventListener('click', () => form.remove());
        actions.append(submit, cancel);
        form.append(actions);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            submit.disabled = true;
            try {
                const body = readForm(form, fields);
                await call(
                    item
                        ? `/admin/tax-rule-versions/${versionId}/${resource}/${item.id}`
                        : `/admin/tax-rule-versions/${versionId}/${resource}`,
                    { method: item ? 'PATCH' : 'POST', body },
                );
                say(item ? `แก้ไข${label}แล้ว` : `เพิ่ม${label}แล้ว`, true);
                await render(versionId, resource, target);
            } catch (error) {
                say(error.message);
            } finally {
                submit.disabled = false;
            }
        });

        target.append(form);
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    return { loadReferences, render };
}
