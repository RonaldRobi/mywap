<?php

namespace App\Enums;

enum EventCategory: string
{
    case Muktamar = 'muktamar';
    case Ijtimak = 'ijtimak';
    case Seminar = 'seminar';
    case Kursus = 'kursus';
    case Kem = 'kem';
    case Bengkel = 'bengkel';
    case Konvensyen = 'konvensyen';
    case Lain = 'lain';

    public function label(): string
    {
        return match ($this) {
            self::Muktamar => 'Muktamar',
            self::Ijtimak => 'Ijtimak',
            self::Seminar => 'Seminar',
            self::Kursus => 'Kursus',
            self::Kem => 'Kem',
            self::Bengkel => 'Bengkel',
            self::Konvensyen => 'Konvensyen',
            self::Lain => 'Lain-lain',
        };
    }
}
