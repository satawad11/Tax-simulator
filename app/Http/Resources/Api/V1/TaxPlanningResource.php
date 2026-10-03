<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxPlanningResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return array_intersect_key($this->resource, array_flip([
            'tax_year', 'form_code', 'rule_version', 'before', 'after', 'difference',
            'estimated_tax_saving', 'warnings', 'recommendations', 'disclaimer',
        ]));
    }
}
