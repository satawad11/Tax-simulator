<?php

namespace Tests\Feature;

use App\Models\ContentPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone09WebUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_home_and_simulator_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('เริ่มทดลองคำนวณ');
        $this->get('/tax-simulator')->assertOk()->assertSee('เลือกแบบภาษีที่ต้องการทดลองคำนวณ');
        $this->get('/tax-simulator/pnd91')->assertOk()->assertSee('PND91')->assertSee('data-simulator', false);
        $this->get('/tax-simulator/pnd90')->assertOk()->assertSee('PND90')->assertSee('data-simulator', false);
        $this->get('/tax-simulator/unknown')->assertNotFound();
    }

    public function test_authentication_pages_render_without_exposing_tokens(): void
    {
        $this->get('/login')->assertOk()->assertSee('เข้าสู่ระบบ')->assertDontSee('Bearer');
        $this->get('/register')->assertOk()->assertSee('สมัครสมาชิก')->assertDontSee('Bearer');
    }

    public function test_dashboard_shell_contains_no_private_data_and_member_api_requires_authentication(): void
    {
        $this->get('/dashboard')->assertOk()->assertSee('กำลังตรวจสอบบัญชี')->assertDontSee('personal_access_tokens');
        $this->getJson('/api/v1/tax-returns')->assertUnauthorized();
    }

    public function test_member_can_load_dashboard_shell_and_access_the_member_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tax-returns')->assertOk();
    }

    public function test_member_routes_render_shared_noindex_shell(): void
    {
        foreach (['/dashboard/tax-returns', '/dashboard/tax-returns/1', '/dashboard/tax-returns/1/history', '/dashboard/tax-returns/1/planning'] as $url) {
            $this->get($url)->assertOk()->assertSee('noindex,nofollow', false);
        }
    }

    public function test_content_filters_never_reveal_drafts(): void
    {
        $author = User::factory()->create();
        ContentPost::factory()->create(['author_id' => $author->id, 'type' => 'guide', 'title' => 'คู่มือที่เผยแพร่', 'status' => 'published', 'published_at' => now(), 'first_published_at' => now()]);
        ContentPost::factory()->create(['author_id' => $author->id, 'type' => 'guide', 'title' => 'คู่มือฉบับร่าง', 'status' => 'draft', 'published_at' => null]);

        $this->get('/knowledge?q=คู่มือ')->assertOk()->assertSee('คู่มือที่เผยแพร่')->assertDontSee('คู่มือฉบับร่าง');
    }

    public function test_admin_and_member_api_boundaries_are_unchanged(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->getJson('/api/v1/admin')->assertUnauthorized();
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/admin')->assertForbidden();
    }
}
