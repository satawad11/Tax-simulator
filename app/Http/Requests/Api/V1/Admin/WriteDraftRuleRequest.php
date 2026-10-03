<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\StrictApiRequest;
use App\Services\Admin\DraftRuleRegistry;

/**
 * Milestone 08. One request for every draft rule entity, with the field list and validation
 * taken from DraftRuleRegistry, so the declaration of what an admin may write lives in exactly
 * one place. StrictApiRequest still rejects anything outside that list.
 */
class WriteDraftRuleRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $resource = (string) $this->route('resource');
        if (! DraftRuleRegistry::knows($resource)) {
            return [];
        }

        return DraftRuleRegistry::rules($resource, $this->isMethod('PATCH') ? 'sometimes' : 'required');
    }
}
