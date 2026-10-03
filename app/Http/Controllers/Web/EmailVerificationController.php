<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Phase 1 — the far end of the verification link.
 *
 * This is a web route, not an API one: the link is clicked in a mail client, which sends a plain
 * browser GET carrying no bearer token. Authorisation therefore comes from the signature on the
 * URL itself — `signed` proves this application issued it and that it has not expired, and the
 * hash proves it was issued for the address the account currently holds. A member does not need
 * to be signed in to complete it, and being signed in as someone else changes nothing.
 */
class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash): View
    {
        $user = User::find($id);

        // A link whose hash no longer matches was issued for an address the account has since
        // changed. It is stale rather than hostile, and it says so.
        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->notice('ลิงก์ยืนยันอีเมลไม่ถูกต้อง',
                'ลิงก์นี้อาจถูกใช้ไปแล้ว หมดอายุ หรือออกให้กับอีเมลเดิมก่อนที่คุณจะเปลี่ยน กรุณาขอลิงก์ยืนยันใหม่จากหน้าบัญชีของคุณ', false);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->notice('อีเมลนี้ยืนยันแล้ว', 'คุณยืนยันอีเมลนี้ไว้ก่อนหน้านี้แล้ว ไม่ต้องทำอะไรเพิ่ม', true);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return $this->notice('ยืนยันอีเมลเรียบร้อยแล้ว',
            'ขอบคุณครับ/ค่ะ ตอนนี้เราติดต่อกลับหาคุณได้เมื่อคุณขอตั้งรหัสผ่านใหม่', true);
    }

    private function notice(string $title, string $message, bool $success): View
    {
        return view('auth.notice', compact('title', 'message', 'success'));
    }
}
