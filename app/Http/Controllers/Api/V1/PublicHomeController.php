<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Article;
use App\Models\DashboardBanner;
use App\Models\Event;
use App\Models\Infaq;
use App\Models\LibraryItem;
use App\Models\NewsPost;
use App\Models\Video;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * PublicHomeController
 *
 * Kandungan untuk skrin Beranda awam (tanpa log masuk). Memapar banner
 * dashboard admin + artikel, video, pustaka, info terkini, program dan infaq
 * supaya aplikasi berguna kepada orang awam — bukan hanya ahli.
 */
class PublicHomeController extends Controller
{
    public function index(): JsonResponse
    {
        $setting = AppSetting::singleton();

        return ApiResponse::success([
            'logo_url' => $setting?->system_logo_path
                ? url($setting->system_logo_path)
                : null,
            'banners' => $this->section('banners', fn () => $this->banners()),
            'articles' => $this->section('articles', fn () => $this->articles()),
            'news' => $this->section('news', fn () => $this->news()),
            'videos' => $this->section('videos', fn () => $this->videos()),
            'library' => $this->section('library', fn () => $this->library()),
            'events' => $this->section('events', fn () => $this->events()),
            'infaqs' => $this->section('infaqs', fn () => $this->infaqs()),
        ]);
    }

    /**
     * Jalankan satu seksyen secara berasingan supaya kegagalan satu domain
     * (cth lajur DB yang belum dimigrasi) tidak meruntuhkan seluruh Beranda
     * awam. Ralat tetap direkodkan untuk siasatan.
     */
    private function section(string $name, callable $callback): array
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            Log::warning('public/home section failed', [
                'section' => $name,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function banners(): array
    {
        return DashboardBanner::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->take(8)
            ->get()
            ->map(fn (DashboardBanner $banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'image_path' => $banner->image_path,
                'link_url' => $banner->link_url,
                'link_target' => $banner->link_target,
            ])
            ->values()
            ->all();
    }

    private function articles(): array
    {
        return Article::query()
            ->with(['author' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name')])
            ->where('is_published', true)
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->latest('published_at')
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn (Article $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'cover_image_path' => $article->cover_image_path,
                'author_name' => $article->author?->name ?? 'Admin',
                'published_at' => $article->published_at?->toDateString(),
            ])
            ->values()
            ->all();
    }

    private function news(): array
    {
        return NewsPost::query()
            ->with(['category:id,name'])
            ->where('is_published', true)
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->latest('published_at')
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn (NewsPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'cover_image_path' => $post->cover_image_path,
                'category_name' => $post->category?->name ?? 'Umum',
                'published_at' => $post->published_at?->toDateString(),
            ])
            ->values()
            ->all();
    }

    private function videos(): array
    {
        return Video::query()
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Video $video) => [
                'id' => $video->id,
                'title' => $video->title,
                'youtube_id' => $video->youtube_id,
                'thumbnail_url' => $video->thumbnail_url,
                'embed_url' => $video->embed_url,
            ])
            ->values()
            ->all();
    }

    private function library(): array
    {
        return LibraryItem::query()
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (LibraryItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'category' => $item->category,
                'file_path' => $item->file_path,
                'cover_image_path' => $item->cover_image_path,
            ])
            ->values()
            ->all();
    }

    private function events(): array
    {
        return Event::query()
            ->with('organization')
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->take(5)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'type' => $event->type,
                'location_or_link' => $event->location_or_link,
                'start_formatted' => $event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                'featured_image_url' => $event->featured_image_url,
                'organization_name' => $event->organization?->name ?? 'Semua Organisasi',
            ])
            ->values()
            ->all();
    }

    private function infaqs(): array
    {
        return Infaq::query()
            ->with('organization:id,name')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn (Infaq $infaq) => [
                'id' => $infaq->id,
                'title' => $infaq->title,
                'slug' => $infaq->slug,
                'image_path' => $infaq->image_path,
                'type' => $infaq->type,
                'target_amount' => $infaq->target_amount,
                'collected_amount' => $infaq->collected_amount,
                'progress_percent' => $infaq->progress_percent,
                'organization_name' => $infaq->organization?->name,
            ])
            ->values()
            ->all();
    }
}
