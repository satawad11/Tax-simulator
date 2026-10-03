<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\ContentPost;
use App\Models\ContentTag;
use App\Models\TaxReturn;
use App\Models\TaxRuleVersion;
use App\Models\TaxSource;
use App\Models\User;
use Database\Seeders\CmsInitialDataSeeder;
use Database\Seeders\DevelopmentAccountSeeder;
use Database\Seeders\DevelopmentDemoDataSeeder;
use Database\Seeders\TaxBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Milestone 09.1 — the guarantees the development seeders make.
 *
 * These seeders write known accounts and synthetic tax returns, which makes their safety
 * properties worth asserting rather than describing: they refuse any environment that is not an
 * approved development one, they take their passwords from the environment with no fallback,
 * and rerunning them changes nothing.
 */
class DevelopmentSeedingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * On in-memory SQLite the schema is built once for the whole run, and the seed runs with that
     * migration. A class that migrates first without asking to be seeded therefore leaves every
     * later class without a baseline. This class sorts early alphabetically, so it opts in — the
     * same fix AllowanceCapEngineTest carries for the same reason.
     */
    protected $seed = true;

    private const ADMIN_PASSWORD = 'Synthetic-Admin-1!';

    private const MEMBER_PASSWORD = 'Synthetic-Member-1!';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('seeding.development_accounts.admin_password', self::ADMIN_PASSWORD);
        config()->set('seeding.development_accounts.member_password', self::MEMBER_PASSWORD);
    }

    private function asLocal(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
    }

    /**
     * Runs a seeder without going through the `db:seed` command.
     *
     * The command asks for confirmation in production, and that prompt — not the guard — is what
     * a test would otherwise hit. Resolving the seeder directly exercises exactly the code an
     * operator's `db:seed` would reach after confirming.
     *
     * @param  class-string  $seeder
     */
    private function runSeeder(string $seeder): void
    {
        $this->app->make($seeder)->setContainer($this->app)->run();
    }

    /** @return array<string, array{class-string}> */
    public static function developmentSeeders(): array
    {
        return [
            'accounts' => [DevelopmentAccountSeeder::class],
            'demo data' => [DevelopmentDemoDataSeeder::class],
        ];
    }

    /** @param  class-string  $seeder */
    #[DataProvider('developmentSeeders')]
    public function test_development_seeders_refuse_production(string $seeder): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('refuses to run in the "production" environment');

        $this->runSeeder($seeder);
    }

    /** @param  class-string  $seeder */
    #[DataProvider('developmentSeeders')]
    public function test_development_seeders_refuse_any_environment_that_is_not_approved(string $seeder): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');

        $this->expectException(RuntimeException::class);

        $this->runSeeder($seeder);
        $this->assertDatabaseMissing('users', ['email' => DevelopmentAccountSeeder::ADMIN_EMAIL]);
    }

    public function test_account_seeder_fails_rather_than_inventing_a_default_password(): void
    {
        $this->asLocal();
        config()->set('seeding.development_accounts.admin_password', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEV_ADMIN_PASSWORD is not set');

        $this->runSeeder(DevelopmentAccountSeeder::class);
    }

    public function test_accounts_are_created_with_the_right_roles_and_hashed_passwords(): void
    {
        $this->asLocal();
        $this->runSeeder(DevelopmentAccountSeeder::class);

        $admin = User::where('email', DevelopmentAccountSeeder::ADMIN_EMAIL)->sole();
        $member = User::where('email', DevelopmentAccountSeeder::MEMBER_EMAIL)->sole();

        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('ผู้ดูแลระบบทดสอบ', $admin->name);

        $this->assertSame(User::ROLE_MEMBER, $member->role);
        $this->assertFalse($member->isAdmin());
        $this->assertSame('ผู้ใช้งานทดสอบ', $member->name);

        foreach ([$admin, $member] as $user) {
            $this->assertNotSame(self::ADMIN_PASSWORD, $user->password);
            $this->assertNotSame(self::MEMBER_PASSWORD, $user->password);
            $this->assertTrue(str_starts_with($user->password, '$'), 'The stored password must be a hash.');
        }

        $this->assertTrue(Hash::check(self::ADMIN_PASSWORD, $admin->password));
        $this->assertTrue(Hash::check(self::MEMBER_PASSWORD, $member->password));
    }

    public function test_account_seeding_is_idempotent(): void
    {
        $this->asLocal();
        $this->runSeeder(DevelopmentAccountSeeder::class);
        $adminId = User::where('email', DevelopmentAccountSeeder::ADMIN_EMAIL)->value('id');

        $this->runSeeder(DevelopmentAccountSeeder::class);
        $this->runSeeder(DevelopmentAccountSeeder::class);

        $this->assertSame(1, User::where('email', DevelopmentAccountSeeder::ADMIN_EMAIL)->count());
        $this->assertSame(1, User::where('email', DevelopmentAccountSeeder::MEMBER_EMAIL)->count());
        $this->assertSame($adminId, User::where('email', DevelopmentAccountSeeder::ADMIN_EMAIL)->value('id'));
        $this->assertSame(User::ROLE_ADMIN, User::find($adminId)->role);
    }

    public function test_cms_seeding_produces_the_required_public_content_and_is_idempotent(): void
    {
        $this->seed(CmsInitialDataSeeder::class);

        $this->assertSame(5, ContentCategory::count());
        $this->assertSame(8, ContentTag::count());
        $this->assertSame(3, ContentPost::where('type', 'guide')->where('status', 'published')->count());
        $this->assertSame(3, ContentPost::where('type', 'article')->where('status', 'published')->count());
        $this->assertSame(2, ContentPost::where('type', 'news')->where('status', 'published')->count());
        $this->assertSame(6, ContentPost::where('type', 'faq')->where('status', 'published')->count());
        $this->assertSame(1, ContentPost::where('status', 'draft')->count());
        $this->assertGreaterThanOrEqual(3, ContentPost::where('featured', true)->where('status', 'published')->count());

        // Nothing seeded may carry markup; the body format is the same one the write API enforces.
        foreach (ContentPost::all() as $post) {
            $this->assertDoesNotMatchRegularExpression('/<[a-z!\/]/i', $post->content, $post->slug.' contains markup.');
            $this->assertNotNull($post->excerpt, $post->slug.' has no excerpt.');
        }

        $count = ContentPost::count();
        $this->seed(CmsInitialDataSeeder::class);
        $this->seed(CmsInitialDataSeeder::class);

        $this->assertSame($count, ContentPost::count());
        $this->assertSame(5, ContentCategory::count());
        $this->assertSame(8, ContentTag::count());
    }

    public function test_cms_seeding_leaves_an_administrator_edit_alone(): void
    {
        $this->seed(CmsInitialDataSeeder::class);
        $post = ContentPost::where('slug', 'article-pnd90-vs-pnd91')->sole();
        $post->forceFill(['title' => 'แก้ไขโดยผู้ดูแลระบบ', 'status' => 'draft', 'published_at' => null])->save();

        $this->seed(CmsInitialDataSeeder::class);

        $post->refresh();
        $this->assertSame('แก้ไขโดยผู้ดูแลระบบ', $post->title);
        $this->assertSame('draft', $post->status);
    }

    public function test_demo_data_is_synthetic_marked_and_idempotent(): void
    {
        $this->seed();
        $this->asLocal();
        $this->runSeeder(DevelopmentAccountSeeder::class);
        $this->runSeeder(DevelopmentDemoDataSeeder::class);

        $member = User::where('email', DevelopmentAccountSeeder::MEMBER_EMAIL)->sole();
        $returns = $member->taxReturns()->get();

        $this->assertCount(3, $returns);
        foreach ($returns as $return) {
            $this->assertStringStartsWith(DevelopmentDemoDataSeeder::MARKER, $return->name);
        }

        $drafts = $returns->where('status', 'draft');
        $this->assertCount(2, $drafts);
        $this->assertSame(['PND90', 'PND91'], $drafts->map(fn ($r) => $r->taxForm->code)->sort()->values()->all());

        $completed = $returns->firstWhere('status', 'completed');
        $this->assertNotNull($completed);
        // Two calculations, so the history page has more than a single row to render.
        $this->assertSame(2, $completed->calculations()->count());
        $this->assertContains($completed->calculations()->latest('id')->first()->result_status, ['PAYABLE', 'REFUND', 'ZERO']);

        $scenario = $returns->firstWhere('name', DevelopmentDemoDataSeeder::PND90_DRAFT)->scenarios()->sole();
        $this->assertSame(DevelopmentDemoDataSeeder::SCENARIO_NAME, $scenario->name);
        // The comparison was produced by the planning service, not assembled by the seeder.
        $this->assertNotNull($scenario->calculated_at);
        $this->assertNotNull($scenario->before_tax);
        $this->assertNotNull($scenario->after_tax);

        $calculations = $member->taxReturns()->withCount('calculations')->get()->sum('calculations_count');

        $this->runSeeder(DevelopmentDemoDataSeeder::class);
        $this->runSeeder(DevelopmentDemoDataSeeder::class);

        $this->assertCount(3, $member->taxReturns()->get());
        $this->assertSame($calculations, $member->taxReturns()->withCount('calculations')->get()->sum('calculations_count'));
        $this->assertSame(1, $member->taxReturns()->where('name', DevelopmentDemoDataSeeder::PND90_DRAFT)->sole()->scenarios()->count());
    }

    public function test_demo_seeding_never_touches_another_members_data(): void
    {
        $this->seed();
        $this->asLocal();
        $this->runSeeder(DevelopmentAccountSeeder::class);

        $other = User::factory()->create();
        $ownReturn = TaxReturn::factory()->create(['user_id' => $other->id, 'name' => 'ของผู้ใช้คนอื่น']);

        $this->runSeeder(DevelopmentDemoDataSeeder::class);

        $this->assertDatabaseHas('tax_returns', ['id' => $ownReturn->id, 'user_id' => $other->id, 'name' => 'ของผู้ใช้คนอื่น']);
        $this->assertSame(1, $other->taxReturns()->count());
    }

    public function test_demo_seeder_requires_the_account_seeder_first(): void
    {
        $this->seed();
        $this->asLocal();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('development member account is missing');

        $this->runSeeder(DevelopmentDemoDataSeeder::class);
    }

    public function test_baseline_seeder_verifies_without_writing_a_rule_row(): void
    {
        $this->seed();
        $version = TaxRuleVersion::where('version', TaxBaselineSeeder::BASELINE_VERSION)->sole();
        $before = [$version->status, $version->updated_at?->toIso8601String(), $version->brackets()->count()];

        $this->seed(TaxBaselineSeeder::class);

        $version->refresh();
        $this->assertSame($before, [$version->status, $version->updated_at?->toIso8601String(), $version->brackets()->count()]);
    }

    public function test_baseline_seeder_rejects_a_second_published_version(): void
    {
        $this->seed();
        $original = TaxRuleVersion::where('version', TaxBaselineSeeder::BASELINE_VERSION)->sole();
        // ProtectsPublishedRules forbids creating a row that is already published — which is the
        // point of the trait. The conflicting state is therefore written past the model, through
        // the query builder, because no supported path can produce it.
        DB::table('tax_rule_versions')->insert(['tax_year_id' => $original->tax_year_id, 'version' => '2568.98',
            'status' => 'published', 'description' => 'Synthetic conflicting version.',
            'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('published rule versions');

        $this->seed(TaxBaselineSeeder::class);
    }

    public function test_reference_seeding_registers_the_repository_approved_sources_only(): void
    {
        $this->seed();

        $sources = TaxSource::all();
        $this->assertCount(5, $sources);

        foreach ($sources as $source) {
            $this->assertStringStartsWith('docs/tax-source/', (string) $source->file_path);
            $this->assertContains($source->source_type, TaxSource::TYPES);
        }
    }
}
