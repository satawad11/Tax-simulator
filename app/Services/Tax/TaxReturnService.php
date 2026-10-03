<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\Models\TaxReturn;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaxReturnService
{
    public const DETAIL = ['taxYear', 'taxForm', 'ruleVersion', 'profile', 'spouse', 'dependents', 'incomes.incomeType',
        'allowances.allowanceType', 'incomeExemptions', 'donations', 'withholdings', 'latestCalculation'];

    public function __construct(private TaxMetadataService $metadata) {}

    public function listing(User $user, array $filters): LengthAwarePaginator
    {
        $query = $user->taxReturns()->with(['taxYear', 'taxForm', 'ruleVersion']);
        foreach (['status', 'q', 'tax_year', 'form'] as $filter) {
            if (! isset($filters[$filter])) {
                continue;
            }
            match ($filter) {
                'status' => $query->where('status', $filters[$filter]),
                'q' => $query->where('name', 'like', '%'.$filters[$filter].'%'),
                'tax_year' => $query->whereHas('taxYear', fn ($q) => $q->where('year', $filters[$filter])),
                'form' => $query->whereHas('taxForm', fn ($q) => $q->where('code', $filters[$filter])),
            };
        }

        return $query->orderByDesc('updated_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 20);
    }

    public function create(User $user, array $data): TaxReturn
    {
        return DB::transaction(function () use ($user, $data): TaxReturn {
            $year = $this->metadata->context($data['tax_year']);
            $form = $this->metadata->form($year, $data['form_code']);

            return $user->taxReturns()->create(['tax_year_id' => $year->id, 'tax_form_id' => $form->id,
                'rule_version_id' => $year->publishedRuleVersion->id, 'name' => $data['name'], 'status' => 'draft', 'current_step' => 1])
                ->load(self::DETAIL);
        });
    }

    public function detail(TaxReturn $return): TaxReturn
    {
        Gate::authorize('view', $return);

        return $return->load(self::DETAIL);
    }

    public function locked(TaxReturn $return): TaxReturn
    {
        $locked = TaxReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
        Gate::authorize('view', $locked);

        return $locked;
    }

    public function assertDraft(TaxReturn $return): void
    {
        if ($return->status !== 'draft') {
            throw new TaxMetadataConflictException('Completed or archived simulations are read-only. Duplicate to a new draft.');
        }
    }

    public function update(TaxReturn $return, array $data): TaxReturn
    {
        return DB::transaction(function () use ($return, $data): TaxReturn {
            $return = $this->locked($return);
            $return->update($data);

            return $this->detail($return);
        });
    }

    public function delete(TaxReturn $return): void
    {
        DB::transaction(function () use ($return): void {
            $this->locked($return)->delete();
        });
    }
}
