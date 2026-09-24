<?php

namespace Tests\Unit;

use App\Support\Pakistan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PakistanTest extends TestCase
{
    #[DataProvider('mobileShapes')]
    public function test_every_way_a_customer_writes_their_number_normalises_the_same(string $input): void
    {
        $this->assertSame('03001234567', Pakistan::normalizeMobile($input));
    }

    public static function mobileShapes(): array
    {
        return [
            'local' => ['03001234567'],
            'local spaced' => ['0300 1234567'],
            'local dashed' => ['0300-1234567'],
            'no trunk zero' => ['3001234567'],
            'plus country code' => ['+923001234567'],
            'plus code spaced' => ['+92 300 1234567'],
            'bare country code' => ['923001234567'],
            'double zero code' => ['00923001234567'],
            'double zero dashed' => ['0092-300-1234567'],
            'brackets and spaces' => ['(0300) 123 4567'],
        ];
    }

    #[DataProvider('rejectedMobiles')]
    public function test_numbers_that_are_not_pakistani_mobiles_are_rejected(string $input): void
    {
        $this->assertSame('', Pakistan::normalizeMobile($input));
        $this->assertFalse(Pakistan::isValidMobile($input));
    }

    public static function rejectedMobiles(): array
    {
        return [
            'landline karachi' => ['0213456789'],
            'landline lahore' => ['042 35678901'],
            'too short' => ['0300123456'],
            'too long' => ['030012345678'],
            'not a mobile' => ['0400 1234567'],
            'uk number' => ['+447700900123'],
            'letters' => ['not a phone'],
            'empty' => [''],
        ];
    }

    public function test_mobile_is_displayed_grouped(): void
    {
        $this->assertSame('0300 1234567', Pakistan::formatMobile('+92 300 1234567'));
    }

    public function test_cnic_normalises_and_formats_round_trip(): void
    {
        $this->assertSame('4210112345671', Pakistan::normalizeCnic('42101-1234567-1'));
        $this->assertSame('42101-1234567-1', Pakistan::formatCnic('4210112345671'));
        $this->assertSame('42101-1234567-1', Pakistan::formatCnic('42101-1234567-1'));
    }

    #[DataProvider('cnicCases')]
    public function test_cnic_validity(string $input, bool $valid): void
    {
        $this->assertSame($valid, Pakistan::isValidCnic($input));
    }

    public static function cnicCases(): array
    {
        return [
            'punjab' => ['35202-1234567-1', true],
            'sindh' => ['42101-1234567-1', true],
            'islamabad' => ['61101-1234567-9', true],
            'bare digits' => ['4210112345671', true],
            'province code 0' => ['02101-1234567-1', false],
            'province code 8' => ['82101-1234567-1', false],
            'province code 9' => ['92101-1234567-1', false],
            'all zeros' => ['00000-0000000-0', false],
            'twelve digits' => ['4210112345 67', false],
            'fourteen digits' => ['42101123456712', false],
            'empty' => ['', false],
        ];
    }

    public function test_an_unparseable_cnic_is_returned_untouched_for_display(): void
    {
        // The form re-renders whatever the customer typed so they can see the mistake.
        $this->assertSame('123', Pakistan::formatCnic('123'));
    }
}
