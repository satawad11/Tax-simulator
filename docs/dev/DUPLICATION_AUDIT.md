# Removing duplication — measured, not guessed

2026-09-17. An audit for repeated code, then the removals that were worth making. Measured first,
because "clean it up" is otherwise an invitation to churn a working codebase.

---

## What the measurement found

| Duplication | Extent | Verdict |
| --- | --- | --- |
| SVG icon paths | 5 files; the shield path **4×**, the check **4×**, the pencil **3×** | removed — was already drifting |
| Thai content-type labels | 3 copies of the same four words | removed |
| Content status labels | 3 copies | removed |
| `escapeHtml` / `escapeAttribute` | two implementations of one escaping routine | removed |
| Test admin creation | 7 classes, one of them 3× | removed |
| `forgetGuards()` | 5 classes, 12 call sites, one comment explaining why | removed |
| `console.js` at 1,026 lines | one cohesive console | **kept** — size is not a defect |

## 1. Icons — one source for Blade and the browser

The shapes lived in `icon.blade.php` **and** in private maps inside `admin/console.js`,
`admin/activity.js`, `tax-result.js` and `member-dashboard.js`. They had already begun to disagree:

```
icon.blade.php   'logout' => '<path d="M15 5.5H…"/><path d="M12.5 12h8m-3-3 3 3-3 3"/>'
console.js       logout:     'M15 5.5H…M12.5 12h8m-3-3 3 3-3 3'      ← one concatenated d
member-dashboard logout:     'M15 5.5H…M12.5 12h8m-3-3 3 3-3 3'
```

Two definitions of one action eventually draw two different actions.

Now `resources/icons.json` is the single source. `icon.blade.php` reads it; `resources/js/icons.js`
imports it and exports `icon(name, classes)`. The renderers name icons instead of carrying shapes:

```js
const ACTION_ICON = { edit: 'pencil', deactivate: 'power', promote: 'shield', suspend: 'ban', … };
```

The indirection stays deliberately — the console speaks in **actions** (promote, demote, suspend)
while the icon set speaks in **shapes**, and naming the mapping is what lets one change without
dragging the other with it.

**Cost, stated honestly:** importing the whole map adds about 1 KB gzipped, because a bundler
cannot tree-shake keys out of a JSON object. The renderers between them already inlined ~50 paths,
so the bundle moved 138.08 → 139.04 KB (33.07 → 33.29 KB gzipped). Worth it to stop a thing that
had started disagreeing with itself.

**A test caught a real miss.** The extraction silently dropped `key`, the last entry in the map — a
missing name falls back to `info`, so the wrong picture would have shipped with no error anywhere.
`test_every_icon_a_page_asks_for_exists` now scans every view and module for icon references and
fails if any names a shape that does not exist.

## 2. Labels — the API sends the words

`article → บทความ` was written out in `article-card.blade.php`, `content/show.blade.php` and the
console's content list. Phase 3 had already found the console's copy printing raw `ARTICLE` codes
while the other two showed บทความ — three copies of four words is three chances to disagree about
what the product calls a thing.

The vocabulary now lives on the model that owns it, `ContentPost::TYPE_LABELS` and
`STATUS_LABELS`. The Blade views read the constants; the admin API sends `type_label` and
`status_label`, so **the browser keeps no copy of the vocabulary at all** and cannot drift from the
server-rendered pages.

## 3. One escaping function

`console.js` had its own, differing from `ui.js` only in whether an apostrophe became `&#39;` or
`&#039;`. Functionally identical today — but two implementations of an escaping routine is exactly
the duplication worth removing, because the day they drift is the day one of them is wrong.

## 4. Test helpers — `Tests\Concerns\ActsAsAdmin`

Seven classes each wrote `User::factory()->create()` then
`forceFill(['role' => User::ROLE_ADMIN])->save()`. Harmless until the shape changes — and it just
had: `users` gained `suspended_at`, so "an administrator who can actually use the console" became
two conditions rather than one.

The trait carries `admin()`, `actingAsAdmin()`, `actingAsMember()` and `forgetAuthenticatedUser()`
— the last with the explanation five classes had each rediscovered and only one wrote down.

Five older classes still have their own `admin()` helper. They were left alone deliberately; see
below.

## What was deliberately not done

**`console.js` was not split.** At 1,026 lines it holds nine page loaders for one console. Breaking
it into nine modules would add nine files and a web of cross-imports to change nothing a user or a
maintainer can observe. Line count is not a defect on its own.

**Five older test classes keep their local `admin()`.** After the incident below, converting more
files by script was not worth the risk for duplication that costs nothing today. They can move
when they are next edited for another reason.

---

## An incident, recorded because it matters

While converting the test files, a script of mine rewrote six of them with `preg_replace`. One
pattern failed to compile, `preg_replace` returned `null`, and the code wrote that `null` to
disk — **truncating six test files to zero bytes**, on the host and in the container, with no
version control in this repository to restore from.

All six were rewritten from source and verified: **84 tests, exactly the count they had before**
(17 + 23 + 12 + 15 + 11 + 6), all passing.

Three things that should have prevented it, in order of how much they would have helped:

1. **Check the return value.** `preg_replace` returning `null` is its documented failure mode. The
   script wrote whatever it got back.
2. **Never mass-rewrite source files with regex.** The conversion was mechanical-looking and was
   not — each file had a different helper signature.
3. **The warnings were visible and ignored.** Six `preg_replace(): Compilation failed` lines
   printed in the same output as six "rewrote" lines. Output that reports success and failure
   together is output that has to be read, not skimmed.

## Verification

| Check | Result |
| --- | --- |
| SQLite full suite | 1,090 passed, 2 skipped, 6,063 assertions |
| MySQL full suite | 1,090 passed, 2 skipped, 6,063 assertions |
| Pint | 295 files, pass |
| `npm run build` | built |
| Identical SVG paths across files | **0** (was 5 shapes repeated 2–4× each) |
| Modules holding SVG path literals | **0** (was 4) |
| Browser | 26 SVGs on the simulator, 22 on the knowledge page, **none empty**; category badges render their Thai labels |
