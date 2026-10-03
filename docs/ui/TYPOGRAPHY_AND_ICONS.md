# Thai typography, and icons that accompany words

2026-09-16. Two requests: consider icons in place of text on the admin and member pages, and
improve the readability of the type. One is delivered as asked; the other I have answered
differently, with the reasoning below.

---

## 1. Typography — the real finding

The stack was:

```css
--font-sans: Tahoma, 'Leelawadee UI', ui-sans-serif, system-ui, sans-serif, …;
```

**Tahoma ships only Regular and Bold.** This design asks for four weights — `font-medium`,
`font-semibold`, `font-bold`, `font-extrabold` — used **143 times** across the CSS, the Blade
templates and the JavaScript renderers. Three-quarters of the hierarchy it defines never rendered.

Measured in the browser, one Thai sentence at 32px:

| Weight | Tahoma | Noto Sans Thai Variable |
| --- | --- | --- |
| 400 | 488.81px | 466.73px |
| 500 | **488.81px** — identical to 400 | 473.20px |
| 600 | 534.33px | 480.47px |
| 700 | **534.33px** — identical to 600 | 488.75px |
| 800 | **534.33px** — identical to 600 | 495.48px |

Four weights collapsing to two, against five distinct weights. This is measurement, not taste: a
heading marked `font-extrabold` and a nav item marked `font-semibold` were drawn with the same
strokes, so the page had less hierarchy than its own stylesheet believed.

Two further problems with the old stack, both structural:

- **Tahoma's Thai is a loopless (ไม่มีหัว) design from an era of much lower pixel densities**,
  drawn tight for small sizes. Looped (มีหัว) faces are what Thai readers expect for continuous
  reading.
- **It renders differently per operating system.** Tahoma is a Windows font; elsewhere the stack
  fell through to whatever the platform chose. The product did not look the same to two readers.

### What changed

`@fontsource-variable/noto-sans-thai` — a variable font carrying weights 100–900 in one file, so
every weight in the design is a real one.

**Self-hosted through the bundler, not a font CDN.** No third-party request on a page where
someone is typing their income, nothing to fail on an intranet, and Vite fingerprints the files.
They are subset by `unicode-range` — 27 KB of Thai, 31 KB of Latin, and a browser fetches only the
ranges the page actually uses.

`'Leelawadee UI'` stays second in the stack: it is the looped Thai UI face Windows already carries,
so a page rendered in the moment before the webfont arrives still uses something designed for Thai
on screen rather than falling straight back to Tahoma.

### Vertical room

```css
body { line-height: 1.7; }
```

Thai stacks vowels above the line and tone marks above those, with descending vowels below. A line
height tuned for Latin puts one line's ไม้โท into the next line's สระอุ. Only the default moves —
every `leading-*` the components already set (`.ui-body` at 8, `.ui-help` at 6) still wins, so
nothing deliberately tuned is disturbed.

---

## 2. Icons — accompanying the words, not replacing them

I did not replace text with icons, and this section is the argument for why.

### Where icon-only would have gone wrong

The accounts table puts **ถอดสิทธิ์ผู้ดูแล** next to **ออกจากระบบทุกอุปกรณ์** in the same row. Both are
consequential — one grants or removes administrator rights over the whole product, the other ends
someone's sessions mid-task. As icons they would be two small shields-and-arrows a few pixels
apart, told apart only by colour.

The taxonomy page is worse. It deliberately says **ปิดใช้งาน** rather than **ลบ** because the API
deactivates a label that content already uses and deletes one that nothing references — a
distinction the page was built to make honest. No Thai icon convention distinguishes those two.
An icon there would throw away clarity that cost work to establish.

And icon-only controls still need an accessible name, so the words do not actually disappear —
they only become invisible to sighted users, which is the one group they were removed for.

### What was done instead

Icons **beside** the existing labels, so a column of actions is scannable at a glance while every
control still says what it does:

| Where | Before | After |
| --- | --- | --- |
| Admin row actions — แก้ไข, ปิดใช้งาน, ลบ, แก้ชื่อ | text only | icon + text, through one `actionButton()` helper |
| Accounts — ตั้งเป็นผู้ดูแล, ถอดสิทธิ์ผู้ดูแล, ออกจากระบบทุกอุปกรณ์ | text only | shield / shield-struck / logout + text |
| Member tabs — ภาพรวม, แบบภาษีของฉัน, บัญชีของฉัน | text only | chart / document / user + text |
| Account page — เปลี่ยนรหัสผ่าน, ออกจากระบบทุกอุปกรณ์, ส่งลิงก์ยืนยัน | text only | key / logout / mail + text |
| Result — พิมพ์, แก้ไขข้อมูล, วางแผน, บันทึกผล | text only | printer / pencil / chart / save + text |

Eight glyphs were added to the icon component: `pencil`, `power`, `trash`, `shield-off`, `logout`,
`printer`, `mail`, `key`.

### The one place icon-only is right, and already was

The **collapsed admin sidebar**. There the label is present in the markup and hidden by CSS, the
link carries a `title`, and the reader chose the collapsed state themselves. It is icon-only on
screen and never icon-only in the document. `TypographyAndIconsTest` asserts that shape, and
asserts that every other row action keeps its words.

---

## A dependency that was almost lost

The font was first installed inside the container, and a later `docker cp package.json` overwrote
npm's update — the build kept working from `node_modules` while neither manifest recorded the
package. Any machine running `npm ci` would have built without it and silently fallen back.

It is now in `package.json` and `package-lock.json`, verified by deleting the package and running
`npm ci`, and `test_the_font_dependency_is_recorded_so_a_clean_install_gets_it` fails if it is ever
dropped again.

## A dev-server note, not a product issue

Browsing the dev server at `http://127.0.0.1:8088` makes the webfont fail: Vite sets
`Access-Control-Allow-Origin` to `APP_URL`, which is `http://localhost:8088`, and a font request
is CORS-checked. **Use `localhost`, not `127.0.0.1`, in development.** Built assets are served
same-origin by nginx, so production is unaffected — verified by moving `public/hot` aside and
loading the built bundle.

## Verification

| Check | Result |
| --- | --- |
| `TypographyAndIconsTest` (new) | 12 passed, 46 assertions |
| SQLite full suite | 1,027 passed, 2 skipped, 5,757 assertions |
| MySQL full suite | 1,027 passed, 2 skipped, 5,757 assertions |
| Pint | 290 files, pass |
| `npm run build` | built; three woff2 subsets emitted alongside the CSS |
| `npm ci` from a cleaned state | restores the font package |
| Browser | `document.fonts` reports the face **loaded**; five distinct weight widths measured; admin and member pages rendered and inspected |

`AdminConsoleCoverageTest::test_the_source_control_does_not_promise_deletion` checked for the old
button construction. Its subject is the **label** — the endpoint deactivates and never deletes, so
the control must not say ลบ — so the assertion was updated to the new construction and keeps its
meaning rather than being relaxed.

---

## 3. The type scale, sized for Thai and fluid across devices (2026-09-16)

Changing the typeface fixed *which* letterforms render. It did not fix *how big* they are — the
scale was still Tailwind's Latin defaults.

### What the measurement said

Canvas TextMetrics at 16px in the browser:

| | Ascent | Note |
| --- | --- | --- |
| Latin capitals `HX` | 12px | the reference |
| Thai consonants `กขถ` | **9px** | **0.75× a Latin capital** |
| Thai with marks `ที่นี้ญ` | 17px up, 3px down | **1.25em** of stacked height |

So Thai set at 14px has the visual presence of Latin at about 10.5px. The scale in use was 80
occurrences of `text-sm` at **14px**, 25 of `text-xs` at **12px** — a 9px Thai body — and a group
label at **10.88px**, where a Thai consonant's body is barely six pixels tall.

### Two things that were not preferences

**Form fields were 15.2px, and iOS Safari zooms the viewport when a field under 16px takes focus.**
Tapping any income box in the wizard on an iPhone threw the page out of its layout and left the
reader pinching back. Fields are now exactly `1rem`. There is no way to opt out short of disabling
zoom entirely, which would be worse — and the page must never do that.

**A marked Thai syllable spans 1.25em**, so a line height under ~1.25 clips tone marks and under
~1.5 collides with the line below. Every size token now carries its own line height, and a test
fails if any drops below 1.25.

### The correction, after a first pass that overshot

The first attempt raised **every** step on the strength of that 0.75 ratio, and the product read as
oversized. That was the wrong inference: **the ratio is a reason to lift the floor, not to inflate
the scale.** A 36px heading gains no legibility at 42px, because it was never near a legibility
threshold — the sizes that needed help were the ones already down near it.

So the body steps sit back at their familiar values, and only three things moved:

| | Was | Now | Why |
| --- | --- | --- | --- |
| form fields | 15.2px | **16px** | iOS zoom — not a preference |
| sidebar group label | 10.88px | **12px** | a Thai body height of about six pixels |
| step-rail label | 11.2px | **12px** | the same |

`text-xs` 12px, `text-sm` 14px, `text-base` 16px and `.ui-body` 15px are exactly what they were.

The display sizes became `clamp()`s whose **upper bounds are the sizes the design already used** —
24 / 30 / 36px — so a desktop looks as it did. Only the lower bounds are new, so a 375px phone gets
20 / 23 / 26px: a heading that fits its column instead of one scaled for a laptop.
`.ui-section-title` dropped its `sm:` override, which would otherwise have scaled twice.

Body line height went 1.5 → 1.7 → **1.65**: enough for the 1.25em mark stack without the page
turning airy.

`.ui-note-icon` keeps its 0.7rem: it centres a single character — "i", "!" — inside a 20px circle.
A glyph, not prose. The floor test names it as the one exception rather than leaving a blanket
threshold nobody could explain.

### Verified in the browser, not just in the stylesheet

| Viewport | `2xl` / `3xl` / `4xl` | Fields | Horizontal overflow |
| --- | --- | --- | --- |
| 375 × 812 (phone) | 20 / 23 / 26px | 16px | **0px** |
| 1280 × 800 | **24 / 30 / 36px — the original sizes** | 16px | 0px |

Overflow measured as 0 on `/tax-simulator/pnd91`, `/admin/users`, `/knowledge` and
`/dashboard/account` at 375px. The step rail is wider than the phone screen **by design** — it sits
in its own `overflow-x` container, which is why the page itself never scrolls sideways.

| Check | Result |
| --- | --- |
| `TypeScaleTest` (new) | 11 passed, 100 assertions |
| SQLite full suite | 1,082 passed, 2 skipped, 5,986 assertions |
| MySQL full suite | 1,082 passed, 2 skipped, 5,986 assertions |
| Pint | 296 files, pass |
| `npm run build` | built |

`ResponsiveLayoutInvariantTest`, which locks the product against horizontal overflow at 375px,
passes unchanged.

`TypographyAndIconsTest` had pinned `line-height: 1.7` literally. It now asserts the *property* —
a line height is set, and it clears the 1.25em mark stack — and leaves the exact value to
`TypeScaleTest`, which owns the scale. Two tests pinning one tuning number only fight each other
the next time it moves, which it just did.
