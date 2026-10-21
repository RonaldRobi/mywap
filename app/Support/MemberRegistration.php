<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Peraturan & mesej pendaftaran ahli baharu yang dikongsi oleh aliran web
 * (RegisteredUserController) dan API mobile (AuthController).
 *
 * Mesej sengaja ditulis dalam Bahasa Melayu yang mudah difahami supaya ahli
 * yang kurang celik IT tahu apa yang perlu dibetulkan.
 */
class MemberRegistration
{
    /**
     * Buang ruang kosong dan jadikan huruf besar (kekalkan sengkang/titik
     * supaya format yang ditaip semasa daftar sama dengan semasa log masuk).
     * Contoh: " a1234567 " -> "A1234567".
     */
    public static function normalizeIc(?string $ic): string
    {
        return Str::upper(preg_replace('/\s+/', '', trim((string) $ic)) ?? '');
    }

    /**
     * Emel sentiasa disimpan dalam huruf kecil supaya "Ahmad@Emel.com" dan
     * "ahmad@emel.com" dianggap sama dan tidak gagal dengan ralat mengelirukan.
     */
    public static function normalizeEmail(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'ic_number' => ['required', 'string', 'min:6', 'max:32', 'unique:users,ic_number'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'dob' => ['required', 'date', 'before_or_equal:today'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Sila masukkan nama penuh anda.',
            'name.max' => 'Nama terlalu panjang. Sila gunakan maksimum 255 aksara.',

            'email.required' => 'Sila masukkan alamat emel anda.',
            'email.email' => 'Alamat emel tidak sah. Contoh yang betul: nama@emel.com',
            'email.max' => 'Alamat emel terlalu panjang.',
            'email.unique' => 'Emel ini sudah didaftarkan. Sila log masuk, atau gunakan emel lain.',

            'ic_number.required' => 'Sila masukkan No. Kad Pengenalan atau No. Passport.',
            'ic_number.min' => 'No. Kad Pengenalan tidak sah. Masukkan 12 digit No. Kad Pengenalan (cth: 900101011234) atau No. Passport.',
            'ic_number.max' => 'No. Kad Pengenalan terlalu panjang.',
            'ic_number.unique' => 'No. Kad Pengenalan ini sudah didaftarkan. Sila log masuk, atau hubungi admin jika anda terlupa No. Ahli.',

            'phone.regex' => 'No. telefon tidak sah. Gunakan nombor sahaja, contoh: 0123456789.',
            'phone.max' => 'No. telefon terlalu panjang.',

            'dob.required' => 'Tarikh lahir wajib diisi kerana No. IC tidak dapat dibaca. Sila pilih tarikh lahir.',
            'dob.date' => 'Tarikh lahir tidak sah. Sila pilih tarikh yang betul.',
            'dob.before_or_equal' => 'Tarikh lahir tidak boleh pada masa hadapan.',

            'branch_id.exists' => 'Cawangan yang dipilih tidak sah. Sila pilih semula.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => 'nama penuh',
            'email' => 'emel',
            'ic_number' => 'No. Kad Pengenalan',
            'phone' => 'No. telefon',
            'dob' => 'tarikh lahir',
            'branch_id' => 'cawangan',
        ];
    }
}
