import paths from '../icons.json';

/**
 * The same icon shapes the Blade component draws, for markup built in the browser.
 *
 * These were duplicated: `icon.blade.php` held all of them, and `admin/console.js`,
 * `tax-result.js`, `member-dashboard.js` and `admin/activity.js` each kept their own partial copy
 * — the shield path appeared four times, the check four, the pencil three. They had already begun
 * to drift, `logout` existing as two `<path>` elements in Blade and one concatenated `d` in the
 * renderers. Two definitions of one action eventually draw two different actions.
 *
 * The cost of importing the whole map is a few kilobytes the bundler cannot tree-shake out of a
 * JSON object. The renderers between them already inlined about fifty of these paths, so the net
 * is close to neutral, and it buys a single source for a thing that had started to disagree with
 * itself.
 *
 * @param {string} name  a key in resources/icons.json
 * @param {string} classes  Tailwind sizing, e.g. 'size-4 shrink-0'
 */
export function icon(name, classes = 'size-5') {
    return `<svg class="${classes}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${
        paths[name] ?? paths.info}</svg>`;
}

/** An `<svg>` element rather than a string, for the places that build nodes. */
export function iconNode(name, classes = 'size-5') {
    const wrapper = document.createElement('span');
    wrapper.innerHTML = icon(name, classes);

    return wrapper.firstElementChild;
}

/** Whether a name exists — so a caller can fail loudly rather than silently draw `info`. */
export function hasIcon(name) {
    return Object.hasOwn(paths, name);
}
