<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Data-free shells; protected admin data is loaded only from the authorized API. */
class AdminPageController extends Controller
{
    public function login(): View
    {
        return view('admin.login');
    }

    public function dashboard(): View
    {
        return view('admin.dashboard');
    }

    public function content(): View
    {
        return view('admin.content-index');
    }

    public function contentForm(?int $content = null): View
    {
        return view('admin.content-form', ['contentId' => $content]);
    }

    public function ruleVersions(): View
    {
        return view('admin.rule-versions');
    }

    public function ruleVersion(int $taxRuleVersion): View
    {
        return view('admin.rule-version', ['versionId' => $taxRuleVersion]);
    }

    public function taxonomy(): View
    {
        return view('admin.taxonomy');
    }

    public function auditLogs(): View
    {
        return view('admin.audit-logs');
    }

    /** Phase 2 — the accounts page. Renders empty; every row comes from the authorised API. */
    public function users(): View
    {
        return view('admin.users');
    }

    public function taxYears(): View
    {
        return view('admin.tax-years');
    }

    public function taxSources(): View
    {
        return view('admin.tax-sources');
    }
}
