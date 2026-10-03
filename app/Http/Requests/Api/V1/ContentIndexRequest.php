<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ContentPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Milestone 08. The public listing filters, and nothing else. */
class ContentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in([...ContentPost::TYPES, ...array_map('strtoupper', ContentPost::TYPES)])],
            'category' => ['sometimes', 'string', 'max:191'],
            'tag' => ['sometimes', 'string', 'max:191'],
            'tax_year' => ['sometimes', 'integer', 'min:2000', 'max:9999'],
            'q' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), array_keys($this->rules())) as $key) {
                $validator->errors()->add($key, 'Unknown query parameter.');
            }
        }];
    }
}
