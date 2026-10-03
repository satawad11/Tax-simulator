<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Milestone 09.1 — the console's sidebar navigation.
 *
 * The console's menu moved from a row above the page to a rail beside it: grouped, collapsible on
 * a desktop, and a drawer on a phone. These assertions cover the parts that are easy to break
 * later and invisible until someone opens the console on a particular screen.
 */
class AdminSidebarLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function shell(string $path = '/admin'): string
    {
        return $this->get($path)->assertOk()->getContent();
    }

    public function test_the_sidebar_lists_every_console_page_in_groups(): void
    {
        $body = $this->shell();

        foreach (['admin.dashboard', 'admin.content', 'admin.taxonomy', 'admin.rule-versions',
            'admin.tax-sources', 'admin.audit-logs'] as $name) {
            $this->assertStringContainsString('href="'.route($name).'"', $body,
                "The sidebar has no link to {$name}.");
        }

        foreach (['ภาพรวม', 'เนื้อหา', 'กฎภาษี', 'ระบบ'] as $group) {
            $this->assertStringContainsString('admin-nav-group">'.$group, $body,
                "The sidebar has no \"{$group}\" group.");
        }
    }

    public function test_the_current_page_is_marked_in_the_sidebar(): void
    {
        // Marked by weight and a filled background, and announced by aria-current — never colour
        // alone, and never left to the reader to work out from the URL.
        $body = $this->shell('/admin/tax-sources');

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*\/admin\/tax-sources"[^>]*class="[^"]*admin-nav-item-active[^"]*"[^>]*aria-current="page"/',
            $body);
    }

    public function test_the_sidebar_is_a_drawer_with_a_trigger_and_a_backdrop(): void
    {
        $body = $this->shell();

        // The trigger states what it controls and whether it is open, for a screen reader.
        $this->assertMatchesRegularExpression(
            '/data-sidebar-open[^>]*aria-controls="admin-sidebar"[^>]*aria-expanded="false"/', $body);
        $this->assertStringContainsString('data-sidebar-backdrop', $body);
        $this->assertStringContainsString('data-sidebar-close', $body);
        // The backdrop must not be announced; it is a scrim, not content.
        $this->assertMatchesRegularExpression('/data-sidebar-backdrop[^>]*aria-hidden="true"/', $body);
    }

    public function test_the_collapse_control_exists_and_is_desktop_only(): void
    {
        $body = $this->shell();

        $this->assertMatchesRegularExpression(
            '/data-sidebar-collapse[^>]*class="[^"]*lg:flex[^"]*"/', $body,
            'Collapsing is a desktop affordance; on a phone the drawer simply closes.');
        $this->assertStringContainsString('data-sidebar-collapse-label', $body);
    }

    public function test_the_remembered_state_is_applied_before_first_paint(): void
    {
        $body = $this->shell();

        // Read in the head, so a collapsed rail is never drawn wide and then snapped narrow.
        $this->assertStringContainsString("localStorage.getItem('tax-simulator.admin-sidebar')", $body);
        $this->assertStringContainsString('data-sidebar-collapsed', $body);
    }

    public function test_the_rail_can_actually_be_narrow(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // A flex item's automatic minimum size is its content's min-content width, so without
        // min-w-0 the rail is silently inflated back to the width of the longest menu label.
        $this->assertMatchesRegularExpression('/\.admin-sidebar\s*\{[^}]*min-w-0/s', $css);
        $this->assertMatchesRegularExpression('/\.admin-sidebar\s*\{[^}]*shrink-0/s', $css);

        $script = (string) file_get_contents(resource_path('js/admin/sidebar.js'));
        $this->assertStringContainsString("collapsed: '4.75rem'", $script);
        // The rail width is only meaningful above the breakpoint; below it the drawer owns it.
        $this->assertStringContainsString("sidebar.style.removeProperty('width')", $script);
    }

    public function test_the_drawer_can_always_be_closed(): void
    {
        $script = (string) file_get_contents(resource_path('js/admin/sidebar.js'));

        // Escape, the backdrop, following a link, and growing past the breakpoint — a drawer left
        // open while the window is widened would otherwise sit there as a stuck overlay.
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString("backdrop?.addEventListener('click'", $script);
        $this->assertStringContainsString("event.target.closest('a')", $script);
        $this->assertStringContainsString("desktop.addEventListener('change'", $script);
        // Focus must follow the drawer, or it covers the page and leaves the keyboard behind it.
        $this->assertStringContainsString("sidebar.querySelector('a, button')?.focus()", $script);
    }

    public function test_the_console_shell_still_carries_no_protected_data(): void
    {
        // The sidebar is markup; every value on every page still comes from the authorised API.
        $body = $this->shell();

        $this->assertStringNotContainsString('tax_simulator', $body);
        $this->assertMatchesRegularExpression('/<aside[^>]*data-visible-to="admin"[^>]*hidden/', $body,
            'The sidebar itself is offered to administrators only.');
    }
}
