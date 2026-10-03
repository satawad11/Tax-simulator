<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\StrictApiRequest;
use App\Models\ContentPost;
use App\Services\Content\ContentBodyFormat;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Milestone 08. `status`, `published_at`, `author_id` and `published_by` are absent on purpose:
 * the author comes from the authenticated admin and the status from a workflow action, so a
 * request body can never set either. StrictApiRequest rejects any field not listed here.
 */
class WriteContentRequest extends StrictApiRequest
{
    /**
     * Phase 3 — accept the `type` this API itself hands out.
     *
     * `AdminContentResource` emits `type` upper-cased for display while `ContentPost::TYPES` is
     * lower case, so a client that read a post and sent it straight back was refused for a field
     * it had not touched. The console did exactly that: editing any existing post left the type
     * select blank and saving failed with a 422 on `type`.
     *
     * Normalising on the way in fixes the round trip for every client without changing what the
     * API emits, so no documented response shape moves.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('type'))) {
            $this->merge(['type' => mb_strtolower(trim($this->input('type')))]);
        }
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $id = $this->route('content');

        return [
            'type' => [$required, Rule::in(ContentPost::TYPES)],
            'title' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('content_posts', 'slug')->ignore($id)->whereNull('deleted_at')],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'body' => [$required, 'string', 'max:200000'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('content_categories', 'id')],
            'tag_ids' => ['sometimes', 'array', 'max:20'],
            'tag_ids.*' => ['integer', Rule::exists('content_tags', 'id')],
            'tax_year_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tax_years', 'id')],
            'featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:300'],
            /*
             * Phase 3 — attribution, the two of the three dormant columns worth keeping.
             *
             * `source_name` and `source_url` were fillable on the model but absent from this
             * request and every view, so nothing could set or show them. On a tax-news item
             * naming the announcement it came from is exactly what a reader needs, and it needs
             * no upload story — unlike `cover_image`, which is dropped rather than half-built.
             *
             * `url` alone would accept `javascript:`; the scheme list is what makes it safe to
             * render as a link.
             */
            'source_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            $body = $this->input('body');
            // The body is structured plain text, never markup. See ContentBodyFormat for why
            // this project refuses HTML rather than trying to sanitize it.
            if (is_string($body) && ! ContentBodyFormat::isSafe($body)) {
                $validator->errors()->add('body',
                    'CONTENT_BODY_MARKUP_REJECTED: the body is structured plain text. HTML tags, javascript: URLs and event handlers are not accepted.');
            }
            foreach (['excerpt', 'meta_title', 'meta_description', 'title'] as $field) {
                $value = $this->input($field);
                if (is_string($value) && ! ContentBodyFormat::isSafe($value)) {
                    $validator->errors()->add($field, 'CONTENT_BODY_MARKUP_REJECTED: markup is not accepted in this field.');
                }
            }
        });
    }
}
