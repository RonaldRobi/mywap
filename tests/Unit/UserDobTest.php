<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserDobTest extends TestCase
{
    public function test_it_parses_birth_year_2000_correctly(): void
    {
        // "00" must resolve to 2000, not 1900.
        $this->assertSame('2000-01-01', User::parseDobFromIc('000101101234'));
        $this->assertSame('2000-12-31', User::parseDobFromIc('001231101234'));
    }

    public function test_it_accepts_feb_29_because_the_ic_has_no_century(): void
    {
        $this->assertSame('2000-02-29', User::parseDobFromIc('000229101234'));
    }

    public function test_it_parses_past_century_years(): void
    {
        $this->assertSame('1999-01-01', User::parseDobFromIc('990101101234'));
        $this->assertSame('1985-06-15', User::parseDobFromIc('850615101234'));
    }

    public function test_century_pivot_is_dynamic_and_tracks_the_current_year(): void
    {
        $currentYY = (int) date('y');

        $currentIc = str_pad((string) $currentYY, 2, '0', STR_PAD_LEFT).'0101101234';
        $this->assertSame(date('Y').'-01-01', User::parseDobFromIc($currentIc));

        // The next two-digit year is 100 years ago, not the future.
        $nextYY = ($currentYY + 1) % 100;
        $nextIc = str_pad((string) $nextYY, 2, '0', STR_PAD_LEFT).'0101101234';
        $expected = ((int) date('Y') + 1 - 100).'-01-01';
        $this->assertSame($expected, User::parseDobFromIc($nextIc));
    }

    public function test_it_rejects_invalid_or_too_short_input(): void
    {
        $this->assertNull(User::parseDobFromIc(null));
        $this->assertNull(User::parseDobFromIc(''));
        $this->assertNull(User::parseDobFromIc('0001'));
        $this->assertNull(User::parseDobFromIc('001301101234'));
        $this->assertNull(User::parseDobFromIc('000032101234'));
    }
}
