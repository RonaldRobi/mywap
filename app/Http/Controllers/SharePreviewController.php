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
            description: $infaq->description ?: 'Sertai kempen infaq ini sekarang.',
            imageUrl: $this->absoluteUrl($infaq->image_path),
            pageUrl: route('share.infaq', $infaq, true),
            redirectUrl: route('infaq.show', $infaq, true),
            type: 'article'
        );
    }

    public function event(Event $event): View
    {
        $redirectUrl = route('events.index', [
            'tab' => 'upcoming',
            'search' => $event->title,
        ], true);

        return $this->renderPreview(
            title: $event->title,
            description: $event->description ?: 'Program komuniti terkini. Jom sertai bersama.',
            imageUrl: $this->absoluteUrl($event->featured_image_url),
            pageUrl: route('share.event', $event, true),
            redirectUrl: $redirectUrl,
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
        if (Schema::hasTable('app_settings')) {
            $fallbackLogo = AppSetting::singleton()->system_logo_path;
        }

        $resolvedImage = $imageUrl ?: $this->absoluteUrl($fallbackLogo) ?: asset('apple-touch-icon.png');

        return view('share.preview', [
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
