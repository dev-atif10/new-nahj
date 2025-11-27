<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject(trans('auth.otp_verification'))
                    ->view('admin.auth.otp_verify')
                    ->with(['otp' => $this->data['otp'], 'email' => $this->data['email']]);
    }
}