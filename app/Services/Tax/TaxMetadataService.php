<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\Models\AllowanceType;
use App\Models\IncomeType;
use App\Models\TaxForm;
use App\Models\TaxYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TaxMetadataService
{
    public function __construct(private PublishedTaxRuleResolver $resolver) {}

    /** @return Collection<int, TaxYear> */
    /**
     * The years a caller can actually simulate — not merely the years that exist.
     *
     * Opening next year's tax year is an administrative act that happens **months** before its
     * rates are published: `AdminTaxYearController` creates the year and copies the forms, and the
     * rule version is cloned and published later. In between, the year is active and has no
     * published baseline.
     *
     * This method listed it anyway. The wizard takes the newest year in the list, every metadata
     * request for it answered 409 through `PublishedTaxRuleResolver`, and the public simulator
     * stopped working the moment an administrator opened the next year — the exact thing the
     * tax-year workflow was built to make safe.
     *
     * A public metadata list means "these are the years you can calculate", so a year without a
     * published version does not belong in it. Requesting one directly still answers 409, which is
     * the truthful answer to a caller who named it; `/admin/tax-years` is unaffected and still
     * shows the year an administrator is preparing.
     */
    public function years(): Collection
    {
        return TaxYear::where('active', true)
            ->whereHas('ruleVersions', fn ($query) => $query->where('status', 'published'))
            ->orderByDesc('year')->get();
    }

    public function context(int $year): TaxYear
    {
        $record = TaxYear::where('year', $year)->where('active', true)->firstOrFail();

        return $record->setRelation('publishedRuleVersion', $this->resolver->resolve($record));
    }

    /** @return Collection<int, TaxForm> */
    public function forms(TaxYear $year): Collection
    {
        return $year->forms()->where('active', true)->orderBy('code')->get();
    }

    public function form(TaxYear $year, string $code): TaxForm
    {
        return $year->forms()->where('active', true)->where('code', $code)->firstOrFail();
    }

    /** @return Builder<IncomeType> */
    public function incomeTypes(TaxYear $year, ?string $form = null): Builder
    {
        $formId = $form === null ? null : $this->form($year, $form)->id;

        return IncomeType::query()->whereHas('taxForms', function (Builder $query) use ($year, $formId): void {
            $query->where('tax_year_id', $year->id)->where('active', true);
            if ($formId !== null) {
                $query->where('tax_forms.id', $formId);
            }
        })->orderBy('code');
    }

    public function incomeType(TaxYear $year, string $code): IncomeType
    {
        $type = $this->incomeTypes($year)->where('code', $code)->with([
            'incomeRules' => fn ($query) => $query->where('rule_version_id', $year->publishedRuleVersion->id),
            'expenseRules' => fn ($query) => $query->where('rule_version_id', $year->publishedRuleVersion->id)
                ->where('active', true)->whereNotNull('source_reference')->where('source_reference', '!=', ''),
        ])->firstOrFail();
        // ภ.ง.ด.90 prints several subcategories for some income types, so more than one rule
        // is expected; two rules for the *same* subcategory is the ambiguity worth refusing.
        $selectors = $type->expenseRules->map(fn ($rule): string => implode('|', [
            (string) $rule->income_subtype,
            (string) $rule->expense_activity,
            (string) $rule->holding_years_min,
            (string) $rule->holding_years_max,
        ]));
        if ($selectors->count() !== $selectors->unique()->count()) {
            throw new TaxMetadataConflictException('Multiple verified expense rules exist for one income subcategory.');
        }

        return $type;
    }

    /** @return Builder<AllowanceType> */
    public function allowances(TaxYear $year, ?string $category = null): Builder
    {
        return AllowanceType::where('is_active', true)
            ->when($category !== null, fn (Builder $query) => $query->where('category', $category))
            ->with(['allowanceRules' => fn ($query) => $query->where('rule_version_id', $year->publishedRuleVersion->id)
                ->where('active', true)->whereNotNull('source_reference')->where('source_reference', '!=', '')])
            ->orderBy('code');
    }

    /** @return Collection<int, AllowanceType> */
    public function allowanceList(TaxYear $year, ?string $category = null): Collection
    {
        $types = $this->allowances($year, $category)->get();
        foreach ($types as $type) {
            $this->assertSingleAllowanceRule($type);
        }

        return $types;
    }

    public function allowance(TaxYear $year, string $code): AllowanceType
    {
        $type = $this->allowances($year)->where('code', $code)->firstOrFail();
        $this->assertSingleAllowanceRule($type);

        return $type;
    }

    private function assertSingleAllowanceRule(AllowanceType $type): void
    {
        if ($type->allowanceRules->count() > 1) {
            throw new TaxMetadataConflictException('Multiple verified allowance rules exist for this allowance type.');
        }
    }
}
