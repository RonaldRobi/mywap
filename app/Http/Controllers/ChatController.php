<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\KnowledgeArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $message = $data['message'];
        $setting = AppSetting::singleton();
        $apiKey = $setting?->gemini_api_key;

        $articles = KnowledgeArticle::where('is_active', true)
            ->where(function ($q) use ($message) {
                $q->where('question', 'like', "%{$message}%")
                  ->orWhere('answer', 'like', "%{$message}%")
                  ->orWhere('keywords', 'like', "%{$message}%")
                  ->orWhere('document_content', 'like', "%{$message}%");
            })
            ->take(3)
            ->get();

        if ($articles->isEmpty()) {
            $email = $setting?->admin_contact_email ?? 'admin@mywap.my';
            $phone = $setting?->admin_contact_phone ?? 'pihak pentadbir';

            return response()->json([
                'reply' => "Maaf, saya tak dapat jawab soalan tu. Sila emel {$email} atau hubungi {$phone} untuk bantuan lanjut.",
            ]);
        }

        if (! $apiKey) {
            return response()->json([
                'reply' => 'AI chatbot belum dikonfigurasi. Sila hubungi admin.',
            ], 503);
        }

        $context = $articles->map(function ($a) {
            $parts = [];
            if ($a->question) {
                $parts[] = "Soalan: {$a->question}";
            }
            $parts[] = "Jawapan: {$a->answer}";
            if ($a->document_content) {
                $parts[] = "Kandungan dokumen: {$a->document_content}";
            }
            return implode("\n", $parts);
        })->implode("\n\n---\n\n");

        $response = Http::timeout(30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}",
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => "Gunakan maklumat berikut untuk menjawab soalan pengguna. Jika maklumat yang diberikan tidak mencukupi untuk menjawab, jawab secara umum dan nasihatkan pengguna untuk menghubungi pentadbir.\n\nMaklumat rujukan:\n{$context}\n\nSoalan pengguna: {$message}"],
                        ],
                    ],
                ],
                'systemInstruction' => [
                    'parts' => [
                        ['text' => 'Anda adalah pembantu AI mesra yang membantu ahli myWAP. Jawab dalam Bahasa Melayu. Berikan jawapan yang ringkas, tepat dan membantu. myWAP adalah platform pengurusan organisasi untuk pertubuhan Islam di Malaysia.'],
                    ],
                ],
            ]
        );

        if ($response->failed()) {
            $email = $setting?->admin_contact_email ?? 'admin@mywap.my';
            return response()->json([
                'reply' => "Maaf, saya tidak dapat memproses soalan anda sekarang. Sila cuba sebentar lagi atau emel {$email} untuk bantuan.",
            ], 500);
        }

        $body = $response->json();
        $reply = $body['candidates'][0]['content']['parts'][0]['text']
            ?? 'Maaf, saya tidak dapat memproses soalan anda.';

        return response()->json([
            'reply' => $reply,
        ]);
    }
}
