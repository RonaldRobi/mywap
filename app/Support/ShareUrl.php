<?php

namespace App\Support;

use Illuminate\Routing\UrlGenerator;

final class ShareUrl
{
    public static function route(string $name, mixed $parameters = []): string
    {
        $path = app(UrlGenerator::class)->route($name, $parameters, false);
        $base = rtrim((string) config('app.share_url', config('app.url')), '/');

        return $base.'/'.ltrim($path, '/');
    }
}
