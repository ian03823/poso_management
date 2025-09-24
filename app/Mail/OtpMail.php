<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $subjectLine, public string $otp) {}

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('emails.otp')
            ->with(['otp' => $this->otp]);
    }
}
