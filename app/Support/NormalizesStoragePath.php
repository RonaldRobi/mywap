<?php

namespace App\Support;

trait NormalizesStoragePath
{
    protected function normalizeStoragePath(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        $parsedPath = parse_url($value, PHP_URL_PATH);

        if (is_string($parsedPath) && str_starts_with($parsedPath, '/storage/')) {
            return $parsedPath;
        }

        return $value;
    }
}
