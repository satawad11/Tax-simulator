<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Narrowing the audit trail by who and by when.
 *
 * Action and entity type answer "what kind of thing happened". The questions this record exists
 * for — *what did this administrator change?* and *what happened around the time that rule was
 * published?* — could not be asked at all. Newest-first pagination made recent activity findable
 * and everything older effectively unreachable.
 */
class AuditTrailFilterTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    // In-memory SQLite seeds once, at the first migration of the run. This class sorts early
    // enough to be that first migration, so without this the whole suite runs unseeded.
    protected $seed = true;

    /**
     * `created_at` is not fillable on `AdminAuditLog` — the model states the timestamp itself so a
     * caller cannot backdate an audit entry, which is the right rule and means a test wanting a
     * specific time has to force it after the row exists.
     */
    private function entry(User $actor, string $summary, string $at): AdminAuditLog
    {
        $entry = AdminAuditLog::create(['actor_user_id' => $actor->id, 'action' => 'CONTENT_UPDATED',
            'entity_type' => 'content', 'entity_id' => 1, 'summary' => $summary]);
        $entry->forceFill(['created_at' => $at])->save();

        return $entry;
    }

    public function test_the_trail_can_be_narrowed_to_one_administrator(): void
    {
        $viewer = $this->actingAsAdmin(['name' => 'ผู้ตรวจสอบ']);
        $subject = $this->admin(['name' => 'ผู้ถูกตรวจสอบ']);
        $this->entry($subject, 'สิ่งที่ผู้ถูกตรวจสอบทำ', '2026-09-10 10:00:00');
        $this->entry($viewer, 'สิ่งที่ผู้ตรวจสอบทำ', '2026-09-10 11:00:00');

        $summaries = array_column(
            $this->getJson("/api/v1/admin/audit-logs?actor_user_id={$subject->id}")->assertOk()->json('data'),
            'summary');

        $this->assertSame(['สิ่งที่ผู้ถูกตรวจสอบทำ'], $summaries);
    }

    public function test_the_trail_can_be_narrowed_to_a_date_range(): void
    {
        $actor = $this->actingAsAdmin();
        $this->entry($actor, 'ก่อนช่วงเวลา', '2026-09-01 09:00:00');
        $this->entry($actor, 'ในช่วงเวลา', '2026-09-10 09:00:00');
        $this->entry($actor, 'หลังช่วงเวลา', '2026-09-20 09:00:00');

        $summaries = array_column(
            $this->getJson('/api/v1/admin/audit-logs?from=2026-09-05&to=2026-09-15')->assertOk()->json('data'),
            'summary');

        $this->assertSame(['ในช่วงเวลา'], $summaries);
    }

    public function test_the_last_day_of_the_range_is_included_whole(): void
    {
        // An operator naming a date means that day, not the midnight that starts it.
        $actor = $this->actingAsAdmin();
        $this->entry($actor, 'ปลายวัน', '2026-09-15 23:45:00');

        $this->getJson('/api/v1/admin/audit-logs?to=2026-09-15')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_first_day_of_the_range_is_included_from_its_start(): void
    {
        $actor = $this->actingAsAdmin();
        $this->entry($actor, 'ต้นวัน', '2026-09-05 00:10:00');

        $this->getJson('/api/v1/admin/audit-logs?from=2026-09-05')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filters_combine(): void
    {
        $viewer = $this->actingAsAdmin(['name' => 'ผู้ตรวจสอบ']);
        $other = $this->admin(['name' => 'อีกคน']);
        $this->entry($other, 'คนอื่น ในช่วง', '2026-09-10 09:00:00');
        $this->entry($viewer, 'ตรงทั้งคู่', '2026-09-10 09:00:00');
        $this->entry($viewer, 'ถูกคน นอกช่วง', '2026-09-25 09:00:00');

        $summaries = array_column($this->getJson(
            "/api/v1/admin/audit-logs?actor_user_id={$viewer->id}&from=2026-09-05&to=2026-09-15")
            ->assertOk()->json('data'), 'summary');

        $this->assertSame(['ตรงทั้งคู่'], $summaries);
    }

    public function test_an_unparseable_date_narrows_nothing(): void
    {
        // Better than emptying the page without saying why.
        $actor = $this->actingAsAdmin();
        $this->entry($actor, 'รายการเดียว', '2026-09-10 09:00:00');

        $this->getJson('/api/v1/admin/audit-logs?from=not-a-date')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_unknown_actor_returns_nothing_rather_than_everything(): void
    {
        $actor = $this->actingAsAdmin();
        $this->entry($actor, 'รายการเดียว', '2026-09-10 09:00:00');

        $this->getJson('/api/v1/admin/audit-logs?actor_user_id=999999')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_member_still_cannot_read_the_trail(): void
    {
        $this->actingAsMember();

        $this->getJson('/api/v1/admin/audit-logs?actor_user_id=1')->assertStatus(403);
    }

    // ---------------------------------------------------------------- console

    public function test_the_page_offers_the_two_new_filters(): void
    {
        $this->get('/admin/audit-logs')->assertOk()
            ->assertSee('name="from"', false)
            ->assertSee('name="to"', false)
            ->assertSee('name="actor_user_id"', false);
    }

    public function test_the_page_carries_an_actor_from_the_query_string(): void
    {
        // This is what the accounts page links into.
        $this->get('/admin/audit-logs?actor_user_id=42')->assertOk()->assertSee('value="42"', false);
    }

    public function test_the_accounts_page_links_into_the_filtered_trail(): void
    {
        $console = file_get_contents(resource_path('js/admin/console.js'));

        $this->assertStringContainsString('/admin/audit-logs?actor_user_id=${item.id}', $console);
        // Only for administrators — the trail records administrative changes, so a member's row
        // would always link to an empty page.
        $this->assertStringContainsString('if (item.is_admin) {', $console);
    }

    public function test_the_filtered_page_says_whose_trail_it_is(): void
    {
        // A filter you cannot see is a filter you cannot clear.
        $this->get('/admin/audit-logs?actor_user_id=42')->assertOk()
            ->assertSee('data-audit-actor', false)
            ->assertSee('กำลังแสดงเฉพาะรายการที่ทำโดยบัญชี')
            ->assertSee('แสดงทั้งหมด');
    }
}
