<?php

use App\Http\Controllers\Api\V1\Admin\AdminContentController;
use App\Http\Controllers\Api\V1\Admin\AdminContentTaxonomyController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminDraftRuleController;
use App\Http\Controllers\Api\V1\Admin\AdminTaxRuleVersionController;
use App\Http\Controllers\Api\V1\Admin\AdminTaxSourceController;
use App\Http\Controllers\Api\V1\Admin\AdminTaxYearController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AllowanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IncomeExemptionController;
use App\Http\Controllers\Api\V1\IncomeTypeController;
use App\Http\Controllers\Api\V1\MemberTaxCalculationController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\TaxBracketController;
use App\Http\Controllers\Api\V1\TaxCalculationController;
use App\Http\Controllers\Api\V1\TaxFormController;
use App\Http\Controllers\Api\V1\TaxFormRecommendationController;
use App\Http\Controllers\Api\V1\TaxPlanningController;
use App\Http\Controllers\Api\V1\TaxReturnAllowanceController;
use App\Http\Controllers\Api\V1\TaxReturnController;
use App\Http\Controllers\Api\V1\TaxReturnDependentController;
use App\Http\Controllers\Api\V1\TaxReturnDonationController;
use App\Http\Controllers\Api\V1\TaxReturnIncomeController;
use App\Http\Controllers\Api\V1\TaxReturnIncomeExemptionController;
use App\Http\Controllers\Api\V1\TaxReturnProfileController;
use App\Http\Controllers\Api\V1\TaxReturnRecommendationController;
use App\Http\Controllers\Api\V1\TaxReturnSpouseController;
use App\Http\Controllers\Api\V1\TaxReturnWithholdingController;
use App\Http\Controllers\Api\V1\TaxScenarioCalculationController;
use App\Http\Controllers\Api\V1\TaxScenarioController;
use App\Http\Controllers\Api\V1\TaxYearController;
use Illuminate\Support\Facades\Route;

Route::post('/tax/calculate', TaxCalculationController::class)->middleware('throttle:30,1')->name('api.v1.tax.calculate');
Route::post('/tax/plan', TaxPlanningController::class)->middleware('throttle:30,1')->name('api.v1.tax.plan');

Route::get('/health', HealthController::class)->name('api.v1.health');

Route::name('api.v1.')->group(function (): void {
    Route::get('/tax-years', [TaxYearController::class, 'index'])->name('tax-years.index');
    Route::prefix('/tax-years/{year}')->where(['year' => '[0-9]{1,5}'])->group(function (): void {
        Route::get('/', [TaxYearController::class, 'show'])->name('tax-years.show');
        Route::get('/forms', [TaxFormController::class, 'index'])->name('forms.index');
        Route::get('/forms/{form}', [TaxFormController::class, 'show'])->name('forms.show');
        Route::get('/income-types', [IncomeTypeController::class, 'index'])->name('income-types.index');
        Route::get('/income-types/{code}', [IncomeTypeController::class, 'show'])->name('income-types.show');
        Route::get('/allowances', [AllowanceController::class, 'index'])->name('allowances.index');
        Route::get('/allowances/{code}', [AllowanceController::class, 'show'])->name('allowances.show');
        // เงินได้ที่ได้รับยกเว้นหลังหักค่าใช้จ่าย — ใบแนบ ข้อ 13 และ ข้อ 20 ซึ่งเป็นคนละขั้นกับค่าลดหย่อน
        Route::get('/income-exemptions', [IncomeExemptionController::class, 'index'])->name('income-exemptions.index');
        Route::get('/tax-brackets', TaxBracketController::class)->name('tax-brackets.index');
    });
    Route::post('/tax/forms/recommend', TaxFormRecommendationController::class)->middleware('throttle:30,1')->name('forms.recommend');
});

Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

    /*
     * Phase 1 — account recovery.
     *
     * Both are public, because a member who cannot sign in is exactly who needs them, and both
     * are throttled harder than sign-in: each request costs an outbound email or a token guess.
     * `forgot` answers identically for a registered and an unregistered address, so the rate
     * limit is the only thing standing between a caller and unlimited attempts.
     */
    Route::post('password/forgot', [PasswordController::class, 'forgot'])->middleware('throttle:5,1')->name('password.forgot');
    Route::post('password/reset', [PasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.reset');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('me', [AuthController::class, 'update'])->name('update');
        Route::put('password', [PasswordController::class, 'change'])->middleware('throttle:5,1')->name('password.change');
        Route::post('email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:5,1')->name('email.resend');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
    });
});
/*
 * Phase 4 — rate limits on the member's own writes, in two tiers.
 *
 * Every authenticated write here was unthrottled: one token could create returns in an unbounded
 * loop, and nothing caps how many incomes or dependents a single return may hold.
 *
 * `member-create` is the tight tier for the unbounded-growth vector; `member-write` is the
 * generous one for the chatty-but-bounded rest. Both are **named** limiters, defined in
 * `AppServiceProvider::registerMemberWriteLimits()`, which is where the numbers and the reason
 * they differ are written down — and where the reason they must not be two stacked `throttle:n,1`
 * middlewares is explained.
 */
Route::prefix('tax-returns')->middleware(['auth:sanctum', 'throttle:member-write'])->name('api.v1.tax-returns.')->group(function (): void {
    Route::get('/', [TaxReturnController::class, 'index'])->name('index');
    Route::post('/', [TaxReturnController::class, 'store'])->middleware('throttle:member-create')->name('store');
    Route::prefix('{taxReturn}')->whereNumber('taxReturn')->middleware('can:view,taxReturn')->group(function (): void {
        Route::get('/', [TaxReturnController::class, 'show'])->name('show');
        Route::patch('/', [TaxReturnController::class, 'update'])->name('update');
        Route::delete('/', [TaxReturnController::class, 'destroy'])->name('destroy');
        // Duplication creates a whole return with all its children, so it belongs on the tight
        // tier with creation rather than the chatty one.
        Route::post('duplicate', [TaxReturnController::class, 'duplicate'])->middleware('throttle:member-create')->name('duplicate');
        Route::post('calculate', [MemberTaxCalculationController::class, 'calculate'])->name('calculate');
        Route::post('complete', [MemberTaxCalculationController::class, 'complete'])->name('complete');
        Route::get('calculations', [MemberTaxCalculationController::class, 'index'])->name('calculations.index');
        Route::get('calculations/{calculationId}', [MemberTaxCalculationController::class, 'show'])->whereNumber('calculationId')->name('calculations.show');
        Route::get('recommendations', TaxReturnRecommendationController::class)->name('recommendations');
        Route::get('scenarios', [TaxScenarioController::class, 'index'])->name('scenarios.index');
        Route::post('scenarios', [TaxScenarioController::class, 'store'])->name('scenarios.store');
        Route::get('scenarios/{scenario}', [TaxScenarioController::class, 'show'])->whereNumber('scenario')->name('scenarios.show');
        Route::patch('scenarios/{scenario}', [TaxScenarioController::class, 'update'])->whereNumber('scenario')->name('scenarios.update');
        Route::delete('scenarios/{scenario}', [TaxScenarioController::class, 'destroy'])->whereNumber('scenario')->name('scenarios.destroy');
        Route::post('scenarios/{scenario}/calculate', TaxScenarioCalculationController::class)->whereNumber('scenario')->name('scenarios.calculate');
        Route::put('profile', [TaxReturnProfileController::class, 'update'])->name('profile.update');
        Route::put('spouse', [TaxReturnSpouseController::class, 'update'])->name('spouse.update');
        Route::delete('spouse', [TaxReturnSpouseController::class, 'destroy'])->name('spouse.destroy');
        Route::post('dependents', [TaxReturnDependentController::class, 'store'])->name('dependents.store');
        Route::patch('dependents/{child}', [TaxReturnDependentController::class, 'update'])->whereNumber('child')->name('dependents.update');
        Route::delete('dependents/{child}', [TaxReturnDependentController::class, 'destroy'])->whereNumber('child')->name('dependents.destroy');
        Route::post('incomes', [TaxReturnIncomeController::class, 'store'])->name('incomes.store');
        Route::patch('incomes/{child}', [TaxReturnIncomeController::class, 'update'])->whereNumber('child')->name('incomes.update');
        Route::delete('incomes/{child}', [TaxReturnIncomeController::class, 'destroy'])->whereNumber('child')->name('incomes.destroy');
        Route::post('allowances', [TaxReturnAllowanceController::class, 'store'])->name('allowances.store');
        Route::patch('allowances/{child}', [TaxReturnAllowanceController::class, 'update'])->whereNumber('child')->name('allowances.update');
        Route::delete('allowances/{child}', [TaxReturnAllowanceController::class, 'destroy'])->whereNumber('child')->name('allowances.destroy');
        // ใบแนบ ข้อ 13 และ ข้อ 20 — a separate stage from ค่าลดหย่อน, so a separate resource.
        Route::post('income-exemptions', [TaxReturnIncomeExemptionController::class, 'store'])->name('income-exemptions.store');
        Route::patch('income-exemptions/{child}', [TaxReturnIncomeExemptionController::class, 'update'])->whereNumber('child')->name('income-exemptions.update');
        Route::delete('income-exemptions/{child}', [TaxReturnIncomeExemptionController::class, 'destroy'])->whereNumber('child')->name('income-exemptions.destroy');
        Route::post('donations', [TaxReturnDonationController::class, 'store'])->name('donations.store');
        Route::patch('donations/{child}', [TaxReturnDonationController::class, 'update'])->whereNumber('child')->name('donations.update');
        Route::delete('donations/{child}', [TaxReturnDonationController::class, 'destroy'])->whereNumber('child')->name('donations.destroy');
        Route::post('withholdings', [TaxReturnWithholdingController::class, 'store'])->name('withholdings.store');
        Route::patch('withholdings/{child}', [TaxReturnWithholdingController::class, 'update'])->whereNumber('child')->name('withholdings.update');
        Route::delete('withholdings/{child}', [TaxReturnWithholdingController::class, 'destroy'])->whereNumber('child')->name('withholdings.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Milestone 08 — public content
|--------------------------------------------------------------------------
|
| No authentication. Every query runs through ContentService::publicQuery(), which is the one
| definition of public visibility, so nothing unpublished can be reached here. The same
| endpoints serve the web pages and any future mobile client.
*/
Route::prefix('content')->name('api.v1.content.')->group(function (): void {
    Route::get('articles', [ContentController::class, 'index'])->name('articles.index');
    Route::get('featured', [ContentController::class, 'featured'])->name('featured');
    Route::get('faqs', [ContentController::class, 'faqs'])->name('faqs');
    Route::get('categories', [ContentController::class, 'categories'])->name('categories.index');
    Route::get('categories/{slug}', [ContentController::class, 'category'])->name('categories.show');
    Route::get('tags', [ContentController::class, 'tags'])->name('tags.index');
    Route::get('tags/{slug}', [ContentController::class, 'tag'])->name('tags.show');
    // Last, so it cannot shadow the fixed segments above.
    Route::get('articles/{slug}', [ContentController::class, 'show'])->name('articles.show');
});

/*
|--------------------------------------------------------------------------
| Milestone 08 — administration
|--------------------------------------------------------------------------
|
| auth:sanctum answers 401 for an unauthenticated caller; the admin middleware answers 403 for a
| signed-in member. Each action additionally authorizes through a policy, so a route added later
| without this group is still not reachable by a member.
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->name('api.v1.admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('audit-logs', [AdminDashboardController::class, 'auditLogs'])->name('audit-logs');

    Route::prefix('content')->name('content.')->group(function (): void {
        // Fixed segments before the {content} binding, so "categories" is never a post id.
        Route::get('categories', [AdminContentTaxonomyController::class, 'categories'])->name('categories.index');
        Route::post('categories', [AdminContentTaxonomyController::class, 'storeCategory'])->name('categories.store');
        Route::patch('categories/{category}', [AdminContentTaxonomyController::class, 'updateCategory'])->whereNumber('category')->name('categories.update');
        Route::delete('categories/{category}', [AdminContentTaxonomyController::class, 'destroyCategory'])->whereNumber('category')->name('categories.destroy');
        Route::get('tags', [AdminContentTaxonomyController::class, 'tags'])->name('tags.index');
        Route::post('tags', [AdminContentTaxonomyController::class, 'storeTag'])->name('tags.store');
        Route::patch('tags/{tag}', [AdminContentTaxonomyController::class, 'updateTag'])->whereNumber('tag')->name('tags.update');
        Route::delete('tags/{tag}', [AdminContentTaxonomyController::class, 'destroyTag'])->whereNumber('tag')->name('tags.destroy');

        Route::get('/', [AdminContentController::class, 'index'])->name('index');
        Route::post('/', [AdminContentController::class, 'store'])->name('store');
        Route::get('{content}', [AdminContentController::class, 'show'])->whereNumber('content')->name('show');
        // Mints the short-lived signed link that lets an author see a draft before publishing it.
        Route::get('{content}/preview-url', [AdminContentController::class, 'previewUrl'])->whereNumber('content')->name('preview-url');
        Route::patch('{content}', [AdminContentController::class, 'update'])->whereNumber('content')->name('update');
        Route::delete('{content}', [AdminContentController::class, 'destroy'])->whereNumber('content')->name('destroy');
        Route::post('{content}/publish', [AdminContentController::class, 'publish'])->whereNumber('content')->name('publish');
        Route::post('{content}/unpublish', [AdminContentController::class, 'unpublish'])->whereNumber('content')->name('unpublish');
        Route::post('{content}/archive', [AdminContentController::class, 'archive'])->whereNumber('content')->name('archive');
    });

    // Written out rather than apiResource(), so the route parameter matches the controller's
    // typed argument and implicit binding resolves.
    /*
     * Phase 2 — account administration.
     *
     * Read, move between the two roles, and end sessions. No create, no delete, no password
     * setting: registration is public, erasure is an open question, and an administrator who
     * could set a password could sign in as that member.
     *
     * Each action authorises through `UserPolicy` as well as this group, so a route added later
     * without the group is still not reachable by a member.
     */
    Route::prefix('users')->name('users.')->group(function (): void {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::patch('{user}/role', [AdminUserController::class, 'updateRole'])->whereNumber('user')->name('role');
        Route::post('{user}/revoke-sessions', [AdminUserController::class, 'revokeSessions'])->whereNumber('user')->name('revoke-sessions');
        /*
         * Suspension — the lever revocation is not. Revoking sessions ends them and the member
         * signs straight back in; this refuses sign-in and password reset until an administrator
         * reopens the account, and deletes nothing.
         *
         * Two endpoints rather than one flag, so a generic update can never lock somebody out.
         */
        Route::post('{user}/suspend', [AdminUserController::class, 'suspend'])->whereNumber('user')->name('suspend');
        Route::post('{user}/unsuspend', [AdminUserController::class, 'unsuspend'])->whereNumber('user')->name('unsuspend');
    });

    Route::prefix('tax-sources')->name('tax-sources.')->group(function (): void {
        Route::get('/', [AdminTaxSourceController::class, 'index'])->name('index');
        Route::post('/', [AdminTaxSourceController::class, 'store'])->name('store');
        Route::get('{taxSource}', [AdminTaxSourceController::class, 'show'])->whereNumber('taxSource')->name('show');
        Route::patch('{taxSource}', [AdminTaxSourceController::class, 'update'])->whereNumber('taxSource')->name('update');
        Route::delete('{taxSource}', [AdminTaxSourceController::class, 'destroy'])->whereNumber('taxSource')->name('destroy');
    });

    // M9.1 — the id/code pairs a rule may point at, so the console can offer a picker instead of
    // asking an administrator to type a primary key. Read-only; a fixed segment, so it can never
    // be mistaken for a {taxRuleVersion} id.
    Route::get('rule-references', [AdminDraftRuleController::class, 'references'])->name('rule-references');

    /*
     * M9.1 — tax years. Opening a year is what makes "next year's rates changed" an
     * administrative task: the year is created here with its form structure copied from an
     * existing year, and the rules are then carried over by cloning a rule version into it.
     * A year is never deleted; DELETE retires it and leaves everything that uses it untouched.
     */
    Route::prefix('tax-years')->name('tax-years.')->group(function (): void {
        Route::get('/', [AdminTaxYearController::class, 'index'])->name('index');
        Route::post('/', [AdminTaxYearController::class, 'store'])->name('store');
        Route::patch('{taxYear}', [AdminTaxYearController::class, 'update'])->whereNumber('taxYear')->name('update');
        Route::delete('{taxYear}', [AdminTaxYearController::class, 'destroy'])->whereNumber('taxYear')->name('destroy');
    });

    Route::prefix('tax-rule-versions')->name('tax-rule-versions.')->group(function (): void {
        Route::get('/', [AdminTaxRuleVersionController::class, 'index'])->name('index');
        Route::post('/', [AdminTaxRuleVersionController::class, 'store'])->name('store');
        Route::prefix('{taxRuleVersion}')->whereNumber('taxRuleVersion')->group(function (): void {
            Route::get('/', [AdminTaxRuleVersionController::class, 'show'])->name('show');
            Route::patch('/', [AdminTaxRuleVersionController::class, 'update'])->name('update');
            Route::post('clone', [AdminTaxRuleVersionController::class, 'clone'])->name('clone');
            Route::post('validate', [AdminTaxRuleVersionController::class, 'validateVersion'])->name('validate');
            Route::post('publish', [AdminTaxRuleVersionController::class, 'publish'])->name('publish');
            Route::post('archive', [AdminTaxRuleVersionController::class, 'archive'])->name('archive');
            // Draft rule entities. {resource} is matched against DraftRuleRegistry, never free text.
            Route::get('{resource}', [AdminDraftRuleController::class, 'index'])->name('rules.index');
            Route::post('{resource}', [AdminDraftRuleController::class, 'store'])->name('rules.store');
            Route::patch('{resource}/{ruleId}', [AdminDraftRuleController::class, 'update'])->whereNumber('ruleId')->name('rules.update');
            Route::delete('{resource}/{ruleId}', [AdminDraftRuleController::class, 'destroy'])->whereNumber('ruleId')->name('rules.destroy');
        });
    });
});
