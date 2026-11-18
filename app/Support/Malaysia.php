<?php

namespace App\Support;

use Illuminate\Support\Str;

class Malaysia
{
    /**
     * @return array<int, string>
     */
    public static function states(): array
    {
        return [
            'Johor',
            'Kedah',
            'Kelantan',
            'Melaka',
            'Negeri Sembilan',
            'Pahang',
            'Perak',
            'Perlis',
            'Pulau Pinang',
            'Sabah',
            'Sarawak',
            'Selangor',
            'Terengganu',
            'Wilayah Persekutuan',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function districts(): array
    {
        return [
            'Johor' => [
                'Batu Pahat', 'Johor Bahru', 'Kluang', 'Kota Tinggi', 'Kulai',
                'Mersing', 'Muar', 'Pontian', 'Segamat', 'Tangkak',
            ],
            'Kedah' => [
                'Baling', 'Bandar Baharu', 'Kota Setar', 'Kubang Pasu', 'Kulim',
                'Kuala Muda', 'Langkawi', 'Padang Terap', 'Pendang', 'Pokok Sena',
                'Sik', 'Yan',
            ],
            'Kelantan' => [
                'Bachok', 'Gua Musang', 'Jeli', 'Kota Bharu', 'Kuala Krai',
                'Lojing', 'Machang', 'Pasir Mas', 'Pasir Puteh', 'Tanah Merah',
                'Tumpat',
            ],
            'Melaka' => ['Alor Gajah', 'Jasin', 'Melaka Tengah'],
            'Negeri Sembilan' => [
                'Jelebu', 'Jempol', 'Kuala Pilah', 'Port Dickson', 'Rembau',
                'Seremban', 'Tampin',
            ],
            'Pahang' => [
                'Bentong', 'Bera', 'Cameron Highlands', 'Jerantut', 'Kuantan',
                'Lipis', 'Maran', 'Pekan', 'Raub', 'Rompin', 'Temerloh',
            ],
            'Perak' => [
                'Bagan Datuk', 'Batang Padang', 'Hilir Perak', 'Hulu Perak',
                'Kampar', 'Kerian', 'Kinta', 'Kuala Kangsar',
                'Larut, Matang dan Selama', 'Manjung', 'Muallim', 'Perak Tengah',
            ],
            'Perlis' => ['Perlis'],
            'Pulau Pinang' => [
                'Barat Daya', 'Seberang Perai Selatan', 'Seberang Perai Tengah',
                'Seberang Perai Utara', 'Timur Laut',
            ],
            'Sabah' => [
                'Beaufort', 'Beluran', 'Kalabakan', 'Keningau', 'Kinabatangan',
                'Kota Belud', 'Kota Kinabalu', 'Kota Marudu', 'Kuala Penyu',
                'Kudat', 'Kunak', 'Lahad Datu', 'Nabawan', 'Papar', 'Penampang',
                'Pitas', 'Putatan', 'Ranau', 'Sandakan', 'Semporna', 'Sipitang',
                'Tambunan', 'Tawau', 'Telupid', 'Tenom', 'Tongod', 'Tuaran',
            ],
            'Sarawak' => [
                'Betong', 'Bintulu', 'Kapit', 'Kota Samarahan', 'Kuching',
                'Limbang', 'Miri', 'Mukah', 'Serian', 'Sibu', 'Sri Aman',
                'Sarikei',
            ],
            'Selangor' => [
                'Gombak', 'Hulu Langat', 'Hulu Selangor', 'Klang',
                'Kuala Langat', 'Kuala Selangor', 'Petaling', 'Sabak Bernam',
                'Sepang',
            ],
            'Terengganu' => [
                'Besut', 'Dungun', 'Hulu Terengganu', 'Kemaman',
                'Kuala Nerus', 'Kuala Terengganu', 'Marang', 'Setiu',
            ],
            'Wilayah Persekutuan' => ['Kuala Lumpur', 'Labuan', 'Putrajaya'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function educationLevels(): array
    {
        return [
            'UPSR',
            'PT3',
            'SPM',
            'STAM',
            'STPM',
            'Sijil Kemahiran Malaysia (SKM)',
            'Sijil',
            'Diploma',
            'Diploma Lanjutan',
            'Ijazah Sarjana Muda',
            'Ijazah Sarjana',
            'Doktor Falsafah (PhD)',
            'Lain-lain',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function salutations(): array
    {
        return [
            'Encik',
            'Cik',
            'Puan',
            'Tuan',
            'Dato\'',
            'Datin',
            'Datuk',
            'Tan Sri',
            'Puan Sri',
            'Tun',
            'Toh Puan',
            'Dr.',
            'Prof.',
            'Prof. Madya',
            'Haji',
            'Hajah',
            'Ustaz',
            'Ustazah',
        ];
    }

    public static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value));

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '60')) {
            $digits = substr($digits, 2);

            if ($digits === '') {
                return null;
            }

            if (! str_starts_with($digits, '0')) {
                $digits = '0'.$digits;
            }
        } elseif (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /**
     * Title-case names that are stored in full uppercase (legacy branch and
     * wadah data) while leaving mixed-case names untouched. ABIM/PKPIM are
     * acronyms and stay uppercase.
     */
    public static function titleCase(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($value === '' || $value !== mb_strtoupper($value)) {
            return $value;
        }

        $acronyms = ['ABIM', 'PKPIM'];

        return preg_replace_callback(
            '/\b[\p{L}\p{M}\']+\b/u',
            function (array $matches) use ($acronyms): string {
                $word = $matches[0];

                return in_array(mb_strtoupper($word), $acronyms, true)
                    ? mb_strtoupper($word)
                    : Str::title(mb_strtolower($word));
            },
            $value,
        );
    }
}
