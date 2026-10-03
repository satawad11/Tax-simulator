<?php

use App\Http\Controllers\Web\AdminPageController;
use App\Http\Controllers\Web\ContentPageController;
use App\Http\Controllers\Web\ContentPreviewController;
use App\Http\Controllers\Web\EmailVerificationController;
use App\Http\Controllers\Web\MemberPageController;
use Illuminate\Support\Facades\Route;

// M9.1 — the home page renders the featured content strip the approved mockup shows, so it
// reads through ContentPageController rather than returning a static view.
Route::get('/', [ContentPageController::class, 'home'])->name('home');

Route::get('/tax-simulator', [MemberPageController::class, 'simulator'])->name('simulator.index');
Route::get('/tax-simulator/{form}', [MemberPageController::class, 'simulator'])
    ->whereIn('form', ['pnd90', 'pnd91'])->name('simulator.form');
Route::get('/login', [MemberPageController::class, 'login'])->name('login');
Route::get('/register', [MemberPageController::class, 'register'])->name('register');

/*
|--------------------------------------------------------------------------
| Phase 1 — account recovery
|--------------------------------------------------------------------------
|
| These pages render forms only; every decision is made by the API they call. The reset page takes
| the token from its own path because that is where the emailed link puts it, and the address from
| the query string because the broker verifies the pair.
|
| The verification route is `signed`: the link arrives in a mail client with no bearer token, so
| the signature on the URL is the authorisation. It is named `verification.verify` because that is
| the name the framework's own notification contract expects.
*/
Route::get('/password/forgot', [MemberPageController::class, 'passwordForgot'])->name('password.forgot');
// The broker's token is a SHA-256 HMAC in hex, so anything outside this alphabet is not a token
// that could ever verify. Refusing it at the route is better than rendering it and relying on
// escaping: nothing attacker-shaped reaches the page at all.
Route::get('/password/reset/{token}', [MemberPageController::class, 'passwordReset'])
    ->where('token', '[A-Za-z0-9]{1,255}')->name('password.reset');
Route::get('/account/password', [MemberPageController::class, 'passwordChange'])->name('password.change');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')->whereNumber('id')->name('verification.verify');

Route::get('/dashboard', [MemberPageController::class, 'dashboard'])->name('dashboard');
Route::get('/dashboard/tax-returns', [MemberPageController::class, 'returns'])->name('dashboard.returns');
// Phase 4 — the account settings page. `PUT /auth/me` has existed since M5 with nothing calling it.
Route::get('/dashboard/account', [MemberPageController::class, 'account'])->name('dashboard.account');
Route::get('/dashboard/tax-returns/{taxReturn}', [MemberPageController::class, 'returnDetail'])->whereNumber('taxReturn')->name('dashboard.return');
Route::get('/dashboard/tax-returns/{taxReturn}/history', [MemberPageController::class, 'history'])->whereNumber('taxReturn')->name('dashboard.history');
Route::get('/dashboard/tax-returns/{taxReturn}/planning', [MemberPageController::class, 'planning'])->whereNumber('taxReturn')->name('dashboard.planning');

/*
|--------------------------------------------------------------------------
| Milestone 08 — public content pages
|--------------------------------------------------------------------------
|
| Server-rendered from the same ContentService::publicQuery() the API reads, so a draft cannot
| appear here either. The simulator UI is untouched; these are the knowledge, news and FAQ pages
| the approved navigation already names.
*/
Route::get('/knowledge', [ContentPageController::class, 'knowledge'])->name('content.knowledge');
Route::get('/news', [ContentPageController::class, 'news'])->name('content.news');
Route::get('/faq', [ContentPageController::class, 'faq'])->name('content.faq');
Route::get('/article/{slug}', [ContentPageController::class, 'article'])->name('content.article');

/*
 * Draft preview.
 *
 * `signed` is the whole authorisation: the link is minted only for an administrator by
 * `GET /api/v1/admin/content/{content}/preview-url`, carries an expiry, and arrives here as a
 * plain browser GET with no bearer token — the same shape as the email verification link.
 *
 * Deliberately outside the `/article/{slug}` space: a preview is addressed by id, so it keeps
 * working while a draft's slug is still being edited, and no published URL is ever shadowed.
 */
Route::get('/content-preview/{content}', [ContentPreviewController::class, 'show'])
    ->middleware('signed')->whereNumber('content')->name('content.preview');

/*
|--------------------------------------------------------------------------
| Milestone 08 — admin console
|--------------------------------------------------------------------------
|
| These routes render the console shell only. Authentication and authorization are enforced by
| the API the console calls: a page here shows a sign-in prompt rather than data when the caller
| holds no admin token, and no page route can change anything by itself.
*/
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('login', [AdminPageController::class, 'login'])->name('login');
    Route::get('/', [AdminPageController::class, 'dashboard'])->name('dashboard');
    Route::get('content', [AdminPageController::class, 'content'])->name('content');
    Route::get('content/create', [AdminPageController::class, 'contentForm'])->name('content.create');
    Route::get('content/{content}/edit', [AdminPageController::class, 'contentForm'])->whereNumber('content')->name('content.edit');
    Route::get('tax-rule-versions', [AdminPageController::class, 'ruleVersions'])->name('rule-versions');
    Route::get('tax-rule-versions/{taxRuleVersion}', [AdminPageController::class, 'ruleVersion'])->whereNumber('taxRuleVersion')->name('rule-version');
    // M9.1 — the categories and tags the CMS already exposed over the API but had no page for.
    Route::get('taxonomy', [AdminPageController::class, 'taxonomy'])->name('taxonomy');
    // M9.1 — the audit trail the API has recorded since M8, with the filters it supports.
    Route::get('audit-logs', [AdminPageController::class, 'auditLogs'])->name('audit-logs');
    // Phase 2 — accounts. Until this, a second administrator could only be made in the database.
    Route::get('users', [AdminPageController::class, 'users'])->name('users');
    // M9.1 — opening a tax year, so a rate change next year is an administrative task.
    Route::get('tax-years', [AdminPageController::class, 'taxYears'])->name('tax-years');
    Route::get('tax-sources', [AdminPageController::class, 'taxSources'])->name('tax-sources');
});
