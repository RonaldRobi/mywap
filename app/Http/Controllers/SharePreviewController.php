<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Infaq;
use App\Models\NewsPost;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SharePreviewController extends Controller
{
    public function info(NewsPost $newsPost): View
    {
        abort_if(! $newsPost->is_published, 404);
        abort_if($newsPost->published_at && $newsPost->published_at->isFuture(), 404);

        return $this->renderPreview(
            title: $newsPost->title,
            description: $newsPost->excerpt ?: Str::limit(strip_tags((string) $newsPost->content), 160),
            imageUrl: $this->absoluteUrl($newsPost->cover_image_path),
            pageUrl: route('share.info', $newsPost, true),
            redirectUrl: route('news.show', $newsPost, true),
            type: 'article'
        );
    }

    public function article(\App\Models\Article $article): View
    {
        abort_if(! $article->is_published, 404);
        abort_if($article->published_at && $article->published_at->isFuture(), 404);

        return $this->renderPreview(
            title: $article->title,
            description: $article->excerpt ?: Str::limit(strip_tags((string) $article->content), 160),
            imageUrl: $this->absoluteUrl($article->cover_image_path),
            pageUrl: route('share.article', $article, true),
            redirectUrl: route('articles.show', $article, true),
            type: 'article'
        );
    }

    public function infaq(Infaq $infaq): View
    {
        abort_if(! $infaq->is_active, 404);

        return $this->renderPreview(
            title: $infaq->title,
            description: strip_tags((string) $infaq->description) ?: 'Sertai kempen infaq ini sekarang.',
            imageUrl: $this->absoluteUrl($infaq->image_path),
            pageUrl: route('share.infaq', $infaq, true),
            redirectUrl: route('infaq.show', $infaq, true),
            type: 'article'
        );
    }

    public function event(Event $event): View
    {
        return $this->renderPreview(
            title: $event->title,
            description: $event->description ?: 'Program komuniti terkini. Jom sertai bersama.',
            imageUrl: $this->absoluteUrl($event->featured_image_url),
            pageUrl: route('share.event', $event, true),
            redirectUrl: route('events.show', $event->slug, true),
            type: 'article'
        );
    }

    private function renderPreview(
        string $title,
        string $description,
        ?string $imageUrl,
        string $pageUrl,
        string $redirectUrl,
        string $type = 'website'
    ): View {
        $fallbackLogo = null;
        $siteName = config('app.name', 'myWAP');

        if (Schema::hasTable('app_settings')) {
            $setting = AppSetting::singleton();
            $fallbackLogo = $setting->system_logo_path;
            $siteName = $setting->app_name ?: $siteName;
        }

        $resolvedImage = $imageUrl ?: $this->absoluteUrl($fallbackLogo) ?: asset('apple-touch-icon.png');

        return view('share.preview', [
            'siteName' => $siteName,
            'metaTitle' => $title,
            'metaDescription' => Str::limit(trim($description), 200),
            'metaImage' => $resolvedImage,
            'metaUrl' => $pageUrl,
            'metaType' => $type,
            'redirectUrl' => $redirectUrl,
        ]);
    }

    private function absoluteUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url($url);
    }
}
