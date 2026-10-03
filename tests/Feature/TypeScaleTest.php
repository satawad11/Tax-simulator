<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The type scale, sized for Thai and fluid across devices.
 *
 * Measured in a browser with canvas TextMetrics at 16px: a Thai consonant's body height is
 * **0.75× a Latin capital's**, and a marked syllable — สระ above, วรรณยุกต์ above that, สระล่าง
 * below — spans 1.25em. The scale had inherited Tailwind's Latin defaults: 80 uses of `text-sm` at
 * 14px, 25 of `text-xs` at 12px, and a 10.88px group label, which in Thai is a body height of
 * about six pixels.
 *
 * Two of the rules here are not preferences and are the reason this file exists.
 */
class TypeScaleTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(resource_path('css/app.css'));
    }

    public function test_form_fields_are_at_least_sixteen_pixels(): void
    {
        /*
         * Not a preference: **iOS Safari zooms the whole viewport when a field under 16px takes
         * focus.** The fields were 0.95rem — 15.2px — so tapping any income box on an iPhone threw
         * the page out of its layout and left the reader pinching back. There is no way to opt out
         * short of disabling zoom entirely, which would be worse.
         */
        $css = $this->css();
        $block = substr($css, strpos($css, "input:not([type='checkbox'])"));
        $block = substr($block, 0, strpos($block, '}'));

        $this->assertStringContainsString('font-size: 1rem;', $block);
        $this->assertStringNotContainsString('text-[0.95rem]', $block);
    }

    public function test_no_text_is_sized_below_the_thai_floor(): void
    {
        // 0.8125rem (13px) is the floor for anything a reader has to read. The one exception is
        // `.ui-note-icon`, which centres a single character — "i", "!" — inside a 20px circle: a
        // glyph, not prose.
        $css = $this->css();

        preg_match_all('/text-\[(\d*\.?\d+)rem\]/', $css, $matches);
        foreach ($matches[1] as $index => $rem) {
            if ((float) $rem >= 0.75) {
                continue;
            }
            $context = substr($css, max(0, strpos($css, $matches[0][$index]) - 260), 260);
            $this->assertStringContainsString('ui-note-icon', $context,
                "{$matches[0][$index]} is below the Thai legibility floor and is not the note glyph");
        }
    }

    public function test_the_body_sizes_stay_at_their_familiar_values(): void
    {
        /*
         * A first pass raised every step on the strength of the 0.75× ratio, and the product read
         * as oversized — a 36px heading gains no legibility at 42px, because it was never near a
         * legibility threshold. The ratio is a reason to lift the *floor*, not to inflate the
         * scale, so `xs`, `sm` and `base` sit where they always did and the correction lives in
         * the two places the text was genuinely too small plus the field rule above.
         */
        $css = $this->css();

        $this->assertStringContainsString('--text-xs: 0.75rem;', $css);
        $this->assertStringContainsString('--text-sm: 0.875rem;', $css);
        $this->assertStringContainsString('--text-base: 1rem;', $css);
    }

    public function test_the_display_sizes_end_where_the_design_already_was(): void
    {
        // The upper bound of each clamp is the size the design used before, so a desktop looks as
        // it did; only the phone end comes down.
        $css = $this->css();

        $this->assertStringContainsString('1.5rem)', $css);    // 2xl tops out at 24px
        $this->assertStringContainsString('1.875rem)', $css);  // 3xl at 30px
        $this->assertStringContainsString('2.25rem)', $css);   // 4xl at 36px
    }

    public function test_the_two_labels_that_were_unreadable_are_at_the_floor(): void
    {
        // 10.88px and 11.2px in Thai are a body height of about six pixels. Both are now 12px,
        // which is what `text-xs` means.
        $css = $this->css();

        foreach (['.admin-nav-group', '.ui-step-label'] as $selector) {
            $rule = substr($css, strpos($css, $selector));
            $rule = substr($rule, 0, strpos($rule, '}'));
            $this->assertStringContainsString('text-xs', $rule, "$selector must sit at the floor");
            $this->assertStringNotContainsString('0.68rem', $rule);
            $this->assertStringNotContainsString('0.7rem', $rule);
        }
    }

    public function test_the_display_sizes_are_fluid_rather_than_stepped(): void
    {
        // A heading should grow smoothly from a 320px phone to a wide desktop, not jump twice on
        // the way there.
        $css = $this->css();

        foreach (['--text-2xl', '--text-3xl', '--text-4xl'] as $token) {
            $this->assertMatchesRegularExpression("/{$token}: clamp\(/", $css,
                "$token must scale with the viewport");
        }
    }

    public function test_every_size_token_carries_a_line_height_thai_can_live_in(): void
    {
        // A marked Thai syllable spans 1.25em, so anything under about 1.25 clips and anything
        // under about 1.5 collides with the line below.
        $css = $this->css();

        preg_match_all('/--text-(\w+)--line-height: ([\d.]+);/', $css, $matches, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(8, count($matches), 'every size token needs a line height');

        foreach ($matches as [$whole, $name, $value]) {
            $this->assertGreaterThanOrEqual(1.25, (float) $value,
                "--text-$name--line-height would clip Thai tone marks");
        }
    }

    public function test_body_text_keeps_the_room_thai_marks_need(): void
    {
        $this->assertMatchesRegularExpression('/body\s*\{\s*line-height:\s*1\.65;/', $this->css());
    }

    public function test_the_heading_no_longer_steps_at_breakpoints(): void
    {
        // The token is fluid now, so a `sm:` override on top of it would scale it twice.
        $css = $this->css();
        $rule = substr($css, strpos($css, '.ui-section-title'));
        $rule = substr($rule, 0, strpos($rule, '}'));

        $this->assertStringContainsString('text-3xl', $rule);
        $this->assertStringNotContainsString('sm:text-', $rule);
    }

    public function test_call_sites_use_the_scale_rather_than_their_own_sizes(): void
    {
        // Raising one token moves the whole product only while nothing opts out of it.
        foreach (['views', 'js'] as $directory) {
            $files = glob(resource_path($directory).'/{,*/}*.{blade.php,js}', GLOB_BRACE);
            foreach ($files as $file) {
                $this->assertStringNotContainsString('text-[0.95rem]', file_get_contents($file),
                    basename($file).' pins a size instead of using the scale');
            }
        }
    }

    public function test_the_pages_still_declare_a_responsive_viewport(): void
    {
        // Fluid type is meaningless if the page is rendered at a fixed width first.
        $this->get('/tax-simulator')->assertOk()
            ->assertSee('width=device-width, initial-scale=1', false);
        // And zoom must not be disabled — the 16px field rule exists so it never has to be.
        $this->get('/tax-simulator')->assertOk()->assertDontSee('user-scalable=no', false);
        $this->get('/tax-simulator')->assertOk()->assertDontSee('maximum-scale=1', false);
    }
}
