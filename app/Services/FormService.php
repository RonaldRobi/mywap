<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormResponse;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * FormService
 *
 * Logik tunggal untuk domain Form — dikongsi oleh WebController (Inertia)
 * dan ApiController (JSON) supaya web & Flutter tidak drift.
 */
class FormService
{
    /**
     * Cari borang aktif melalui share_token. 404 jika tiada/tidak aktif.
     */
    public function findPublic(string $token): Form
    {
        return Form::where('share_token', $token)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * Serialize borang kepada bentuk konsisten untuk halaman public
     * (web `Forms/Public` dan API `GET /forms/{token}`).
     */
    public function publicFormPayload(Form $form): array
    {
        $form->loadMissing([
            'questions' => fn ($q) => $q->orderBy('sort_order'),
            'organization:id,name',
        ]);

        return [
            'id' => $form->id,
            'title' => $form->title,
            'description' => $form->description,
            'price' => $form->price,
            'price_tiers' => $form->tiers(),
            'payment_required' => $form->payment_required,
            'terms' => $form->terms,
            'event_id' => $form->event_id,
            'share_token' => $form->share_token,
            'organization_name' => $form->organization?->name,
            'branch_options' => $form->branchOptions(),
            'header_image_url' => $form->header_image_path
                ? asset('storage/'.$form->header_image_path)
                : null,
            'questions' => $form->questions
                ->sortBy('sort_order')
                ->map(fn ($q) => [
                    'id' => $q->id,
                    'label' => $q->label,
                    'type' => $q->type,
                    'options' => $q->options,
                    'required' => $q->required,
                    'placeholder' => $q->placeholder,
                    'help_text' => $q->help_text,
                ])->values(),
        ];
    }

    /**
     * Peraturan validasi dinamik mengikut jenis soalan — dikongsi web & API.
     */
    public function validationRules(Form $form): array
    {
        $rules = [
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'respondent_email' => ['nullable', 'email', 'max:255'],
            'respondent_phone' => ['nullable', 'string', 'max:50'],
            'answers' => ['required', 'array'],
        ];

        foreach ($form->questions as $q) {
            $key = "answers.{$q->id}";
            $rule = $q->required ? ['required'] : ['nullable'];

            if ($q->type === 'file') {
                $rule[] = 'file';
                $rule[] = 'mimes:pdf,png,jpg,jpeg,doc,docx,xls,xlsx,zip';
                $rule[] = 'max:10240';
            } elseif (in_array($q->type, ['email'])) {
                $rule[] = 'email';
            } elseif (in_array($q->type, ['number'])) {
                $rule[] = 'numeric';
            } elseif (in_array($q->type, ['date'])) {
                $rule[] = 'date';
            }

            $rules[$key] = $rule;
        }

        return $rules;
    }

    /**
     * Simpan satu respons + jawapan borang dalam transaksi.
     */
    public function storeResponse(Form $form, array $data, ?User $user): FormResponse
    {
        return DB::transaction(function () use ($form, $data, $user) {
            $response = FormResponse::create([
                'form_id' => $form->id,
                'user_id' => $user?->id,
                'respondent_name' => $data['respondent_name'] ?? null,
                'respondent_email' => $data['respondent_email'] ?? null,
                'respondent_phone' => $data['respondent_phone'] ?? null,
                'submitted_at' => now(),
            ]);

            foreach ($data['answers'] as $questionId => $value) {
                $stored = $value instanceof UploadedFile
                    ? $value->store('form-uploads', 'public')
                    : (is_array($value) ? implode(', ', $value) : (string) $value);

                FormAnswer::create([
                    'form_response_id' => $response->id,
                    'form_question_id' => $questionId,
                    'value' => $stored,
                ]);
            }

            return $response;
        });
    }
}
