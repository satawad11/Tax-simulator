<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\AllowanceCapGroup;
use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Milestone 08 — tax rule administration, and the promises it must not break.
 *
 * Milestone 7.x closed with 2568.1 frozen. M8 gives administrators a way to change rules, which
 * is precisely when "published rules are immutable" stops being a convention and has to be a
 * mechanism. Every case here exists to prove one of three things:
 *
 *   a published version cannot be edited, by any route;
 *   a saved return keeps the version it was calculated under, forever;
 *   a new version only ever reaches a *new* calculation.
 */
class AdminTaxRuleAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function published(): TaxRuleVersion
    {
        return TaxRuleVersion::where('version', '2568.3')->firstOrFail();
    }

    private function cloneToDraft(string $version = '2568.2'): TaxRuleVersion
    {
        $id = $this->postJson('/api/v1/admin/tax-rule-versions/'.$this->published()->id.'/clone',
            ['version' => $version])->assertCreated()->json('data.id');

        return TaxRuleVersion::findOrFail($id);
    }

    // ------------------------------------------------- listing and creation

    public function test_an_admin_sees_every_version_and_creates_drafts_only(): void
    {
        $this->admin();

        $this->getJson('/api/v1/admin/tax-rule-versions')->assertOk()
            ->assertJsonPath('data.0.version', '2568.3')
            ->assertJsonPath('data.0.status', 'published')
            ->assertJsonPath('data.0.editable', false);

        $this->postJson('/api/v1/admin/tax-rule-versions',
            ['tax_year' => 2568, 'version' => '2568.9', 'description' => 'ฉบับร่างใหม่'])
            ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.editable', true);

        // The identifier must be unique within the tax year.
        $this->postJson('/api/v1/admin/tax-rule-versions', ['tax_year' => 2568, 'version' => '2568.3'])
            ->assertUnprocessable()->assertJsonValidationErrors('version');
    }

    public function test_a_version_cannot_be_created_already_published(): void
    {
        $this->admin();

        $this->postJson('/api/v1/admin/tax-rule-versions',
            ['tax_year' => 2568, 'version' => '2568.9', 'status' => 'published'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    // ------------------------------------------------- published immutability

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function publishedWrites(): array
    {
        return [
            'version metadata' => ['PATCH', '', ['description' => 'แก้ไขชุดกฎที่เผยแพร่แล้ว']],
            'add a bracket' => ['POST', '/tax-brackets', ['sort_order' => 9, 'min_amount' => 0, 'rate' => 99]],
            'add an expense rule' => ['POST', '/expense-rules', ['code' => 'HACK', 'income_type_id' => 1,
                'method' => 'percentage', 'percentage' => 99, 'source_reference' => 'x']],
            'add an allowance rule' => ['POST', '/allowance-rules', ['code' => 'HACK', 'allowance_type_id' => 1,
                'method' => 'fixed', 'fixed_amount' => 999999, 'source_reference' => 'x']],
            'add a cap group' => ['POST', '/allowance-cap-groups', ['code' => 'HACK', 'name' => 'x',
                'maximum_amount' => 1, 'source_reference' => 'x']],
            'add a donation rule' => ['POST', '/donation-rules', ['code' => 'HACK', 'name' => 'x',
                'donation_type' => 'general', 'multiplier' => 9, 'max_percentage' => 99, 'source_reference' => 'x']],
            'add a recommendation rule' => ['POST', '/recommendation-rules', ['code' => 'HACK', 'type' => 'x',
                'priority' => 'high', 'title' => 'x', 'message_template' => 'x']],
        ];
    }

    #[DataProvider('publishedWrites')]
    public function test_a_published_version_refuses_every_write_through_the_api(string $method, string $suffix, array $body): void
    {
        $this->admin();
        $version = $this->published();
        $before = DB::table('tax_rule_versions')->where('id', $version->id)->first();

        $this->json($method, '/api/v1/admin/tax-rule-versions/'.$version->id.$suffix, $body)
            ->assertForbidden();

        $this->assertEquals($before, DB::table('tax_rule_versions')->where('id', $version->id)->first());
    }

    public function test_a_published_rule_row_cannot_be_edited_or_deleted(): void
    {
        $this->admin();
        $version = $this->published();
        $bracket = $version->brackets()->orderBy('sort_order')->firstOrFail();
        $rate = $bracket->rate;

        $this->patchJson("/api/v1/admin/tax-rule-versions/{$version->id}/tax-brackets/{$bracket->id}", ['rate' => 99])
            ->assertForbidden();
        $this->deleteJson("/api/v1/admin/tax-rule-versions/{$version->id}/tax-brackets/{$bracket->id}")
            ->assertForbidden();

        $this->assertEquals($rate, $bracket->fresh()->rate);
    }

    /**
     * The API guard is the friendly layer; the model layer is the real one. Even a caller that
     * reached the model directly — a future controller, a console command — is refused.
     */
    public function test_the_model_layer_refuses_a_published_write_even_without_the_api(): void
    {
        $version = $this->published();

        foreach ([
            fn () => $version->update(['description' => 'x']),
            fn () => $version->brackets()->first()->update(['rate' => 99]),
            fn () => $version->allowanceRules()->first()->update(['maximum_amount' => '999999.00']),
            fn () => $version->expenseRules()->first()->delete(),
            fn () => AllowanceCapGroup::where('rule_version_id', $version->id)->first()->update(['maximum_amount' => '1.00']),
        ] as $index => $write) {
            try {
                $write();
                $this->fail('Write '.$index.' should have been refused by the model layer.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('immutable', $exception->getMessage());
            }
        }
    }

    // ------------------------------------------------- clone and draft editing

    public function test_cloning_copies_every_rule_table_and_nothing_else(): void
    {
        $admin = $this->admin();
        $source = $this->published();
        $draft = $this->cloneToDraft();

        $this->assertSame('draft', $draft->status);
        $this->assertSame($source->tax_year_id, $draft->tax_year_id);

        foreach (['brackets', 'incomeRules', 'expenseRules', 'allowanceRules', 'donationRules', 'recommendationRules'] as $relation) {
            $this->assertSame($source->$relation()->count(), $draft->$relation()->count(), $relation);
        }
        // `income_rules` is empty in the 2568.1 baseline — the engine reads income types and
        // form mappings instead — so only the tables that actually hold rules are checked for
        // content, to keep this from passing on an empty copy.
        foreach (['brackets', 'expenseRules', 'allowanceRules', 'donationRules', 'recommendationRules'] as $relation) {
            $this->assertGreaterThan(0, $draft->$relation()->count(), $relation);
        }
        $this->assertSame(
            AllowanceCapGroup::where('rule_version_id', $source->id)->count(),
            AllowanceCapGroup::where('rule_version_id', $draft->id)->count()
        );
        // Tiers follow their expense rule.
        $this->assertSame(2, DB::table('expense_rule_tiers')
            ->whereIn('expense_rule_id', $draft->expenseRules()->pluck('id'))->count());

        // Member data is never cloned.
        $this->assertSame(0, $draft->taxReturns()->count());
        $this->assertSame(0, $draft->calculations()->count());

        $this->assertDatabaseHas('admin_audit_logs',
            ['action' => 'RULE_VERSION_CLONED', 'actor_user_id' => $admin->id, 'entity_id' => $draft->id]);
    }

    public function test_a_draft_rule_can_be_created_updated_and_deleted(): void
    {
        $this->admin();
        $draft = $this->cloneToDraft();
        $rule = $draft->allowanceRules()->firstOrFail();

        $this->patchJson("/api/v1/admin/tax-rule-versions/{$draft->id}/allowance-rules/{$rule->id}",
            ['maximum_amount' => 12345])->assertOk();
        $this->assertEquals('12345.00', $rule->fresh()->maximum_amount);

        $created = $this->postJson("/api/v1/admin/tax-rule-versions/{$draft->id}/donation-rules", [
            'code' => 'SYNTHETIC_DONATION', 'name' => 'ทดสอบ', 'donation_type' => 'general',
            'multiplier' => 1, 'max_percentage' => 10, 'source_reference' => 'Synthetic test fixture',
        ])->assertCreated()->json('data.id');
        $this->assertSame($draft->id, (int) DB::table('donation_rules')->where('id', $created)->value('rule_version_id'));

        $this->deleteJson("/api/v1/admin/tax-rule-versions/{$draft->id}/donation-rules/{$created}")->assertNoContent();
        $this->assertDatabaseMissing('donation_rules', ['id' => $created]);

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'TAX_RULE_UPDATED']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'TAX_RULE_DELETED']);
    }

    public function test_a_draft_rule_cannot_be_moved_into_another_version(): void
    {
        $this->admin();
        $draft = $this->cloneToDraft();
        $other = $this->cloneToDraft('2568.97');
        $rule = $other->allowanceRules()->firstOrFail();

        // The rule exists, but not in the version named by the route.
        $this->patchJson("/api/v1/admin/tax-rule-versions/{$draft->id}/allowance-rules/{$rule->id}",
            ['maximum_amount' => 1])->assertUnprocessable()->assertJsonValidationErrors('id');
    }

    public function test_an_unknown_rule_resource_is_a_404_not_a_free_text_table_name(): void
    {
        $this->admin();
        $draft = $this->cloneToDraft();

        $this->getJson("/api/v1/admin/tax-rule-versions/{$draft->id}/users")->assertNotFound();
        $this->getJson("/api/v1/admin/tax-rule-versions/{$draft->id}/tax_returns")->assertNotFound();
    }

    // ------------------------------------------------- validation and publication

    public function test_a_clone_of_the_published_baseline_validates_cleanly(): void
    {
        $this->admin();
        $draft = $this->cloneToDraft();

        $this->postJson("/api/v1/admin/tax-rule-versions/{$draft->id}/validate")->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.errors', []);
    }

    /** @return array<string, array{callable-string|\Closure, string}> */
    public static function structuralDefects(): array
    {
        return [
            'bracket gap' => [fn (TaxRuleVersion $draft) => $draft->brackets()->orderBy('sort_order')->skip(1)->first()
                ->update(['min_amount' => '999999.00']), 'TAX_BRACKET_GAP'],
            'final bracket closed' => [fn (TaxRuleVersion $draft) => $draft->brackets()->orderByDesc('sort_order')->first()
                ->update(['max_amount' => '9000000.00']), 'TAX_BRACKET_NOT_OPEN_ENDED'],
            'allowance source missing' => [fn (TaxRuleVersion $draft) => $draft->allowanceRules()->first()
                ->update(['source_reference' => '']), 'ALLOWANCE_SOURCE_MISSING'],
            'percentage base invalid' => [fn (TaxRuleVersion $draft) => $draft->allowanceRules()
                ->where('method', 'percentage_limit')->first()->update(['percentage_base' => 'MADE_UP']),
                'PERCENTAGE_BASE_INVALID'],
            'recommendation condition unsupported' => [fn (TaxRuleVersion $draft) => $draft->recommendationRules()->first()
                ->update(['conditions' => ['not_a_condition' => true]]), 'RECOMMENDATION_CONDITION_UNSUPPORTED'],
        ];
    }

    #[DataProvider('structuralDefects')]
    public function test_a_structurally_invalid_draft_is_reported_and_cannot_be_published(\Closure $break, string $code): void
    {
        $this->admin();
        $draft = $this->cloneToDraft();
        $break($draft);

        $this->postJson("/api/v1/admin/tax-rule-versions/{$draft->id}/validate")->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonFragment(['code' => $code]);

        $this->postJson("/api/v1/admin/tax-rule-versions/{$draft->id}/publish")
            ->assertUnprocessable()->assertJsonValidationErrors('validation');
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_a_valid_draft_publishes_and_then_becomes_immutable_itself(): void
    {
        $admin = $this->admin();
        $draft = $this->cloneToDraft();

        $this->postJson("/api/v1/admin/tax-rule-versions/{$draft->id}/publish")->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.editable', false);
        $this->assertNotNull($draft->fresh()->published_at);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/tax-rule-versions/{$draft->id}", ['description' => 'x'])->assertForbidden();
        $this->assertDatabaseHas('admin_audit_logs',
            ['action' => 'RULE_VERSION_PUBLISHED', 'entity_id' => $draft->id, 'actor_user_id' => $admin->id]);
    }

    // ------------------------------------------------- the promise to history

    public function test_an_existing_return_keeps_its_rule_version_while_a_new_one_uses_the_newest(): void
    {
        $admin = $this->admin();
        $original = $this->published();

        // A member saves and completes a return under 2568.1.
        $member = User::factory()->create();
        Sanctum::actingAs($member);
        $returnId = $this->postJson('/api/v1/tax-returns',
            ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'ก่อนเผยแพร่ชุดกฎใหม่'])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/tax-returns/$returnId/incomes",
            ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00'])->assertCreated();
        $calculation = $this->postJson("/api/v1/tax-returns/$returnId/complete")->assertOk()->json('data.calculation');
        $this->assertSame('45500.00', $calculation['result']['amount']);
        $snapshot = DB::table('tax_calculations')->where('tax_return_id', $returnId)->value('result_snapshot');

        // The administrator publishes a new version whose top rate differs.
        Sanctum::actingAs($admin);
        $draft = $this->cloneToDraft();
        $top = $draft->brackets()->orderByDesc('sort_order')->firstOrFail();
        $this->patchJson("/api/v1/admin/tax-rule-versions/{$draft->id}/tax-brackets/{$top->id}", ['rate' => 40])
            ->assertOk();
        $this->postJson("/api/v1/admin/tax-rule-versions/{$draft->id}/publish")->assertOk();

        // The saved return is untouched: same version, same stored figures.
        $saved = TaxReturn::findOrFail($returnId);
        $this->assertSame($original->id, $saved->rule_version_id);
        $this->assertSame($snapshot, DB::table('tax_calculations')->where('tax_return_id', $returnId)->value('result_snapshot'));
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/tax-returns/$returnId")->assertOk()
            ->assertJsonPath('data.rule_version', $original->version);

        // A new return resolves the newest published version instead.
        $newId = $this->postJson('/api/v1/tax-returns',
            ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'หลังเผยแพร่ชุดกฎใหม่'])
            ->assertCreated()->json('data.id');
        $this->assertSame($draft->id, TaxReturn::findOrFail($newId)->rule_version_id);
    }

    public function test_a_version_referenced_by_history_is_reported_and_archiving_keeps_it_readable(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        Sanctum::actingAs($member);
        $returnId = $this->postJson('/api/v1/tax-returns',
            ['tax_year' => 2568, 'form_code' => 'PND91', 'name' => 'ประวัติ'])->assertCreated()->json('data.id');

        Sanctum::actingAs($admin);
        $version = $this->published();
        $this->getJson('/api/v1/admin/tax-rule-versions/'.$version->id)->assertOk()
            ->assertJsonPath('data.referenced_by_history', true);

        $this->postJson('/api/v1/admin/tax-rule-versions/'.$version->id.'/archive')->assertOk()
            ->assertJsonPath('data.status', 'archived');

        // Archived, not deleted: the saved return still resolves and still reads.
        $this->assertDatabaseHas('tax_rule_versions', ['id' => $version->id, 'status' => 'archived']);
        $this->assertSame($version->id, TaxReturn::findOrFail($returnId)->rule_version_id);
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/tax-returns/$returnId")->assertOk();
    }

    // ------------------------------------------------- tax sources

    public function test_tax_sources_are_managed_by_admins_and_protected_while_cited(): void
    {
        $admin = $this->admin();
        $id = $this->postJson('/api/v1/admin/tax-sources', [
            'code' => 'ADMIN_MANAGED_SOURCE_TEST', 'title' => 'เอกสารทดสอบการจัดการแหล่งข้อมูล',
            'source_type' => 'FILING_INSTRUCTIONS', 'file_path' => 'docs/tax-source/admin-managed-source-test.pdf',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/admin/tax-sources/$id", ['title' => 'ปรับชื่อเอกสาร'])->assertOk();

        // A citation from the published baseline blocks destruction.
        $version = $this->published();
        DB::table('tax_rule_sources')->insert([
            'rule_version_id' => $version->id, 'tax_source_id' => $id,
            'rule_entity_type' => 'allowance_rule', 'rule_entity_id' => $version->allowanceRules()->value('id'),
            'page_reference' => 'p.11', 'section_reference' => 'ใบแนบ item 10.4',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->deleteJson("/api/v1/admin/tax-sources/$id")->assertUnprocessable()
            ->assertJsonValidationErrors('id');
        $this->assertDatabaseHas('tax_sources', ['id' => $id, 'active' => true]);

        $this->getJson('/api/v1/admin/tax-sources')->assertOk()
            ->assertJsonPath('data.0.cited_by_published_rule', true)
            ->assertJsonPath('data.0.citation_count', 1);

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'TAX_SOURCE_CREATED', 'actor_user_id' => $admin->id]);
    }

    public function test_a_tax_source_path_cannot_escape_the_repository(): void
    {
        $this->admin();

        foreach (['../../etc/passwd', 'https://example.com/doc.pdf', 'docs/../../../secret'] as $path) {
            $this->postJson('/api/v1/admin/tax-sources', ['code' => 'X_'.strlen($path), 'title' => 'x',
                'source_type' => 'OFFICIAL_FORM', 'file_path' => $path])
                ->assertUnprocessable()->assertJsonValidationErrors('file_path');
        }
    }

    public function test_the_audit_trail_never_records_a_member_tax_payload(): void
    {
        $this->admin();
        $draft = $this->cloneToDraft();
        $this->postJson("/api/v1/admin/tax-rule-versions/{$draft->id}/publish")->assertOk();

        $encoded = AdminAuditLog::all()->map(fn (AdminAuditLog $log): string => json_encode(
            [$log->before_json, $log->after_json, $log->summary], JSON_THROW_ON_ERROR))->implode(' ');

        foreach (['input_snapshot', 'result_snapshot', 'calculation_trace', 'password', 'token'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }
}
