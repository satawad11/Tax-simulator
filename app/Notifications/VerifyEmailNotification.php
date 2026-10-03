<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The address-confirmation email.
 *
 * The link is a signed URL with an expiry, so it cannot be forged or replayed indefinitely, and it
 * carries a hash of the address it was issued for — changing the address again invalidates every
 * link already sent for the old one.
 *
 * Not queued, for the reason given on {@see ResetPasswordNotification}.
 */
class VerifyEmailNotification extends Notification
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('ยืนยันอีเมลของคุณ — '.config('app.name'))
            ->greeting('สวัสดีครับ/ค่ะ')
            ->line('กรุณายืนยันว่าอีเมลนี้เป็นของคุณ เพื่อให้เราติดต่อกลับได้เมื่อคุณขอตั้งรหัสผ่านใหม่')
            ->action('ยืนยันอีเมล', $this->url($notifiable))
            ->line('หากคุณไม่ได้สมัครสมาชิกหรือไม่ได้เปลี่ยนอีเมล ไม่ต้องดำเนินการใด ๆ')
            ->salutation('ขอบคุณครับ/ค่ะ');
    }

    private function url(object $notifiable): string
    {
        return URL::temporarySignedRoute('verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]);
    }
}
