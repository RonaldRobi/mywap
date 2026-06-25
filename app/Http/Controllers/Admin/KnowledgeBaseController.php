<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Smalot\PdfParser\Parser as PdfParser;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request): Response
    {
        $query = KnowledgeArticle::query()->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                  ->orWhere('answer', 'like', "%{$search}%")
                  ->orWhere('keywords', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        return Inertia::render('Admin/KnowledgeBaseManage', [
            'articles' => $query->paginate(15)->withQueryString(),
            'categories' => KnowledgeArticle::CATEGORIES,
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['nullable', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'in:' . implode(',', KnowledgeArticle::CATEGORIES)],
            'document' => ['nullable', 'file', 'mimes:txt,pdf', 'max:10240'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $article = new KnowledgeArticle;
        $article->question = $data['question'] ?? null;
        $article->keywords = $data['keywords'] ?? null;
        $article->category = $data['category'] ?? null;
        $article->is_active = (bool) ($data['is_active'] ?? true);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $storedPath = $file->store('knowledge', 'public');
            $article->document_path = '/storage/' . ltrim($storedPath, '/');

            if ($file->getClientOriginalExtension() === 'txt') {
                $article->document_content = file_get_contents($file->getRealPath());
            } elseif ($file->getClientOriginalExtension() === 'pdf') {
                $article->document_content = $this->extractPdfText($file->getRealPath());
            }

            $article->answer = $data['answer'] ?: ($article->document_content
                ? substr($article->document_content, 0, 500)
                : '');
        } else {
            $article->answer = $data['answer'];
        }

        $article->save();

        return back()->with('success', 'Artikel pengetahuan berjaya ditambah.');
    }

    public function update(Request $request, KnowledgeArticle $knowledgeArticle): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['nullable', 'string', 'max:255'],
            'answer' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'in:' . implode(',', KnowledgeArticle::CATEGORIES)],
            'document' => ['nullable', 'file', 'mimes:txt,pdf', 'max:10240'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $article = $knowledgeArticle;
        $article->question = $data['question'] ?? $article->question;
        $article->keywords = $data['keywords'] ?? $article->keywords;
        $article->category = $data['category'] ?? $article->category;
        $article->is_active = $request->has('is_active') ? (bool) $data['is_active'] : $article->is_active;

        if ($request->hasFile('document')) {
            if ($article->document_path) {
                $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $article->document_path, PHP_URL_PATH) ?? ''), '/');
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $file = $request->file('document');
            $storedPath = $file->store('knowledge', 'public');
            $article->document_path = '/storage/' . ltrim($storedPath, '/');

            if ($file->getClientOriginalExtension() === 'txt') {
                $article->document_content = file_get_contents($file->getRealPath());
            } elseif ($file->getClientOriginalExtension() === 'pdf') {
                $article->document_content = $this->extractPdfText($file->getRealPath());
            }
        }

        if ($request->has('answer')) {
            $article->answer = $data['answer'] ?: $article->answer;
        }

        $article->save();

        return back()->with('success', 'Artikel pengetahuan berjaya dikemas kini.');
    }

    public function destroy(KnowledgeArticle $knowledgeArticle): RedirectResponse
    {
        if ($knowledgeArticle->document_path) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $knowledgeArticle->document_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $knowledgeArticle->delete();

        return back()->with('success', 'Artikel pengetahuan berjaya dipadam.');
    }

    private function extractPdfText(string $filePath): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            $text = preg_replace('/\s+/', ' ', $text);
            return trim(substr($text, 0, 10000));
        } catch (\Exception $e) {
            return '';
        }
    }
}
