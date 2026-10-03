<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class TaxBracketCollectionResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return ['tax_year' => $this->year, 'rule_version' => $this->publishedRuleVersion->version,
            'brackets' => TaxBracketResource::collection($this->publishedRuleVersion->brackets)];
    }
}
