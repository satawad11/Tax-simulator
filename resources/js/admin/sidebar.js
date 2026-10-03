const STORAGE_KEY = 'tax-simulator.admin-sidebar';
const DESKTOP = '(min-width: 1024px)';

/**
 * Milestone 09.1 — the console sidebar.
 *
 * One element, two behaviours, because a phone and a desktop are asking different things of it.
 *
 *   Below lg it is a drawer. It opens over the page with a backdrop, and closes on the backdrop,
 *   on Escape, on following a link, and on growing past the breakpoint — a drawer left open
 *   while the window is widened would otherwise sit there as a stuck overlay. Focus moves into
 *   it on open and back to the trigger on close, because a drawer that covers the page and
 *   leaves focus behind it is unusable from the keyboard.
 *
 *   From lg up it is part of the layout and always present; collapsing narrows it to its icons.
 *   That choice is remembered in localStorage and re-applied by an inline script before first
 *   paint, so the sidebar is never drawn wide and then snapped narrow.
 *
 * Both states are attributes on <html>, so the stylesheet owns every pixel of the presentation
 * and this module owns only the state.
 */
export function initializeAdminSidebar() {
    const sidebar = document.getElementById('admin-sidebar');
    if (!sidebar) return;

    const root = document.documentElement;
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const trigger = document.querySelector('[data-sidebar-open]');
    const collapseButton = document.querySelector('[data-sidebar-collapse]');
    const desktop = window.matchMedia(DESKTOP);

    /* ------------------------------------------------------------------ drawer ---- */

    const setOpen = (open) => {
        root.dataset.sidebarOpen = String(open);
        trigger?.setAttribute('aria-expanded', String(open));
        if (backdrop) backdrop.hidden = !open;
        // The page behind a drawer must not scroll away underneath it.
        document.body.style.overflow = open && !desktop.matches ? 'hidden' : '';
        if (open) sidebar.querySelector('a, button')?.focus();
        else if (document.activeElement && sidebar.contains(document.activeElement)) trigger?.focus();
    };

    trigger?.addEventListener('click', () => setOpen(root.dataset.sidebarOpen !== 'true'));
    document.querySelector('[data-sidebar-close]')?.addEventListener('click', () => setOpen(false));
    backdrop?.addEventListener('click', () => setOpen(false));

    // Following a link closes the drawer; on desktop there is nothing to close.
    sidebar.addEventListener('click', (event) => {
        if (event.target.closest('a') && !desktop.matches) setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.dataset.sidebarOpen === 'true') setOpen(false);
    });

    // Growing past the breakpoint turns the drawer back into part of the layout, and the rail
    // width has to follow — below it the drawer owns its own width.
    desktop.addEventListener('change', (event) => {
        if (event.matches) setOpen(false);
        applyRailWidth();
    });

    /* ---------------------------------------------------------------- collapse ---- */

    const RAIL = { expanded: '16rem', collapsed: '4.75rem' };

    /*
     * The rail width is applied inline, and only above the breakpoint.
     *
     * This module already owns the collapsed state, so it owns the one number that state decides.
     * Keeping the width here rather than in a second, more specific stylesheet rule means it
     * cannot depend on how two generated rules happen to be ordered. Below the breakpoint the
     * inline width is removed again, so the drawer keeps the width the stylesheet gives it.
     */
    const applyRailWidth = () => {
        if (!desktop.matches) {
            sidebar.style.removeProperty('width');

            return;
        }
        sidebar.style.width = sidebar.dataset.collapsed === 'true' ? RAIL.collapsed : RAIL.expanded;
    };

    const setCollapsed = (collapsed) => {
        root.dataset.sidebarCollapsed = String(collapsed);
        // The attribute still drives everything that is purely presentational — the labels, the
        // centred icons, the group dividers — which CSS handles perfectly well.
        sidebar.dataset.collapsed = String(collapsed);
        applyRailWidth();
        collapseButton?.setAttribute('aria-expanded', String(!collapsed));
        const label = collapseButton?.querySelector('[data-sidebar-collapse-label]');
        if (label) label.textContent = collapsed ? 'ขยายเมนู' : 'ย่อเมนู';
        // Collapsed, the item is an icon with no words beside it; the name has to come from here.
        collapseButton?.setAttribute('title', collapsed ? 'ขยายเมนู' : 'ย่อเมนู');
        try {
            window.localStorage.setItem(STORAGE_KEY, collapsed ? 'collapsed' : 'expanded');
        } catch { /* Private browsing: the choice simply is not remembered. */ }
    };

    collapseButton?.addEventListener('click', () => setCollapsed(root.dataset.sidebarCollapsed !== 'true'));

    // The inline head script already applied the stored state to <html>; mirror it onto the
    // sidebar and align the button's label with it, without writing storage again on load.
    const collapsed = root.dataset.sidebarCollapsed === 'true';
    sidebar.dataset.collapsed = String(collapsed);
    applyRailWidth();
    collapseButton?.setAttribute('aria-expanded', String(!collapsed));
    const label = collapseButton?.querySelector('[data-sidebar-collapse-label]');
    if (label) label.textContent = collapsed ? 'ขยายเมนู' : 'ย่อเมนู';
    collapseButton?.setAttribute('title', collapsed ? 'ขยายเมนู' : 'ย่อเมนู');

    setOpen(false);
}
