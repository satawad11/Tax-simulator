import { api, authToken, errorText, setAuthToken } from './api.js';
import { applyRoleVisibility, forgetViewer, resolveViewer } from './session.js';
import { hideAlert, showAlert } from './ui.js';

const AFTER_AUTH_KEY = 'tax-simulator.after-auth';

export function initializeAuth() {
    const page = document.querySelector('[data-auth-page]');
    if (!page) return;
    const form = page.querySelector('[data-auth-form]');
    const alert = page.querySelector('[data-auth-alert]');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideAlert(alert);
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        const values = { ...Object.fromEntries(new FormData(form)), device_name: 'Tax Simulator Web' };
        try {
            const result = await api(`/auth/${page.dataset.authPage}`, { method: 'POST', body: JSON.stringify(values) });
            setAuthToken(result.token);
            forgetViewer();

            /*
             * Where someone lands after signing in follows their role.
             *
             * An administrator's home is the console, not the member dashboard — sending them to
             * a page of their own tax drafts and asking them to find /admin was the long way
             * round. A destination they were already heading for still wins, because that is a
             * more specific intention than either default.
             */
            const requested = window.sessionStorage.getItem(AFTER_AUTH_KEY);
            window.sessionStorage.removeItem(AFTER_AUTH_KEY);
            const viewer = await resolveViewer();
            window.location.assign(requested || (viewer.isAdmin ? '/admin' : '/dashboard'));
        } catch (error) {
            showAlert(alert, errorText(error), 'error');
        } finally {
            button.disabled = false;
        }
    });
}

export function requireMember(destination = window.location.pathname) {
    if (authToken()) return true;
    window.sessionStorage.setItem(AFTER_AUTH_KEY, destination);
    window.location.assign('/login');

    return false;
}

export async function currentMember() {
    if (!authToken()) return null;
    try {
        return await api('/auth/me');
    } catch (error) {
        if (error.status === 401) setAuthToken(null);

        return null;
    }
}

/**
 * Milestone 09.1 — the navigation each audience is shown.
 *
 * Guest, member and administrator each see the navigation their role can actually use, resolved
 * once from `/auth/me` and applied through `data-visible-to`. What this decides is what is worth
 * offering; what is permitted is decided by the API on every request, and by nothing here.
 */
export function initializeMemberNavigation() {
    // Sign-out is wired immediately: it must work even if the identity request is slow or fails.
    document.querySelectorAll('[data-member-logout]').forEach((button) => button.addEventListener('click', async () => {
        try {
            await api('/auth/logout', { method: 'POST' });
        } finally {
            setAuthToken(null);
            forgetViewer();
            // After signing out, land on the sign-in page rather than the home page: it is where a
            // returning member most often heads next, and it states plainly that they are signed out.
            window.location.assign('/login');
        }
    }));

    resolveViewer().then((viewer) => applyRoleVisibility(viewer));
}
