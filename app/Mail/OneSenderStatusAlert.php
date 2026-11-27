<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class OneSenderStatusAlert extends Mailable
{
    public function __construct(
        public array $status,
        public bool $isTest = false,
    ) {}

    public function build(): self
    {
        $title = $this->isTest
            ? 'Ujian amaran OneSender - myWAP'
            : ($this->status['state'] === 'offline'
                ? 'Amaran: WhatsApp myWAP offline'
                : 'Amaran: Status WhatsApp myWAP tidak dapat disahkan');

        return $this->subject($title)->text('emails.onesender-status', [
            'checkedAt' => now()->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s').' (Malaysia)',
            'settingsUrl' => url('/superadmin/settings'),
        ]);
    }
}
