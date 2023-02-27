<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class MailHandlerService
{

    public static function sendMail($mailable, $email, $ccs = [])
    {
        $mail = Mail::to($email);
        if (!empty($ccs)) {
            $mail = $mail->cc($ccs);
        }

        $adminEmails = explode(",", env('SYSTEM_EMAIL', ''));
        if (!empty($adminEmails)) {
            $mail = $mail->bcc($adminEmails);
        }

        //
        $mail->send($mailable);
    }
}
