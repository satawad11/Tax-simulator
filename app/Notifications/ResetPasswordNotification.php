<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The password-reset email, in the product's own language.
 *
 * Laravel's built-in notification is in English and names the framework's own route. This one is
 * Thai and points at the reset page this project serves, carrying the address alongside the token
 * because the broker verifies both.
 *
 * **It is deliberately not queued.** This deployment runs no queue worker — there is no
 * `queue:work` process in the compose stack or the image — so a queued notification would be
 * written to the jobs table and never sent, and the member would wait for an email that does not
 * exist. Sending inline costs the request a round trip to the mail transport and is the only
 * option that actually delivers. If a worker is added later, queueing this is a safe improvement;
 * until then it must not be.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);
        $url = url("/password/reset/{$this->token}?email=".urlencode($notifiable->getEmailForPasswordReset()));

        return (new MailMessage)
            ->subject('ตั้งรหัสผ่านใหม่ — '.config('app.name'))
            ->greeting('สวัสดีครับ/ค่ะ')
            ->line('เราได้รับคำขอตั้งรหัสผ่านใหม่สำหรับบัญชีที่ใช้อีเมลนี้')
            ->action('ตั้งรหัสผ่านใหม่', $url)
            ->line("ลิงก์นี้จะหมดอายุใน {$minutes} นาที")
            ->line('หากคุณไม่ได้เป็นผู้ขอ ไม่ต้องดำเนินการใด ๆ รหัสผ่านเดิมของคุณยังใช้ได้ตามปกติ')
            ->salutation('ขอบคุณครับ/ค่ะ');
    }
}
