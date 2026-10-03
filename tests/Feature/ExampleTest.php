<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertOk()->assertSee('ทดลองคำนวณภาษี')
            ->assertSee('เริ่มทดลองเลย')
            ->assertSeeInOrder(['หน้าแรก', 'ทดลองคำนวณภาษี', 'ความรู้ภาษี', 'ข่าวสาร', 'คำถามที่พบบ่อย', 'เข้าสู่ระบบ']);
    }
}
