<?php

namespace Tests\Feature;

use App\Models\TaxRuleVersion;
use App\Models\User;
use App\Services\Admin\DraftRuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Milestone 09.1 — the console's rule editor must offer exactly the fields the API accepts.
 *
 * `DraftRuleRegistry` is the authority on what may be written to a draft rule. The console has to
 * describe those same fields to render a form, and a second copy of a field list is a copy that
 * drifts: a field added to the registry but not the form is a rule an administrator silently
 * cannot set, and one in the form but not the registry is a control that fails on submit.
 *
 * This test compares the two and fails on either kind of drift, which is what makes keeping the
 * spec in JavaScript acceptable at all.
 */
class AdminDraftRuleFieldParityTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** @return array<string, array{string}> */
    public static function resources(): array
    {
        return array_map(fn (string $resource): array => [$resource], array_combine(
            array_keys(DraftRuleRegistry::ENTITIES),
            array_keys(DraftRuleRegistry::ENTITIES),
        ));
    }

    /** @return array<string, list<string>> resource => field names the console renders */
    private function consoleFields(): array
    {
        $source = (string) file_get_contents(resource_path('js/admin/rule-fields.js'));

        // The spec is a plain object literal per resource; the field names are the `name:` keys
        // inside each block. Reading them textually keeps the test free of a JS runtime.
        preg_match('/export const RULE_FIELDS = \{(.*?)\n\};/s', $source, $match);
        $this->assertNotEmpty($match, 'RULE_FIELDS was not found in rule-fields.js.');

        $fields = [];
        $current = null;
        foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
            if (preg_match("/^    '([a-z-]+)':\s*\[/", $line, $resource) === 1) {
                $current = $resource[1];
                $fields[$current] = [];

                continue;
            }
            if ($current === null) {
                continue;
            }
            // A field is either an inline object with a name, or one of the shared constants.
            if (preg_match("/name: '([a-z_]+)'/", $line, $name) === 1) {
                $fields[$current][] = $name[1];
            } elseif (str_contains($line, 'SOURCE_REFERENCE')) {
                $fields[$current][] = 'source_reference';
            } elseif (preg_match('/^\s+ACTIVE,/', $line) === 1) {
                $fields[$current][] = 'active';
            }
        }

        return $fields;
    }

    #[DataProvider('resources')]
    public function test_the_console_offers_exactly_the_fields_the_registry_accepts(string $resource): void
    {
        $registryFields = array_keys(DraftRuleRegistry::rules($resource, 'required'));
        // Nested array rules ("member_allowance_type_ids.*") describe the element, not a field.
        $registryFields = array_values(array_filter($registryFields, fn (string $f): bool => ! str_contains($f, '.')));

        $consoleFields = $this->consoleFields()[$resource] ?? [];

        sort($registryFields);
        sort($consoleFields);

        $this->assertSame($registryFields, $consoleFields,
            "The console's field list for {$resource} has drifted from DraftRuleRegistry.");
    }

    #[DataProvider('resources')]
    public function test_the_registry_never_exposes_a_server_controlled_field(string $resource): void
    {
        $fields = array_keys(DraftRuleRegistry::rules($resource, 'required'));

        foreach (DraftRuleRegistry::serverControlled() as $protected) {
            $this->assertNotContains($protected, $fields,
                "{$resource} accepts the server-controlled field {$protected}.");
        }
    }

    public function test_the_reference_endpoint_returns_the_ids_the_pickers_need(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $data = $this->getJson('/api/v1/admin/rule-references')->assertOk()->json('data');

        $this->assertNotEmpty($data['income_types']);
        $this->assertNotEmpty($data['allowance_types']);
        foreach (['income_types', 'allowance_types'] as $group) {
            $this->assertSame(['id', 'code', 'name'], array_keys($data[$group][0]),
                "{$group} must expose exactly the id/code/name a picker needs.");
        }
    }

    public function test_a_member_cannot_read_the_reference_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/rule-references')->assertForbidden();
    }

    public function test_the_rule_editor_never_offers_editing_on_a_published_version(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $published = TaxRuleVersion::where('version', '2568.3')->sole();

        // The console decides whether to render controls from this flag, not from a guess.
        $this->getJson("/api/v1/admin/tax-rule-versions/{$published->id}/tax-brackets")
            ->assertOk()->assertJsonPath('data.editable', $published->status === 'draft');

        if ($published->status !== 'draft') {
            $this->postJson("/api/v1/admin/tax-rule-versions/{$published->id}/tax-brackets",
                ['sort_order' => 99, 'min_amount' => '0', 'rate' => '5'])->assertForbidden();
        }
    }
}
