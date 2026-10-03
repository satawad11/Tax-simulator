<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Thai typography, and icons that accompany words rather than replace them.
 *
 * The stack was `Tahoma, 'Leelawadee UI', …`. Tahoma ships only Regular and Bold, while this
 * design asks for four weights — medium, semibold, bold, extrabold — in 143 places. Measured in a
 * browser at 32px, Tahoma drew the same Thai sentence at 488.81px for weights 400 *and* 500, and
 * at 534.33px for 600, 700 *and* 800: three-quarters of the hierarchy the design defines never
 * rendered. Noto Sans Thai Variable carries 100–900 in one file; the same measurement gives five
 * distinct widths.
 *
 * On icons, the rule this file exists to hold is that **an icon is never a control's only label**.
 */
class TypographyAndIconsTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(resource_path('css/app.css'));
    }

    // --------------------------------------------------------- typography

    public function test_the_thai_face_is_first_in_the_stack(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            "/--font-sans:\s*'Noto Sans Thai Variable'/", $css,
            'The webfont must lead the stack, or the page falls back to a two-weight face.');
        // Windows' looped Thai UI face stays next, so a page rendered before the webfont arrives
        // is still set in something designed for Thai on screen.
        $this->assertMatchesRegularExpression("/'Noto Sans Thai Variable',\s*'Leelawadee UI'/", $css);
    }

    public function test_the_font_is_self_hosted_not_fetched_from_a_cdn(): void
    {
        // No third-party request on a page where someone is typing their income, and nothing to
        // fail on an intranet.
        $css = $this->css();

        $this->assertStringContainsString("@import '@fontsource-variable/noto-sans-thai/wght.css'", $css);
        $this->assertStringNotContainsString('fonts.googleapis.com', $css);
        $this->assertStringNotContainsString('fonts.gstatic.com', $css);
    }

    public function test_the_font_dependency_is_recorded_so_a_clean_install_gets_it(): void
    {
        // It was briefly installed in the container only, which builds fine and then fails on any
        // machine that runs `npm ci`.
        $package = json_decode(file_get_contents(base_path('package.json')), true);
        $lock = file_get_contents(base_path('package-lock.json'));

        $this->assertArrayHasKey('@fontsource-variable/noto-sans-thai', $package['devDependencies']);
        $this->assertStringContainsString('@fontsource-variable/noto-sans-thai', $lock);
    }

    public function test_thai_gets_the_vertical_room_its_marks_need(): void
    {
        /*
         * Thai stacks vowels above and tone marks above those, with descending vowels below, so a
         * line height tuned for Latin puts one line's ไม้โท into the next line's สระอุ.
         *
         * The *property* is asserted here and the exact value in `TypeScaleTest`, which owns the
         * scale. Two tests pinning the same tuning number would only fight each other the next
         * time it is adjusted — and it was, from 1.7 to 1.65, when the first pass read as airy.
         */
        preg_match('/body\s*\{\s*line-height:\s*([\d.]+);/', $this->css(), $match);

        $this->assertNotEmpty($match, 'body must set a line height for Thai');
        $this->assertGreaterThanOrEqual(1.5, (float) $match[1],
            'a marked Thai syllable spans 1.25em; below about 1.5 the lines collide');
    }

    public function test_the_deliberately_tuned_line_heights_are_untouched(): void
    {
        // Only the default moves; every `leading-*` the components set still wins.
        $css = $this->css();

        $this->assertStringContainsString('leading-8', $css);
        $this->assertStringContainsString('leading-7', $css);
    }

    // -------------------------------------------------------------- icons

    public function test_the_icon_set_carries_the_row_action_glyphs(): void
    {
        // The shapes moved out of the Blade component into `resources/icons.json`, which both
        // Blade and the browser now read; the set still has to carry these.
        $icons = json_decode(file_get_contents(resource_path('icons.json')), true);

        foreach (['pencil', 'power', 'trash', 'shield-off', 'logout', 'printer', 'mail', 'key'] as $name) {
            $this->assertArrayHasKey($name, $icons, "icon '$name' is missing");
        }
    }

    public function test_every_icon_a_page_asks_for_exists(): void
    {
        /*
         * A missing name silently draws `info`, so the wrong picture ships without an error
         * anywhere. Consolidating the set is exactly when this can go wrong — one entry was
         * dropped in the move and only an assertion noticed.
         */
        $icons = json_decode(file_get_contents(resource_path('icons.json')), true);
        $referenced = [];

        foreach (['views', 'js'] as $directory) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path($directory)));
            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                preg_match_all('/<x-icon[^>]*name="([a-z-]+)"|icon\(\'([a-z-]+)\'|glyph: \'([a-z-]+)\'|icon: \'([a-z-]+)\'/',
                    file_get_contents($file->getPathname()), $matches);
                foreach (array_merge($matches[1], $matches[2], $matches[3], $matches[4]) as $name) {
                    if ($name !== '') {
                        $referenced[$name] = $file->getFilename();
                    }
                }
            }
        }

        $this->assertNotEmpty($referenced, 'the scan found no icon references at all');
        foreach ($referenced as $name => $where) {
            $this->assertArrayHasKey($name, $icons, "$where asks for icon '$name', which does not exist");
        }
    }

    public function test_one_icon_source_serves_both_blade_and_the_browser(): void
    {
        /*
         * The shapes were duplicated across `icon.blade.php` and four renderers — the shield path
         * appeared four times, the check four, the pencil three — and had started to drift, with
         * `logout` drawn as two `<path>` elements in Blade and one concatenated `d` in the
         * renderers. Two definitions of one action eventually draw two different actions.
         */
        $this->assertStringContainsString("resource_path('icons.json')",
            file_get_contents(resource_path('views/components/icon.blade.php')));
        $this->assertStringContainsString("import paths from '../icons.json'",
            file_get_contents(resource_path('js/icons.js')));

        // No renderer may keep a private map of paths again.
        foreach (['js/admin/console.js', 'js/admin/activity.js', 'js/tax-result.js', 'js/member-dashboard.js'] as $module) {
            $source = file_get_contents(resource_path($module));
            $this->assertDoesNotMatchRegularExpression('/glyph: \'[Mm][\d\s.]/', $source,
                "$module still inlines an SVG path");
            $this->assertDoesNotMatchRegularExpression('/path: \'[Mm][\d\s.]/', $source,
                "$module still inlines an SVG path");
        }
    }

    public function test_a_row_action_carries_both_an_icon_and_its_words(): void
    {
        /*
         * The rule this whole file exists for. Several of these actions have no settled Thai icon
         * convention — ปิดใช้งาน is deliberately not ลบ — and the accounts table puts
         * ถอดสิทธิ์ผู้ดูแล beside ออกจากระบบทุกอุปกรณ์, where two similar glyphs alone would buy a
         * little space at the price of a misclick that grants or removes administrator rights.
         */
        $console = file_get_contents(resource_path('js/admin/console.js'));

        $this->assertStringContainsString('function actionButton(', $console);
        // The helper writes the label as text; an icon-only variant would have to drop this line.
        $this->assertStringContainsString("button.querySelector('span').textContent = label;", $console);

        // Every row action goes through the helper rather than being built ad hoc. The label may
        // sit inside a ternary — the accounts row picks promote or demote from one call — so the
        // match allows anything before it inside the same argument list.
        foreach (['ตั้งเป็นผู้ดูแล', 'ถอดสิทธิ์ผู้ดูแล', 'ออกจากระบบทุกอุปกรณ์', 'ปิดใช้งาน', 'แก้ไข', 'ลบ'] as $label) {
            $this->assertMatchesRegularExpression("/actionButton\([^)]*?'".preg_quote($label, '/')."'/u", $console,
                "row action '$label' must use actionButton, so it keeps both icon and words");
        }
    }

    public function test_no_row_action_is_built_as_a_bare_text_button(): void
    {
        $console = file_get_contents(resource_path('js/admin/console.js'));

        // `textNode('button', …)` still has legitimate uses (the rule-resource chips, the shared
        // content/version action helper), but none of the destructive row actions may use it.
        foreach (['ปิดใช้งาน', 'ลบ', 'ออกจากระบบทุกอุปกรณ์', 'ถอดสิทธิ์ผู้ดูแล'] as $label) {
            $this->assertStringNotContainsString("textNode('button', '$label'", $console);
        }
    }

    public function test_the_member_tabs_carry_an_icon_beside_each_word(): void
    {
        $shell = file_get_contents(resource_path('views/dashboard/shell.blade.php'));

        $this->assertStringContainsString("'icon' => 'chart'", $shell);
        $this->assertStringContainsString("'icon' => 'document'", $shell);
        $this->assertStringContainsString("'icon' => 'user'", $shell);
        // The label is still printed next to it.
        $this->assertStringContainsString("{{ \$tab['label'] }}", $shell);
    }

    public function test_the_member_tabs_render_with_both(): void
    {
        $response = $this->get('/dashboard')->assertOk();

        $response->assertSee('ภาพรวม');
        $response->assertSee('บัญชีของฉัน');
        // An <svg> inside the tab strip proves the icon reached the page, not just the config.
        $response->assertSee('<svg', false);
    }

    public function test_the_result_actions_keep_their_words(): void
    {
        $renderer = file_get_contents(resource_path('js/tax-result.js'));

        foreach (['พิมพ์ / บันทึกเป็น PDF', 'แก้ไขข้อมูล', 'ทดลองวางแผนภาษี', 'บันทึกผลนี้'] as $label) {
            $this->assertStringContainsString($label, $renderer);
        }
        // Each label is preceded by its icon, now drawn from the shared set by name.
        $this->assertStringContainsString("icon('printer', 'size-4')", $renderer);
    }

    public function test_the_only_icon_only_control_is_the_collapsed_sidebar(): void
    {
        /*
         * There the label is present in the markup and hidden by CSS when collapsed, and the link
         * still carries a `title`, so it is icon-only on screen and never icon-only in the
         * document. That is the one shape this product allows.
         */
        $layout = file_get_contents(resource_path('views/components/admin-layout.blade.php'));

        $this->assertStringContainsString('title="{{ $item[\'label\'] }}"', $layout);
        $this->assertStringContainsString('admin-sidebar-label', $layout);
        $this->assertStringContainsString("{{ \$item['label'] }}</span>", $layout);
    }
}
