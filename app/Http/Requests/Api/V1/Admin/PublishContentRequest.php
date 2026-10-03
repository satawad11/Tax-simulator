<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\StrictApiRequest;

/**
 * Milestone 08. Publishing is immediate unless an explicit future moment is given, which
 * `ContentPost::scopePubliclyVisible()` honours without any job scheduler.
 */
class PublishContentRequest extends StrictApiRequest
{
    public function rules(): array
    {
        return ['published_at' => ['sometimes', 'date', 'after_or_equal:now']];
    }
}
