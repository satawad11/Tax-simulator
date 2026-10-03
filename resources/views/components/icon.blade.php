@props(['name', 'class' => 'size-5'])
{{--
    Milestone 09.1 — the small inline icon set the approved mockup uses.

    Icons are drawn inline rather than loaded from a font or a CDN: the pages must render with no
    external request, and an <svg> can inherit currentColor so one definition works on a white
    card and on a solid blue tile. Every icon is decorative — the label beside it carries the
    meaning — so each is hidden from assistive technology.

    **An icon is never the only label on a control.** The one exception is the collapsed admin
    sidebar, where the label is present in the markup and hidden by CSS, and the link still
    carries a `title`. Everywhere else the words stay: several of this product's actions have no
    settled Thai icon convention — ปิดใช้งาน is deliberately not ลบ, and ถอดสิทธิ์ผู้ดูแล sits next to
    ออกจากระบบทุกอุปกรณ์ in the same row — and two similar glyphs on two consequential actions buys a
    little space at the price of a misclick that grants or removes administrator rights.

    **The shapes live in `resources/icons.json`, not here.** They were duplicated across this file
    and four JavaScript modules, and had begun to drift: `logout` existed as two `<path>` elements
    in Blade and as one concatenated `d` in two renderers. When the same action is drawn from two
    definitions, it eventually looks like two different actions. `resources/js/icons.js` reads the
    same file, so Blade and the browser cannot disagree.
--}}
@php
    // Read once per request rather than once per icon: a page draws dozens of these.
    static $paths;
    $paths ??= json_decode(file_get_contents(resource_path('icons.json')), true);
@endphp
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    {!! $paths[$name] ?? $paths['info'] !!}
</svg>
