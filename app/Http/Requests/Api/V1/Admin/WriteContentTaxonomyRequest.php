<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\StrictApiRequest;
use Illuminate\Validation\Rule;

/** Milestone 08. Categories and tags share a shape; the table differs by route. */
class WriteContentTaxonomyRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $categories = $this->routeIs('*categories*');
        $table = $categories ? 'content_categories' : 'content_tags';
        $id = $this->route('category') ?? $this->route('tag');

        return [
            'name' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique($table, 'slug')->ignore($id)],
            'active' => ['sometimes', 'boolean'],
            ...($categories ? [
                'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            ] : []),
        ];
    }
}
