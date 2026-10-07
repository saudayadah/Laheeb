<?php

namespace App\Notifications;

use App\Support\AppSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordArabic extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $name = (string) AppSettings::get('bakery_name_ar');
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject("استعادة كلمة المرور — {$name}")
            ->greeting('السلام عليكم،')
            ->line('وصلنا طلب لإعادة تعيين كلمة المرور لحسابك.')
            ->action('إعادة تعيين كلمة المرور', $url)
            ->line("هذا الرابط صالح لمدة {$minutes} دقيقة.")
            ->line('إذا ما طلبت تغيير كلمة المرور، تجاهل هذه الرسالة وكلمتك الحالية تبقى كما هي.')
            ->salutation("مع التحية،\n{$name}");
    }
}
