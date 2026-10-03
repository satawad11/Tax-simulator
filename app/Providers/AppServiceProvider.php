<?php

namespace App\Providers;

use App\Models\ContentPost;
use App\Models\TaxRuleVersion;
use App\Models\TaxSource;
use App\Policies\ContentPolicy;
use App\Policies\TaxRuleVersionPolicy;
use App\Policies\TaxSourcePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Milestone 08 — registered by name rather than left to discovery. ContentPost's policy
        // is ContentPolicy, which convention would not find, and an authorization rule that
        // silently fails to load is the kind of bug that only shows up in production.
        Gate::policy(ContentPost::class, ContentPolicy::class);
        Gate::policy(TaxRuleVersion::class, TaxRuleVersionPolicy::class);
        Gate::policy(TaxSource::class, TaxSourcePolicy::class);

        $this->registerMemberWriteLimits();
    }

    /**
     * Phase 4 — two tiers of rate limit on a member's own writes.
     *
     * Every authenticated write was unthrottled: one token could create returns in an unbounded
     * loop, and nothing caps how many incomes or dependents a single return may hold.
     *
     * **Named limiters, not two stacked `throttle:n,1` middlewares.** Stacking looked simpler and
     * is wrong: `ThrottleRequests` derives its key from the route and the user, not from the
     * limit, so two inline throttles on one request share a counter and each request is counted
     * twice — a "20 per minute" tier that actually refuses the eleventh. A named limiter's key is
     * namespaced by its name, so these two count independently.
     *
     * The tiers differ because the cost of abuse differs:
     *
     *   `member-create` — creating or duplicating a return is the unbounded-growth vector, and is
     *     rare in normal use. A member makes a handful a year; 20 a minute is far beyond any real
     *     session.
     *   `member-write` — everything else is chatty but bounded by a return that already exists.
     *     The wizard writes one request per item across five collections, so this has to clear a
     *     realistically heavy return by a wide margin.
     *
     * The theoretical maximum the guest calculator accepts — 100 items in each of five
     * collections — exceeds 300 in one minute and would finish in the next. Accepted: a 500-line
     * return is not a filing anyone makes, and the alternative was no bound at all.
     */
    private function registerMemberWriteLimits(): void
    {
        RateLimiter::for('member-create', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('member-write', fn (Request $request) => Limit::perMinute(300)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
