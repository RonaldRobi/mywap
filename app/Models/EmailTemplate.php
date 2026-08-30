<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'key',
        'subject',
        'body',
        'header_image_path',
    ];

    public static function forKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    public function headerImageUrl(): ?string
    {
        return $this->header_image_path
            ? url($this->header_image_path)
            : null;
    }

    public function renderSubject(array $data = []): string
    {
        return $this->replacePlaceholders($this->subject, $data);
    }

    public function renderBody(array $data = []): string
    {
        return $this->replacePlaceholders($this->body, $data);
    }

    private function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) $value, $text);
        }

        return $text;
    }
}
