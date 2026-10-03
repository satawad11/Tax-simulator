import { api, errorText, setAuthToken } from './api.js';
import { forgetViewer } from './session.js';
import { hideAlert, showAlert } from './ui.js';

/**
 * Phase 1 — the three password forms.
 *
 * One initializer for all of them, because they differ only in endpoint, method and what happens
 * after success. Each page declares which it is with `data-password-page`, exactly as the sign-in
 * page declares itself with `data-auth-page`.
 *
 * What this file does not do is decide anything. It does not check the token, does not judge the
 * password, and does not report whether an address is registered — every one of those answers
 * comes from the API, which is the only place they can be made safely.
 */
const FORMS = {
    forgot: {
        path: '/auth/password/forgot',
        method: 'POST',
        fallback: 'ดำเนินการเรียบร้อยแล้ว',
        // The success message is the API's, and it is deliberately non-committal about whether an
        // account exists. Repeating it verbatim keeps the browser from being more specific than
        // the server intended.
        after: (page) => page.querySelector('[data-password-form]').reset(),
    },
    reset: {
        path: '/auth/password/reset',
        method: 'POST',
        fallback: 'ดำเนินการเรียบร้อยแล้ว',
        // Every token was revoked server-side, so any token held here is already dead.
        after: () => {
            setAuthToken(null);
            forgetViewer();
            window.setTimeout(() => window.location.assign('/login'), 1500);
        },
    },
    change: {
        path: '/auth/password',
        method: 'PUT',
        fallback: 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว',
        after: (page) => page.querySelector('[data-password-form]').reset(),
    },
};

export function initializePasswordForms() {
    const page = document.querySelector('[data-password-page]');
    if (!page) return;
    const config = FORMS[page.dataset.passwordPage];
    if (!config) return;

    const form = page.querySelector('[data-password-form]');
    const alert = page.querySelector('[data-password-alert]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideAlert(alert);
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const body = JSON.stringify(Object.fromEntries(new FormData(form)));
            // These three endpoints carry no payload, so `api()` — which returns `data ?? payload`
            // — hands back the envelope and its `message`. The fallback covers the case where an
            // endpoint later grows a `data` object and the message stops arriving this way.
            const response = await api(config.path, { method: config.method, body });
            showAlert(alert, response?.message || config.fallback, 'success');
            config.after(page);
        } catch (error) {
            showAlert(alert, errorText(error), 'error');
        } finally {
            button.disabled = false;
        }
    });
}
