<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContentPost;
use App\Services\Content\ContentBodyFormat;
use Illuminate\Contracts\View\View;

/**
 * Previewing a post before it is published.
 *
 * Until this, `ContentService::publicQuery()` excluded drafts and nothing else could render an
 * article, so `/article/{slug}` returned 404 for anything unpublished and **publishing was how an
 * author saw their own work**. On a product whose published content is the part readers are asked
 * to trust, that is the wrong way round — and the structured-text body makes it sharper, because
 * an author writing `#` headings and `-` lists had no way to check they parsed as intended.
 *
 * Two things this deliberately does not do:
 *
 * It does not touch `publicQuery()`. That method is the single definition of what the public can
 * see, and every listing, feed and API response depends on it meaning exactly one thing. A
 * `withDrafts()` flag on it would put the burden of remembering on every future caller. This
 * controller reads the post directly instead, and the public query keeps its one meaning.
 *
 * It does not authenticate. The link arrives as a plain browser GET — often pasted to a colleague
 * for a second opinion — so the signature on the URL is the authorisation, exactly as the email
 * verification link works. The signature is minted only for an administrator, by
 * `AdminContentController::previewUrl()`, and it expires.
 */
class ContentPreviewController extends Controller
{
    public function show(ContentPost $content): View
    {
        return view('content.show', [
            'post' => $content->load(['category', 'taxYear', 'tags']),
            'blocks' => ContentBodyFormat::blocks((string) $content->content),
            /*
             * The banner is not decoration. A preview of a published post looks identical to the
             * live page, and an administrator who mistook one for the other could believe an edit
             * was live when it was still a draft. The page says which it is.
             */
            'preview' => true,
        ]);
    }
}
