<?php

namespace App\Services\Tax;

use App\Models\TaxReturn;
use Illuminate\Support\Facades\DB;

class TaxReturnDuplicationService
{
    public function __construct(private TaxReturnService $returns) {}

    public function duplicate(TaxReturn $source, string $name): TaxReturn
    {
        return DB::transaction(function () use ($source, $name): TaxReturn {
            $source = $this->returns->locked($source)->load(['profile', 'spouse', 'dependents', 'incomes', 'allowances', 'donations', 'withholdings']);
            $copy = $source->replicate(['completed_at', 'deleted_at']);
            $copy->name = $name;
            $copy->status = 'draft';
            $copy->completed_at = null;
            $copy->save();
            foreach (['profile', 'spouse', 'dependents', 'incomes', 'allowances', 'donations', 'withholdings'] as $relation) {
                $records = in_array($relation, ['profile', 'spouse'], true) ? collect([$source->$relation])->filter() : $source->$relation;
                foreach ($records as $record) {
                    $child = $record->replicate();
                    $child->tax_return_id = $copy->id;
                    $child->save();
                }
            }

            return $copy->load(TaxReturnService::DETAIL);
        });
    }
}
