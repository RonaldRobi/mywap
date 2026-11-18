<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Article;
use App\Models\Event;
use App\Models\Form;
use App\Models\Infaq;
use App\Models\LibraryItem;
use App\Models\MediaStatement;
use App\Models\NewsPost;
use App\Models\Organization;
use App\Models\Product;
use App\Services\RegistrationShareService;
use App\Support\RegistrationPoster;
use App\Support\ShareUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            pageUrl: ShareUrl::route('share.info', $newsPost),
            redirectUrl: ShareUrl::route('news.show', $newsPost),
            type: 'article'
        );
    }

    public function article(Article $article): View
    {
        abort_if(! $article->is_published, 404);
        abort_if($article->published_at && $article->published_at->isFuture(), 404);

        return $this->renderPreview(
            title: $article->title,
            description: $article->excerpt ?: Str::limit(strip_tags((string) $article->content), 160),
            imageUrl: $this->absoluteUrl($article->cover_image_path),
            pageUrl: ShareUrl::route('share.article', $article),
            redirectUrl: ShareUrl::route('articles.show', $article),
            type: 'article'
        );
    }

    public function mediaStatement(MediaStatement $mediaStatement): View
    {
        abort_if(! $mediaStatement->is_published, 404);
        abort_if($mediaStatement->published_at && $mediaStatement->published_at->isFuture(), 404);
        $description = Str::limit(strip_tags((string) $mediaStatement->content), 160);

        return $this->renderPreview(
            title: $mediaStatement->title,
            description: $description ?: 'Kenyataan media rasmi. Muat turun kenyataan penuh dalam format PDF.',
            imageUrl: $this->absoluteUrl($mediaStatement->featured_image_path),
            pageUrl: ShareUrl::route('share.media-statement', $mediaStatement),
            redirectUrl: ShareUrl::route('media-statements.show', $mediaStatement),
            type: 'article'
        );
    }

    public function pustaka(LibraryItem $libraryItem): View
    {
        return $this->renderPreview(
            title: $libraryItem->title ?: 'Pustaka myWAP',
            description: Str::limit(strip_tags((string) $libraryItem->description), 160)
                ?: 'Buku digital dalam Pustaka myWAP.',
            imageUrl: $this->absoluteUrl($libraryItem->cover_image_path),
            pageUrl: ShareUrl::route('share.pustaka', $libraryItem),
            redirectUrl: ShareUrl::route('member.library'),
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
            pageUrl: ShareUrl::route('share.infaq', $infaq),
            redirectUrl: ShareUrl::route('infaq.show', $infaq),
            type: 'article'
        );
    }

    public function event(Event $event): View
    {
        // Satu borang aktif → kongsi terus ke borang pendaftaran (elak langkah
        // overview). Lebih dari satu / tiada borang → halaman overview (pilih borang).
        $activeForms = $event->activeForms;

        $redirectUrl = $activeForms->count() === 1
            ? ShareUrl::route('events.register.public', $activeForms->first()->share_token)
            : ShareUrl::route('events.show', $event->slug);

        return $this->renderPreview(
            title: $event->title,
            description: $event->description ?: 'Program komuniti terkini. Jom sertai bersama.',
            imageUrl: $this->absoluteUrl($event->featured_image_url),
            pageUrl: ShareUrl::route('share.event', $event),
            redirectUrl: $redirectUrl,
            type: 'article'
        );
    }

    public function form(Form $form): View
    {
        abort_if(! $form->is_active, 404);

        // Use the form's header image if available, otherwise fall back to the global OG image / logo
        $imageUrl = $form->header_image_path
            ? $this->absoluteUrl('/storage/'.$form->header_image_path)
            : null;

        // Borang pendaftaran event → teruskan ke halaman daftar.
        $redirectUrl = $form->event_id
            ? ShareUrl::route('events.register.public', $form->share_token)
            : ShareUrl::route('forms.public', ['token' => $form->share_token]);

        return $this->renderPreview(
            title: $form->title,
            description: $form->description ?: 'Sila isi borang ini.',
            imageUrl: $imageUrl,
            pageUrl: ShareUrl::route('share.form', $form),
            redirectUrl: $redirectUrl,
            type: 'article'
        );
    }

    public function register(Request $request, RegistrationShareService $share): View
    {
        $organization = $this->resolveSharedOrganization($request);
        $referralCode = $this->referralCode($request);
        $context = $share->context($organization, $referralCode);

        $params = array_filter([
            'org' => $organization?->slug,
            'ref' => $referralCode,
        ]);

        return $this->renderPreview(
            title: $context['title'],
            description: $context['description'],
            imageUrl: ShareUrl::route('share.register.image', $params),
            pageUrl: ShareUrl::route('share.register', $params),
            redirectUrl: ShareUrl::route('register', array_filter(['ref' => $referralCode])),
            type: 'website'
        );
    }

    public function registerImage(Request $request, RegistrationShareService $share): Response
    {
        $organization = $this->resolveSharedOrganization($request);
        $context = $share->context($organization, $this->referralCode($request));

        return response(RegistrationPoster::render($organization, $context['register_url']), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function product(Product $product): View
    {
        // Drafts are not shareable; only published mall items get a preview.
        abort_if(! $product->status, 404);

        return $this->renderPreview(
            title: $product->name,
            description: Str::limit(strip_tags((string) $product->description), 160)
                ?: 'Lihat produk ini di myWAP Mall.',
            imageUrl: $this->productImageUrl($product->image),
            pageUrl: ShareUrl::route('share.product', $product),
            redirectUrl: ShareUrl::route('mall.show', $product),
            type: 'product'
        );
    }

    /**
     * Product images are stored as bare relative paths (e.g. "products/x.jpg").
     * Promote them to an absolute "/storage/..." URL for social crawlers.
     */
    private function productImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/storage/'])) {
            return $this->absoluteUrl($path);
        }

        return $this->absoluteUrl('/storage/'.ltrim($path, '/'));
    }

    private function resolveSharedOrganization(Request $request): ?Organization
    {
        $slug = $request->query('org');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Organization::query()
            ->where('slug', $slug)
            ->where('slug', '!=', 'management')
            ->first();
    }

    private function referralCode(Request $request): ?string
    {
        $referralCode = $request->query('ref');

        if (! is_string($referralCode) || $referralCode === '') {
            return null;
        }

        return Str::limit($referralCode, 32, '');
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

        $resolvedImage = $imageUrl
            ?: $this->absoluteUrl($fallbackLogo)
            ?: $this->absoluteUrl('/apple-touch-icon.png');

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

        $base = rtrim((string) config('app.share_url', config('app.url')), '/');

        return $base.'/'.ltrim($url, '/');
    }
}
