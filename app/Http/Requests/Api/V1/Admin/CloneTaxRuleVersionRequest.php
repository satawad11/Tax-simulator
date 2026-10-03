<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\StrictApiRequest;
use Illuminate\Validation\Rule;

/** Milestone 08. Cloning always produces a draft; the caller names it and its destination year. */
class CloneTaxRuleVersionRequest extends StrictApiRequest
{
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:50', 'regex:/^[0-9A-Za-z._-]+$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            // M9.1 — the year to clone into. Omitted, the draft stays in the source's own year;
            // given, it is how a baseline is carried forward when next year's rates change.
            'tax_year' => ['sometimes', 'nullable', 'integer', Rule::exists('tax_years', 'year')],
        ];
    }
}
