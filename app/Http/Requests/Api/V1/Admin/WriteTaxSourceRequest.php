<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\StrictApiRequest;
use App\Models\TaxSource;
use Illuminate\Validation\Rule;

/**
 * Milestone 08. `file_path` is a repository path, not a URL: this project establishes a rule
 * only from a document it holds, and M8 introduces no external fetching.
 */
class WriteTaxSourceRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:100', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique('tax_sources', 'code')->ignore($this->route('taxSource'))],
            'title' => [$required, 'string', 'max:255'],
            'source_type' => [$required, Rule::in(TaxSource::TYPES)],
            'file_path' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:/^[A-Za-z0-9._\-\/\x{0E00}-\x{0E7F}]+$/u', 'not_regex:/(^|\/)\.\.(\/|$)/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'tax_year_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tax_years', 'id')],
            'document_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
