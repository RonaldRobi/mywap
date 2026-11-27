<?php

namespace App\Console\Commands;

use App\Mail\OneSenderStatusAlert;
use App\Services\OneSenderWhatsappService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorOneSender extends Command
{
    protected $signature = 'onesender:monitor {--test-email : Hantar emel ujian tanpa mengubah keadaan amaran}';

    protected $description = 'Semak sambungan WhatsApp dan emel pentadbir apabila OneSender offline';

    public const STATE_KEY = 'onesender:monitor:state';

    public function handle(OneSenderWhatsappService $whatsapp): int
    {
        $recipient = config('services.onesender.alert_email');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat emel amaran OneSender tidak sah.');

            return self::FAILURE;
        }

        return Cache::lock('onesender:monitor:lock', 120)->get(function () use ($whatsapp, $recipient) {
            $status = $whatsapp->connectionStatus();
            $previous = Cache::get(self::STATE_KEY, []);

            try {
                if ($this->option('test-email')) {
                    Mail::to($recipient)->send(new OneSenderStatusAlert($status, true));
                    $this->info('Emel ujian OneSender dihantar.');

                    return self::SUCCESS;
                }

                $alertedAt = $previous['alerted_at'] ?? null;
                if ($status['state'] === 'online') {
                    $alertedAt = null;
                } elseif ($alertedAt === null) {
                    Mail::to($recipient)->send(new OneSenderStatusAlert($status));
                    $alertedAt = now()->toIso8601String();
                    Log::warning('OneSender connection alert sent.', ['state' => $status['state']]);
                }

                Cache::forever(self::STATE_KEY, [
                    ...$status,
                    'checked_at' => now()->toIso8601String(),
                    'alerted_at' => $alertedAt,
                ]);
                $this->info('OneSender: '.$status['state'].($alertedAt ? ' (amaran telah dihantar)' : ''));

                return self::SUCCESS;
            } catch (\Throwable $exception) {
                Log::error('OneSender monitor email failed.', ['exception' => $exception::class]);
                $this->error('Emel amaran gagal dihantar; sistem akan cuba semula pada semakan seterusnya.');

                return self::FAILURE;
            }
        }) ?? self::SUCCESS;
    }
}
