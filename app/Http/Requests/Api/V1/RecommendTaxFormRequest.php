<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Tax\TaxMetadataService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecommendTaxFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'tax_year' => ['bail', 'required', 'integer', Rule::exists('tax_years', 'year')->where('active', true)],
            'income_types' => ['required', 'array', 'list', 'min:1'],
            'income_types.*' => ['bail', 'required', 'string', 'max:50', 'distinct:strict', Rule::exists('income_types', 'code')],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $metadata = app(TaxMetadataService::class);
            $year = $metadata->context((int) $this->input('tax_year'));
            $supported = $metadata->incomeTypes($year)->pluck('code')->all();
            foreach ($this->input('income_types') as $index => $code) {
                if (! in_array($code, $supported, true)) {
                    $validator->errors()->add('income_types.'.$index, 'This income type is not supported for the requested tax year.');
                }
            }
        }];
    }
}
