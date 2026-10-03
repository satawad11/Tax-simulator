import { api, authToken, setAuthToken } from './api.js';

/**
 * Who is looking at this page, and what they may therefore see.
 *
 * Milestone 09.1. Visibility used to be decided in three different places with three ad-hoc
 * attributes, and each decided it slightly differently — which is how the admin link came to be
 * shown to ordinary members on a wide screen. There is now one answer and one mechanism.
 *
 * The answer comes from the API. A token in the browser says someone is signed in; only
 * `/auth/me` says who they are, so the role is never inferred from anything the browser holds.
 *
 * The mechanism is `data-visible-to`, a space-separated list of the audiences an element is for:
 *
 *     <a data-visible-to="guest" href="/login">เข้าสู่ระบบ</a>
 *     <a data-visible-to="member admin" href="/dashboard">แดชบอร์ด</a>
 *     <a data-visible-to="admin" href="/admin">ผู้ดูแลระบบ</a>
 *
 * Elements are hidden with the `hidden` attribute rather than a utility class, because a
 * responsive class such as `lg:inline-flex` overrides the `hidden` class at that breakpoint and
 * would put a hidden control back on screen.
 *
 * None of this is a security boundary and none of it is meant to be one. Every page the console
 * serves is a data-free shell, and every protected endpoint authorises its own caller. This
 * decides what is worth offering, not what is permitted.
 */

export const AUDIENCES = ['guest', 'member', 'admin'];

/** Resolved once per page load; every caller shares the same answer. */
let viewerPromise = null;

/**
 * @returns {Promise<{authenticated: boolean, isAdmin: boolean, name: string|null}>}
 */
export function resolveViewer() {
    if (viewerPromise) return viewerPromise;

    viewerPromise = (async () => {
        if (!authToken()) return { authenticated: false, isAdmin: false, name: null };
        try {
            const user = await api('/auth/me');

            return { authenticated: true, isAdmin: Boolean(user?.is_admin), name: user?.name ?? null };
        } catch (error) {
            // A token the API no longer accepts is not a session; drop it rather than showing
            // member navigation to someone the server treats as a stranger.
            if (error.status === 401) setAuthToken(null);

            return { authenticated: false, isAdmin: false, name: null };
        }
    })();

    return viewerPromise;
}

/** Forgets the cached answer — after signing in or out, the page asks again. */
export function forgetViewer() {
    viewerPromise = null;
}

/** The audience a viewer belongs to. An admin is an admin, not also a member, for display. */
export function audienceOf(viewer) {
    if (!viewer.authenticated) return 'guest';

    return viewer.isAdmin ? 'admin' : 'member';
}

/**
 * Applies `data-visible-to` across a tree.
 *
 * An element with no `data-visible-to` is left alone: most of the page is for everyone, and this
 * must not become a mechanism that has to be applied to every node.
 */
export function applyRoleVisibility(viewer, root = document) {
    const audience = audienceOf(viewer);

    root.querySelectorAll('[data-visible-to]').forEach((element) => {
        const audiences = (element.dataset.visibleTo || '').split(/\s+/).filter(Boolean);
        element.hidden = !audiences.includes(audience);
    });

    // Anything that simply wants to print the signed-in person's name.
    if (viewer.name) {
        root.querySelectorAll('[data-viewer-name]').forEach((element) => {
            element.textContent = viewer.name;
        });
        // The first character stands in for an avatar; the product stores no picture, and a
        // coloured circle with an initial is more legible than a generic silhouette.
        root.querySelectorAll('[data-viewer-initial]').forEach((element) => {
            element.textContent = [...viewer.name][0] ?? '·';
        });
    }

    root.querySelectorAll('[data-viewer-audience]').forEach((element) => {
        element.dataset.viewerAudience = audience;
    });

    return audience;
}
