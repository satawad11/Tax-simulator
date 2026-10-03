<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use Illuminate\Http\JsonResponse;

class EmailVerificationController extends Controller
{
    /**
     * Sends the confirmation link again to the address the account currently holds.
     *
     * Authenticated, so it can only ever mail the caller's own address and reveals nothing about
     * anyone else's. An already-verified account answers the same 202 rather than an error: there
     * is nothing wrong with asking, and the state is reported in `data`.
     */
    public function resend(EmptyMemberActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $verified = $user->hasVerifiedEmail();

        if (! $verified) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['success' => true,
            'message' => $verified ? 'อีเมลนี้ยืนยันแล้ว' : 'ส่งลิงก์ยืนยันอีเมลให้แล้ว กรุณาตรวจกล่องจดหมายของคุณ',
            'data' => ['verified' => $verified]], 202);
    }
}
