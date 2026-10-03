<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class MemberPageController extends Controller
{
    public function simulator(?string $form = null): View
    {
        abort_unless($form === null || in_array(strtoupper($form), ['PND90', 'PND91'], true), 404);

        return view('simulator.index', ['formCode' => $form ? strtoupper($form) : null]);
    }

    public function login(): View
    {
        return view('auth.login');
    }

    public function register(): View
    {
        return view('auth.register');
    }

    public function passwordForgot(): View
    {
        return view('auth.password-forgot');
    }

    /** The token travels in the path, exactly as the emailed link carries it. */
    public function passwordReset(string $token): View
    {
        return view('auth.password-reset', ['token' => $token, 'email' => request()->query('email', '')]);
    }

    public function passwordChange(): View
    {
        return view('auth.password-change');
    }

    public function dashboard(): View
    {
        return $this->dashboardPage('dashboard');
    }

    public function returns(): View
    {
        return $this->dashboardPage('returns');
    }

    /** Phase 4 — `PUT /auth/me` has existed since M5 with no page calling it. */
    public function account(): View
    {
        return $this->dashboardPage('account');
    }

    public function returnDetail(int $taxReturn): View
    {
        return $this->dashboardPage('return', $taxReturn);
    }

    public function history(int $taxReturn): View
    {
        return $this->dashboardPage('history', $taxReturn);
    }

    public function planning(int $taxReturn): View
    {
        return $this->dashboardPage('planning', $taxReturn);
    }

    private function dashboardPage(string $page, ?int $taxReturn = null): View
    {
        return view('dashboard.shell', compact('page', 'taxReturn'));
    }
}
