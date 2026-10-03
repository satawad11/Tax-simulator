export const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
export const currency = (value) => `${new Intl.NumberFormat('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0))} บาท`;
export const thaiDate = (value) => value ? new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
export const showAlert = (element, message, tone = 'info') => {
    if (!element) return;
    element.textContent = message;
    element.classList.remove('hidden', 'border-red-200', 'bg-red-50', 'text-red-800');
    if (tone === 'error') element.classList.add('border-red-200', 'bg-red-50', 'text-red-800');
};
export const hideAlert = (element) => element?.classList.add('hidden');

export function promptValue(title, initialValue = '') {
    const dialog = document.querySelector('[data-prompt-dialog]');
    const input = dialog.querySelector('[data-prompt-input]');
    dialog.querySelector('[data-prompt-title]').textContent = title;
    dialog.querySelector('[data-prompt-field]').classList.remove('hidden');
    input.value = initialValue;
    dialog.showModal();
    input.focus();
    return new Promise((resolve) => dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm' ? input.value.trim() : null), { once: true }));
}

export function confirmAction(title) {
    const dialog = document.querySelector('[data-prompt-dialog]');
    dialog.querySelector('[data-prompt-title]').textContent = title;
    dialog.querySelector('[data-prompt-field]').classList.add('hidden');
    dialog.showModal();
    return new Promise((resolve) => dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true }));
}

/**
 * A modal form with an arbitrary set of fields, for the cases a single text prompt cannot cover —
 * cloning a rule set needs a year picked from the years that actually exist plus a version name.
 *
 * `fields` is an array of { name, label, type, value?, options?, placeholder?, help? }. A `select`
 * carries `options: [{ value, label }]`; anything else renders an `<input>` of that `type`. The
 * promise resolves to a { name: value } map on confirm, or null on cancel — the same cancel signal
 * the prompt helpers use, so callers branch on it the same way.
 */
export function formDialog(title, fields, { confirmLabel = 'ยืนยัน' } = {}) {
    const dialog = document.querySelector('[data-form-dialog]');
    if (!dialog) return Promise.resolve(null);
    const body = dialog.querySelector('[data-form-dialog-body]');
    dialog.querySelector('[data-form-dialog-title]').textContent = title;
    dialog.querySelector('[data-form-dialog-confirm]').textContent = confirmLabel;
    body.replaceChildren();

    fields.forEach((field) => {
        const label = document.createElement('label');
        label.className = 'ui-field mt-4 first:mt-0';
        label.append(document.createTextNode(field.label));

        let control;
        if (field.type === 'select') {
            control = document.createElement('select');
            (field.options ?? []).forEach((option) => {
                const node = document.createElement('option');
                node.value = option.value;
                node.textContent = option.label;
                if (String(option.value) === String(field.value ?? '')) node.selected = true;
                control.append(node);
            });
        } else {
            control = document.createElement('input');
            control.type = field.type ?? 'text';
            control.value = field.value ?? '';
            if (field.placeholder) control.placeholder = field.placeholder;
            control.autocomplete = 'off';
        }
        control.name = field.name;
        label.append(control);

        if (field.help) {
            const help = document.createElement('p');
            help.className = 'ui-help';
            help.textContent = field.help;
            label.append(help);
        }
        body.append(label);
    });

    dialog.showModal();
    body.querySelector('input, select')?.focus();

    return new Promise((resolve) => dialog.addEventListener('close', () => {
        if (dialog.returnValue !== 'confirm') return resolve(null);
        const values = {};
        body.querySelectorAll('[name]').forEach((element) => { values[element.name] = element.value.trim(); });
        resolve(values);
    }, { once: true }));
}
