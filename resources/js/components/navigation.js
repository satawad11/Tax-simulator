/**
 * Milestone 09.1 — the mobile navigation sheet.
 *
 * The desktop bar is always present; only the small-screen sheet toggles. Visibility is driven by
 * the `hidden` attribute rather than a utility class, so the element is removed from the
 * accessibility tree while closed, and `aria-expanded` on the trigger stays the single source of
 * truth for its state. Escape and a click on any link close it, which is what a keyboard user
 * expects from a disclosure that covers the page.
 */
export function initializeNavigation() {
    const toggle = document.querySelector('[data-navigation-toggle]');
    const sheet = document.getElementById('mobile-navigation');

    if (!toggle || !sheet) return;

    const setExpanded = (expanded) => {
        toggle.setAttribute('aria-expanded', String(expanded));
        sheet.hidden = !expanded;
    };

    toggle.addEventListener('click', () => setExpanded(toggle.getAttribute('aria-expanded') !== 'true'));
    sheet.addEventListener('click', (event) => {
        if (event.target.closest('a')) setExpanded(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setExpanded(false);
            toggle.focus();
        }
    });
}
