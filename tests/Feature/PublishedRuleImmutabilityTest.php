<?php

namespace Tests\Feature;

use App\Models\AllowanceRule;
use App\Models\AllowanceType;
use App\Models\DonationRule;
use App\Models\ExpenseRule;
use App\Models\IncomeRule;
use App\Models\IncomeType;
use App\Models\RecommendationRule;
use App\Models\TaxBracket;
use App\Models\TaxRuleVersion;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublishedRuleImmutabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected $seed = true;

    private function publish(TaxRuleVersion $version): void
    {
        $version->status = 'published';
        $version->save();
    }

    public function test_drafts_can_be_edited_then_published_once(): void
    {
        $version = TaxRuleVersion::factory()->create();
        $version->update(['description' => 'Synthetic test version']);

        $this->publish($version);

        $this->assertSame('published', $version->fresh()->status);
        $this->assertNotNull($version->fresh()->published_at);
    }

    public function test_published_version_cannot_be_quietly_changed_back_to_draft(): void
    {
        $version = TaxRuleVersion::factory()->create();
        $this->publish($version);
        $version->status = 'draft';

        $this->expectException(LogicException::class);
        $version->saveQuietly();
    }

    public function test_published_version_cannot_be_deleted(): void
    {
        $version = TaxRuleVersion::factory()->create();
        $this->publish($version);

        $this->expectException(LogicException::class);
        $version->deleteQuietly();
    }

    public function test_stale_draft_instance_cannot_overwrite_a_published_version(): void
    {
        $version = TaxRuleVersion::factory()->create();
        $stale = TaxRuleVersion::findOrFail($version->id);
        $this->publish($version);

        $this->expectException(LogicException::class);
        $stale->update(['description' => 'Stale write']);
    }

    public static function ruleModels(): array
    {
        return [
            'bracket' => [TaxBracket::class], 'income' => [IncomeRule::class],
            'expense' => [ExpenseRule::class], 'allowance' => [AllowanceRule::class],
            'donation' => [DonationRule::class], 'recommendation' => [RecommendationRule::class],
        ];
    }

    private function attributesFor(string $model, TaxRuleVersion $version): array
    {
        $attributes = ['tax_year_id' => $version->tax_year_id, 'rule_version_id' => $version->id];

        return $attributes + match ($model) {
            TaxBracket::class => ['position' => 1, 'lower_bound' => '0.00', 'rate' => '7.1234'],
            IncomeRule::class => ['code' => 'synthetic-income', 'name' => 'Synthetic rule', 'income_type_id' => IncomeType::firstOrFail()->id],
            ExpenseRule::class => ['code' => 'synthetic-expense', 'income_type_id' => IncomeType::firstOrFail()->id],
            AllowanceRule::class => ['code' => 'synthetic-allowance', 'allowance_type_id' => AllowanceType::firstOrFail()->id],
            DonationRule::class => ['code' => 'synthetic-donation'],
            RecommendationRule::class => ['code' => 'synthetic-recommendation'],
        };
    }

    #[DataProvider('ruleModels')]
    public function test_published_child_rules_reject_updates(string $model): void
    {
        $version = TaxRuleVersion::factory()->create();
        $rule = $model::create($this->attributesFor($model, $version));
        $this->publish($version);

        $this->expectException(LogicException::class);
        $rule->update(['source_reference' => 'Unapproved replacement']);
    }

    #[DataProvider('ruleModels')]
    public function test_published_child_rules_reject_deletions(string $model): void
    {
        $version = TaxRuleVersion::factory()->create();
        $rule = $model::create($this->attributesFor($model, $version));
        $this->publish($version);

        $this->expectException(LogicException::class);
        $rule->delete();
    }

    #[DataProvider('ruleModels')]
    public function test_published_versions_reject_new_child_rules(string $model): void
    {
        $version = TaxRuleVersion::factory()->create();
        $attributes = $this->attributesFor($model, $version);
        $this->publish($version);

        $this->expectException(LogicException::class);
        $model::create($attributes);
    }

    public function test_published_rules_cannot_be_moved_to_a_draft_version(): void
    {
        $version = TaxRuleVersion::factory()->create();
        $rule = TaxBracket::create($this->attributesFor(TaxBracket::class, $version));
        $draft = TaxRuleVersion::factory()->create(['tax_year_id' => $version->tax_year_id]);
        $this->publish($version);

        $this->expectException(LogicException::class);
        $rule->update(['rule_version_id' => $draft->id]);
    }

    public function test_draft_rules_cannot_be_moved_into_a_published_version(): void
    {
        $version = TaxRuleVersion::factory()->create();
        $draft = TaxRuleVersion::factory()->create(['tax_year_id' => $version->tax_year_id]);
        $rule = TaxBracket::create($this->attributesFor(TaxBracket::class, $draft));
        $this->publish($version);

        $this->expectException(LogicException::class);
        $rule->update(['rule_version_id' => $version->id]);
    }

    public static function bulkOperations(): array
    {
        return ['update' => ['update'], 'delete' => ['delete'], 'upsert' => ['upsert'], 'insert' => ['insert'], 'increment' => ['increment'], 'truncate' => ['truncate']];
    }

    #[DataProvider('bulkOperations')]
    public function test_bulk_writes_cannot_bypass_publication_protection(string $operation): void
    {
        $version = TaxRuleVersion::factory()->create();
        $this->publish($version);
        $query = TaxRuleVersion::whereKey($version->id);

        $this->expectException(LogicException::class);
        match ($operation) {
            'update' => $query->update(['status' => 'draft']),
            'delete' => $query->delete(),
            'upsert' => $query->upsert([['id' => $version->id, 'status' => 'draft']], ['id'], ['status']),
            'insert' => $query->insert(['tax_year_id' => $version->tax_year_id, 'version' => 'unsafe']),
            'increment' => $query->increment('id'),
            'truncate' => $query->truncate(),
        };
    }
}
