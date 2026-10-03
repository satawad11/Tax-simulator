<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\Models\TaxRuleVersion;
use App\Models\TaxYear;

class PublishedTaxRuleResolver
{
    public function resolve(TaxYear $year): TaxRuleVersion
    {
        $versions = $year->ruleVersions()->where('status', 'published')->limit(2)->get();
        if ($versions->isEmpty()) {
            throw new TaxMetadataConflictException('No published rule version is available for this tax year.');
        }
        if ($versions->count() !== 1) {
            throw new TaxMetadataConflictException('Multiple published rule versions exist for this tax year.');
        }

        return $versions->sole();
    }
}
