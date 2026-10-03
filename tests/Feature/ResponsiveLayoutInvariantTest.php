<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Milestone 09.1 — the two layout rules that a phone screen actually depends on.
 *
 * Both were found by measuring, not by reading: the result page pushed the whole document 65px
 * sideways at 375px wide. The causes were unremarkable and easy to reintroduce, so they are
 * asserted here rather than left to the next person to rediscover.
 *
 * 1. A grid that declares a column count only at a breakpoint has no base count, so its implicit
 *    column is sized by content and a long Thai label widens the page. Every responsive grid must
 *    also say `grid-cols-1`.
 *
 * 2. A flex or grid item defaults to `min-width: auto`, so a label/amount pair sets a minimum
 *    width wider than the screen. The summary row's label must be allowed to shrink.
 *
 * These are static checks over the source, which is what makes them cheap enough to keep.
 */
class ResponsiveLayoutInvariantTest extends TestCase
{
    /** @return list<string> every Blade template and front-end script */
    private function sourceFiles(): array
    {
        $files = [];
        foreach ([resource_path('views'), resource_path('js')] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function test_every_responsive_grid_declares_a_base_column_count(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $path) {
            $contents = (string) file_get_contents($path);
            // Every class list in the file, however it is written: a Blade attribute or a
            // template literal inside a renderer.
            preg_match_all('/class="([^"]*)"/', $contents, $matches);

            foreach ($matches[1] as $classList) {
                $isGrid = preg_match('/(^|\s)grid(\s|$)/', $classList) === 1;
                $hasResponsiveColumns = preg_match('/\b(sm|md|lg|xl):grid-cols-/', $classList) === 1;
                $hasBaseColumns = preg_match('/(^|\s)grid-cols-/', $classList) === 1;

                if ($isGrid && $hasResponsiveColumns && ! $hasBaseColumns) {
                    $offenders[] = basename($path).': '.$classList;
                }
            }
        }

        $this->assertSame([], $offenders,
            "A responsive grid without a base column count sizes its implicit column to content:\n"
            .implode("\n", $offenders));
    }

    public function test_the_summary_row_label_is_allowed_to_shrink(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // The label takes the remaining space and may wrap; the amount never wraps.
        $this->assertMatchesRegularExpression('/\.ui-summary-label\s*\{[^}]*min-w-0/', $css);
        $this->assertMatchesRegularExpression('/\.ui-summary-value\s*\{[^}]*shrink-0/', $css);
        $this->assertMatchesRegularExpression('/\.ui-summary-value\s*\{[^}]*whitespace-nowrap/', $css);
    }

    public function test_wide_regions_scroll_inside_themselves(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // The stepper and every table are wider than a phone; each sits in its own scroll region
        // so the page body never scrolls sideways.
        $this->assertMatchesRegularExpression('/\.ui-scroll-x\s*\{[^}]*overflow-x-auto/', $css);

        foreach (['simulator/index.blade.php', 'admin/taxonomy.blade.php', 'admin/content-index.blade.php'] as $view) {
            $this->assertStringContainsString('ui-scroll-x', (string) file_get_contents(resource_path('views/'.$view)),
                "{$view} renders a wide region without a scroll container.");
        }
    }
}
