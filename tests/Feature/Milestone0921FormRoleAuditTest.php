<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Milestone0921FormRoleAuditTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: array<string, int>}>
     */
    public static function matrixRoleCounts(): array
    {
        return [
            'PND90' => [
                'docs/ui/PND90_FORM_COVERAGE_MATRIX_2568.md',
                [
                    'USER_INPUT' => 6,
                    'CONDITIONAL_INPUT' => 21,
                    'DERIVED' => 5,
                    'INFORMATIONAL' => 0,
                    'FILING_ONLY' => 2,
                    'UNSUPPORTED' => 7,
                ],
            ],
            'PND91' => [
                'docs/ui/PND91_FORM_COVERAGE_MATRIX_2568.md',
                [
                    'USER_INPUT' => 8,
                    'CONDITIONAL_INPUT' => 7,
                    'DERIVED' => 4,
                    'INFORMATIONAL' => 2,
                    'FILING_ONLY' => 2,
                    'UNSUPPORTED' => 5,
                ],
            ],
        ];
    }

    /** @param array<string, int> $expectedCounts */
    #[DataProvider('matrixRoleCounts')]
    public function test_every_matrix_row_has_one_valid_field_role(string $path, array $expectedCounts): void
    {
        $contents = file_get_contents(base_path($path));
        $this->assertIsString($contents);

        $lines = collect(preg_split('/\R/', $contents) ?: []);

        /*
         * The columns are resolved from the header rather than by position. The matrix has gained
         * columns since this audit was first written (the cardinality and required-condition
         * group sits between "Field role" and "User action"), and a positional assertion turns
         * every such addition into a false failure about a document that is in fact more
         * complete. What this test is really about is the content of four named columns.
         */
        $headerLine = $lines->first(fn (string $line): bool => str_starts_with($line, '| Source/page '));
        $this->assertIsString($headerLine, 'The coverage matrix has no header row.');
        $columns = array_flip(array_map('trim', explode('|', trim($headerLine, '|'))));

        foreach (['Coverage status', 'Field role', 'User action', 'System action'] as $required) {
            $this->assertArrayHasKey($required, $columns, "The matrix is missing the \"{$required}\" column.");
        }

        $rows = $lines->filter(
            fn (string $line): bool => str_starts_with($line, '| Form ') || str_starts_with($line, '| Instructions ')
        );
        $this->assertNotEmpty($rows, 'The coverage matrix has no source rows.');

        $actualCounts = array_fill_keys(array_keys($expectedCounts), 0);

        foreach ($rows as $row) {
            $cells = array_map('trim', explode('|', trim($row, '|')));
            $this->assertCount(count($columns), $cells, "Malformed coverage row: {$row}");
            $this->assertContains($cells[$columns['Coverage status']],
                ['IMPLEMENTED', 'MISSING_UI', 'MISSING_API', 'UNSUPPORTED', 'NEEDS_GUIDANCE', 'NOT_APPLICABLE']);
            $this->assertArrayHasKey($cells[$columns['Field role']], $actualCounts, "Invalid field role in row: {$row}");
            // Every row must say what the person does and what the system does; a blank here is
            // an unanswered question about the field, not a formatting slip.
            $this->assertNotSame('', $cells[$columns['User action']], "Row has no user action: {$row}");
            $this->assertNotSame('', $cells[$columns['System action']], "Row has no system action: {$row}");
            $actualCounts[$cells[$columns['Field role']]]++;
        }

        $this->assertSame($expectedCounts, $actualCounts);
    }

    public function test_derived_tax_results_are_not_editable_inputs(): void
    {
        $wizard = file_get_contents(resource_path('js/simulator-wizard.js'));
        $result = file_get_contents(resource_path('js/tax-result.js'));

        foreach (['expense_deduction', 'remaining_income', 'net_income', 'progressive_tax', 'minimum_tax', 'tax_payable', 'refund'] as $field) {
            $this->assertStringNotContainsString("name=\"{$field}\"", $wizard);
        }

        $this->assertStringNotContainsString('<input', $result);
        $this->assertStringContainsString('result.form_code === \'PND90\' && result.minimum_tax', $result);
    }

    public function test_unsupported_items_have_no_enabled_numeric_entry(): void
    {
        $wizard = file_get_contents(resource_path('js/simulator-wizard.js'));

        $this->assertStringContainsString('value="foreign_tax_credit" disabled', $wizard);
        $this->assertStringContainsString('value="other_credit" disabled', $wizard);
        // A blocked allowance is shown as a reason, never as a field. M9.1 replaced the one
        // generic sentence this used to look for with each code's own source-cited reason, so the
        // guard now checks the card's shape — a reason paragraph and no input — rather than
        // wording that is free to improve.
        $this->assertMatchesRegularExpression('/data-coverage-reason="\$\{item\.code\}">\$\{escapeHtml\(item\.coverage\.reason\)\}<\/p><\/div>/', $wizard);
        $this->assertStringNotContainsString('data-allowance="${item.code}" data-coverage', $wizard);
        $this->assertStringNotContainsString('name="foreign_tax_credit"', $wizard);
        $this->assertStringNotContainsString('name="other_credit"', $wizard);
    }

    public function test_conditional_inputs_use_progressive_disclosure(): void
    {
        $view = file_get_contents(resource_path('views/simulator/index.blade.php'));
        $wizard = file_get_contents(resource_path('js/simulator-wizard.js'));

        $this->assertStringContainsString('data-spouse-fields hidden', $view);
        $this->assertStringContainsString("field.classList.toggle('hidden', event.target.value !== 'child')", $wizard);
        $this->assertStringContainsString("selection === 'actual' ? '' : 'hidden'", $wizard);
        $this->assertStringContainsString('data-subtype-slot', $wizard);
        $this->assertStringContainsString('name="expense_activity" required', $wizard);
        $this->assertStringContainsString('name="holding_years" type="number"', $wizard);
    }

    public function test_required_supported_fact_inputs_remain_available_for_both_forms(): void
    {
        $wizard = file_get_contents(resource_path('js/simulator-wizard.js'));

        $this->assertStringContainsString('name="gross_amount" inputmode="decimal" required', $wizard);
        $this->assertStringContainsString('name="exempt_amount" inputmode="decimal"', $wizard);
        $this->assertStringContainsString('name="donation_amount" inputmode="decimal"', $wizard);
        $this->assertStringContainsString('name="withholding_amount" inputmode="decimal"', $wizard);

        $this->get('/tax-simulator/pnd90')->assertOk();
        $this->get('/tax-simulator/pnd91')->assertOk();
    }
}
