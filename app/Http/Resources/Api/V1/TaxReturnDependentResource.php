<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxReturnDependentResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'relation_type' => $this->relation_type,
            'disabled_person_relationship' => $this->disabled_person_relationship,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            // M7.3 — the ใบแนบ item 3 facts and the taxpayer's eligibility declaration.
            'child_type' => $this->child_type, 'birth_order' => $this->birth_order,
            'eligible' => (bool) $this->eligible];
    }
}
