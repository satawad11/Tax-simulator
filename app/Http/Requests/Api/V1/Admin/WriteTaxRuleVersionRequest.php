<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\StrictApiRequest;
use Illuminate\Validation\Rule;

/**
 * Milestone 08. `status` and `published_at` are absent: a version is created as a draft and
 * becomes published only through the publish action, after validation.
 */
class WriteTaxRuleVersionRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $patch = $this->isMethod('PATCH');

        return [
            ...($patch ? [] : ['tax_year' => ['required', 'integer', Rule::exists('tax_years', 'year')]]),
            'version' => [$patch ? 'sometimes' : 'required', 'string', 'max:50', 'regex:/^[0-9A-Za-z._-]+$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'source_reference' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
