<?php

namespace App\Services\Admin;

use App\Models\AllowanceCapGroup;
use App\Models\TaxRuleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD over the rule entities of a **draft** version.
 *
 * Milestone 08. Two independent guards stand between an admin and a published rule:
 *
 *   1. this service refuses the request when the version is not a draft, giving a clear 422;
 *   2. the models themselves refuse the write, because ProtectsPublishedRules throws on any
 *      insert, update or delete whose version is published — including a write that somehow
 *      bypassed guard 1.
 *
 * Guard 2 is the one that matters, and it predates M8. Guard 1 exists so an admin gets a
 * readable message instead of a 500.
 */
class DraftRuleService
{
    public function __construct(private AdminAuditService $audit, private TaxRuleVersionService $versions) {}

    /** @return Collection<int, Model> */
    public function list(TaxRuleVersion $version, string $resource)
    {
        $model = DraftRuleRegistry::model($resource);
        $query = $model::where('rule_version_id', $version->id);

        return $resource === 'tax-brackets'
            ? $query->orderBy('sort_order')->get()
            : $query->orderBy('id')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, TaxRuleVersion $version, string $resource, array $data): Model
    {
        $this->versions->assertDraft($version);

        return DB::transaction(function () use ($actor, $version, $resource, $data): Model {
            $model = DraftRuleRegistry::model($resource);
            $record = new $model($this->columns($resource, $data));
            $record->rule_version_id = $version->id;
            $record->tax_year_id = $version->tax_year_id;
            $record->save();
            $this->syncRelations($resource, $record, $data);

            $this->audit->record($actor, AdminAuditService::TAX_RULE_CREATED,
                DraftRuleRegistry::entityType($resource), $record->id,
                'Created '.$resource.' in draft version '.$version->version, null, $this->snapshot($record));

            return $record->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, TaxRuleVersion $version, string $resource, Model $record, array $data): Model
    {
        $this->versions->assertDraft($version);
        $this->assertBelongs($version, $record);

        return DB::transaction(function () use ($actor, $version, $resource, $record, $data): Model {
            $before = $this->snapshot($record);
            $record->fill($this->columns($resource, $data))->save();
            $this->syncRelations($resource, $record, $data);

            $this->audit->record($actor, AdminAuditService::TAX_RULE_UPDATED,
                DraftRuleRegistry::entityType($resource), $record->id,
                'Updated '.$resource.' in draft version '.$version->version, $before, $this->snapshot($record->fresh()));

            return $record->fresh();
        });
    }

    public function delete(User $actor, TaxRuleVersion $version, string $resource, Model $record): void
    {
        $this->versions->assertDraft($version);
        $this->assertBelongs($version, $record);

        DB::transaction(function () use ($actor, $version, $resource, $record): void {
            $before = $this->snapshot($record);
            $id = $record->id;
            $record->delete();
            $this->audit->record($actor, AdminAuditService::TAX_RULE_DELETED,
                DraftRuleRegistry::entityType($resource), $id,
                'Deleted '.$resource.' from draft version '.$version->version, $before, null);
        });
    }

    /**
     * Only the registry's declared fields, minus anything the route decides.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(string $resource, array $data): array
    {
        $allowed = array_keys(DraftRuleRegistry::rules($resource, 'sometimes'));
        $allowed = array_filter($allowed, fn (string $key): bool => ! str_contains($key, '.'));
        $columns = array_intersect_key($data, array_flip($allowed));
        $columns = array_diff_key($columns, array_flip(DraftRuleRegistry::serverControlled()));
        $columns = array_diff_key($columns, array_flip(DraftRuleRegistry::relationKeys($resource)));

        // Two legacy mirrors the M2 schema keeps alongside the canonical columns.
        if ($resource === 'allowance-rules' || $resource === 'expense-rules') {
            if (array_key_exists('maximum_amount', $columns)) {
                $columns['limit_amount'] = $columns['maximum_amount'];
            }
        }

        return $columns;
    }

    /** @param array<string, mixed> $data */
    private function syncRelations(string $resource, Model $record, array $data): void
    {
        if ($resource !== 'allowance-cap-groups' || ! array_key_exists('member_allowance_type_ids', $data)) {
            return;
        }
        $ids = array_values(array_unique(array_map('intval', $data['member_allowance_type_ids'])));
        $sync = [];
        foreach ($ids as $position => $id) {
            $sync[$id] = ['sort_order' => $position + 1];
        }
        /** @var AllowanceCapGroup $record */
        $record->allowanceTypes()->sync($sync);
    }

    private function assertBelongs(TaxRuleVersion $version, Model $record): void
    {
        if ((int) $record->rule_version_id !== (int) $version->id) {
            throw ValidationException::withMessages([
                'id' => 'RULE_NOT_IN_VERSION: this rule belongs to another rule version.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $record): array
    {
        return array_diff_key($record->getAttributes(), array_flip(['created_at', 'updated_at']));
    }
}
