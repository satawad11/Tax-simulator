<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\StrictApiRequest;
use Illuminate\Validation\Rule;

/**
 * Milestone 09.1 — opening or describing a tax year.
 *
 * `year` is accepted only on create. Rule versions, forms and saved returns all point at the row,
 * so changing the year afterwards would silently relabel history rather than correct it; a year
 * entered wrongly is retired and the right one opened.
 */
class WriteTaxYearRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $patch = $this->isMethod('PATCH');

        return [
            // Buddhist-era years, bounded generously: wide enough never to be the reason a real
            // year cannot be opened, narrow enough to catch a Gregorian year typed by mistake.
            ...($patch ? [] : ['year' => ['required', 'integer', 'between:2500,2700', Rule::unique('tax_years', 'year')]]),
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'filing_start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'filing_end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:filing_start_date'],
            // A new year without forms cannot start a simulation at all, so the structure is
            // copied from an existing year unless the caller explicitly declines.
            ...($patch ? [] : ['copy_forms_from_year' => ['sometimes', 'nullable', 'integer',
                Rule::exists('tax_years', 'year')]]),
        ];
    }
}
