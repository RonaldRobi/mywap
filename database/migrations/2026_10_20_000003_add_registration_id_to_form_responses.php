<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_responses', function (Blueprint $table) {
            $table->foreignId('registration_id')
                ->nullable()
                ->after('form_id')
                ->constrained('registrations')
                ->nullOnDelete();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('form_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registration_id');
        });
    }

    /**
     * Link responses created before this column existed to their registration.
     * Matching is limited to the same form plus user/email/phone/name, and the
     * newest registration wins when several candidates exist.
     */
    private function backfill(): void
    {
        DB::table('form_responses')
            ->whereNull('registration_id')
            ->orderBy('id')
            ->chunkById(500, function ($responses) {
                foreach ($responses as $response) {
                    if ($response->form_id === null) {
                        continue;
                    }

                    $phone = preg_replace('/\D+/', '', (string) $response->respondent_phone);

                    if ($phone !== '' && str_starts_with($phone, '60') && strlen($phone) > 10) {
                        $phone = '0'.substr($phone, 2);
                    }

                    $email = $response->respondent_email
                        ? mb_strtolower((string) $response->respondent_email)
                        : null;

                    if ($response->user_id === null && $email === null && $phone === '' && ! $response->respondent_name) {
                        continue;
                    }

                    $registrationId = DB::table('registrations')
                        ->where('form_id', $response->form_id)
                        ->where(function ($query) use ($response, $email, $phone) {
                            if ($response->user_id !== null) {
                                $query->orWhere('user_id', $response->user_id);
                            }
                            if ($email !== null) {
                                $query->orWhereRaw('LOWER(email) = ?', [$email]);
                            }
                            if ($phone !== '') {
                                $query->orWhere('phone_normalized', $phone);
                            }
                            if ($response->respondent_name) {
                                $query->orWhere('name', $response->respondent_name);
                            }
                        })
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->value('id');

                    if ($registrationId !== null) {
                        DB::table('form_responses')
                            ->where('id', $response->id)
                            ->update(['registration_id' => $registrationId]);
                    }
                }
            });
    }
};
