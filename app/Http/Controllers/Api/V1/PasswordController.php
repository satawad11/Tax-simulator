<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Services\PasswordService;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    /**
     * Always 202, always the same body.
     *
     * Accepted, not OK: what the caller asked for is under way, and whether an email went out is
     * deliberately not stated. A 404 or a 422 here would answer "is this address registered?" to
     * anyone who cared to ask, which is the whole reason this endpoint is shaped this way.
     */
    public function forgot(ForgotPasswordRequest $request, PasswordService $service): JsonResponse
    {
        $service->forgot($request->validated('email'));

        return response()->json(['success' => true,
            'message' => 'หากอีเมลนี้มีบัญชีอยู่ในระบบ เราได้ส่งลิงก์ตั้งรหัสผ่านใหม่ไปให้แล้ว',
            'data' => null], 202);
    }

    public function reset(ResetPasswordRequest $request, PasswordService $service): JsonResponse
    {
        $service->reset($request->validated());

        return response()->json(['success' => true,
            'message' => 'ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว กรุณาเข้าสู่ระบบอีกครั้ง', 'data' => null]);
    }

    public function change(ChangePasswordRequest $request, PasswordService $service): JsonResponse
    {
        $service->change($request->user(), $request->validated('current_password'), $request->validated('password'));

        return response()->json(['success' => true,
            'message' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว อุปกรณ์อื่นที่เข้าสู่ระบบไว้จะต้องเข้าสู่ระบบใหม่',
            'data' => null]);
    }
}
