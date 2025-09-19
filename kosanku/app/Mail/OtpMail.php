<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public int $ttlMinutes;

    public function __construct(string $code, int $ttlMinutes = 15)
    {
        $this->code = $code;
        $this->ttlMinutes = $ttlMinutes;
    }

    public function build()
    {
        return $this->subject('Kode OTP Anda')
            ->html("<p>Kode OTP: <b>{$this->code}</b> ({$this->ttlMinutes} menit)</p>");
        // kalau ini berhasil, berarti problem ada di view 'emails.otp'
    }
}
