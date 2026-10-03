<?php

namespace App\Services\Admin;

use App\Models\TaxSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The registry of repository-approved documents a tax rule may cite.
 *
 * Milestone 08. A source is never fetched from the internet: `file_path` names a file inside
 * this repository, which is the only basis a production numeric rule may rest on.
 */
class TaxSourceService
{
    public function __construct(private AdminAuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): TaxSource
    {
        return DB::transaction(function () use ($actor, $data): TaxSource {
            $source = TaxSource::create([...$this->attributes($data), 'created_by' => $actor->id]);
            $this->audit->record($actor, AdminAuditService::TAX_SOURCE_CREATED, 'tax_source', $source->id,
                'Registered tax source '.$source->code, null, $source->only(['code', 'title', 'source_type', 'file_path']));

            return $source;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, TaxSource $source, array $data): TaxSource
    {
        return DB::transaction(function () use ($actor, $source, $data): TaxSource {
            $before = $source->only(['code', 'title', 'source_type', 'file_path', 'active']);
            $source->fill($this->attributes($data))->save();
            $this->audit->record($actor, AdminAuditService::TAX_SOURCE_UPDATED, 'tax_source', $source->id,
                'Updated tax source '.$source->code, $before,
                $source->only(['code', 'title', 'source_type', 'file_path', 'active']));

            return $source->fresh();
        });
    }

    /**
     * Deactivation, not deletion, whenever a published rule cites the source.
     *
     * Evidence for a published rule must stay readable for as long as that rule exists; a
     * calculation whose citation has been deleted cannot be audited.
     */
    public function delete(User $actor, TaxSource $source): TaxSource
    {
        return DB::transaction(function () use ($actor, $source): TaxSource {
            if ($source->isCitedByPublishedRule()) {
                throw ValidationException::withMessages([
                    'id' => 'TAX_SOURCE_CITED_BY_PUBLISHED_RULE: deactivate it instead; a published rule cites this source.',
                ]);
            }
            if ($source->ruleSources()->exists()) {
                $source->active = false;
                $source->save();
                $this->audit->record($actor, AdminAuditService::TAX_SOURCE_ARCHIVED, 'tax_source', $source->id,
                    'Deactivated tax source '.$source->code.' (cited by a draft rule)', ['active' => true], ['active' => false]);

                return $source;
            }
            $source->active = false;
            $source->save();
            $this->audit->record($actor, AdminAuditService::TAX_SOURCE_ARCHIVED, 'tax_source', $source->id,
                'Deactivated tax source '.$source->code, ['active' => true], ['active' => false]);

            return $source;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['code', 'title', 'source_type', 'file_path',
            'description', 'tax_year_id', 'document_date', 'active']));
    }
}
