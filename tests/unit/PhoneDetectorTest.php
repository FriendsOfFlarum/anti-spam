<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\unit;

use FoF\AntiSpam\ContentFilter\Detectors\PhoneDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PhoneDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string>}>
     */
    public static function phoneNumbers(): iterable
    {
        yield 'plus, spaced' => ['WhatsApp +91 98765 43210 for cheap followers', ['+91 98765 43210']];
        yield 'plus, dashed' => ['Call +1-234-567-8900 now', ['+1-234-567-8900']];
        yield 'plus, dotted' => ['Ring +44.20.7946.0958 today', ['+44.20.7946.0958']];
        yield 'plus, unbroken' => ['Text +12345678901', ['+12345678901']];
        yield 'double zero prefix' => ['Call 00 123 456 7890 today', ['00 123 456 7890']];
        yield 'trunk zero in brackets' => ['Ring +44 (0)20 7946 0958', ['+44 (0)20 7946 0958']];
        yield 'at the start' => ['+91 98765 43210 is my number', ['+91 98765 43210']];
        yield 'after punctuation' => ['Number:+91 98765 43210.', ['+91 98765 43210']];

        // Evasions the old pattern caught and the new one must keep catching.
        yield 'fullwidth digits' => ['Call ＋９１ ９８７６５ ４３２１０', ['＋９１ ９８７６５ ４３２１０']];
        yield 'arabic-indic digits' => ['اتصل +٩١٩٨٧٦٥٤٣٢١٠', ['+٩١٩٨٧٦٥٤٣٢١٠']];
        yield 'no-break spaces' => ["Call +91\u{00A0}98765\u{00A0}43210", ["+91\u{00A0}98765\u{00A0}43210"]];
        yield 'narrow no-break spaces' => ["Call +91\u{202F}98765\u{202F}43210", ["+91\u{202F}98765\u{202F}43210"]];
        yield 'space after the plus' => ['Call + 91 98765 43210', ['+ 91 98765 43210']];
        yield 'spaced dashes' => ['Call +91 - 98765 - 43210', ['+91 - 98765 - 43210']];
        yield 'en dash' => ["Call +91\u{2013}98765\u{2013}43210", ["+91\u{2013}98765\u{2013}43210"]];
        yield 'bracketed country code' => ['Call +(91) 98765 43210', ['+(91) 98765 43210']];
        yield 'glued to a word' => ['call+919876543210', ['+919876543210']];
        yield 'after a full stop' => ['tel.+919876543210', ['+919876543210']];
        yield 'followed by a word' => ['+919876543210WhatsApp me', ['+919876543210']];
        yield 'followed by an underscore' => ['+919876543210_', ['+919876543210']];
        yield 'followed by a year' => ['Call +919876543210 2024 only', ['+919876543210']];
        yield 'two numbers in a row' => ['+919876543210 919876543211', ['+919876543210']];
        yield 'no trailing space before a bracket' => ['Dial +14155552671 (E164)', ['+14155552671']];
    }

    #[Test]
    #[DataProvider('phoneNumbers')]
    public function it_finds_international_phone_numbers(string $content, array $expected): void
    {
        $this->assertSame($expected, PhoneDetector::extractPhoneNumbers($content));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notPhoneNumbers(): iterable
    {
        // Every one of these was read as a phone number, holding an ordinary post for approval.
        yield 'date and time' => ['Released 2008-12-01 12:30, still works'];
        yield 'grouped large number' => ['We have 100 000 000 rows in that table'];
        yield 'column of numbers' => ['memory: 1000 1000 1000 1000'];
        yield 'order number' => ['Order #10012345678 never arrived'];
        yield 'timestamp' => ['Timestamp 1700000000000 in ms'];
        yield 'arithmetic' => ['Answer is 3 + 4 - 5 - 6 - 7'];
        yield 'log line' => ['[2026-10-18 10:00:01] production.ERROR: 500 500 500'];
        yield 'version range' => ['Tested with +2.0.0 and 00.1.2'];
        yield 'short number' => ['Call +44 1234'];
        yield 'too long to be a number' => ['Hash +1234567890123456789'];
        yield 'plus inside a word' => ['Use c++11 or g++ 12345678901'];
        yield 'diff line' => ['+ 1 2 3 4 5 6 7 8 9'];
        yield 'time then a number' => ['At 12:00 1234567890 rows were read'];
        yield 'labelled zero-padded id' => ['ID:00123456789'];
        yield 'dashed zero-padded id' => ['INV-00123456789'];
        yield 'hashed zero-padded id' => ['Order #00123456789'];
        yield 'path segment' => ['GET /api/00123456789'];
    }

    #[Test]
    public function a_huge_run_of_digits_neither_errors_nor_hides_an_earlier_number(): void
    {
        // A run long enough to exhaust the regex engine's stack used to abort the whole match,
        // discarding the real number before it: content that breaks the detector got through.
        $content = 'Call +919876543210 now. +'.str_repeat('9', 100000);

        $this->assertSame(['+919876543210'], PhoneDetector::extractPhoneNumbers($content));
    }

    #[Test]
    #[DataProvider('notPhoneNumbers')]
    public function it_leaves_other_numbers_alone(string $content): void
    {
        $this->assertSame([], PhoneDetector::extractPhoneNumbers($content));
    }

    /**
     * @return iterable<string, array{string, array<string>}>
     */
    public static function localPhoneNumbers(): iterable
    {
        // The posts that got through: a WhatsApp number written the way people in Indonesia write it.
        yield 'indonesian mobile, dashed' => ['layanan BCA WhatsApp Halo BCA 0811-2506-6288 resmi BCA atau 1500888', ['0811-2506-6288']];
        yield 'after a bracketed short code' => ['Halo BCA melalui (1500888) 0811-2506-6288 tersedia 24 jam', ['0811-2506-6288']];
        yield 'twice' => ['WA 0811-2506-6288 atau 0821-4426-2630.', ['0811-2506-6288', '0821-4426-2630']];
        yield 'spaced' => ['Ring 07700 900123 now', ['07700 900123']];
        yield 'dotted' => ['WA 0811.2506.6288', ['0811.2506.6288']];
        yield 'unbroken' => ['WA 081125066288 sekarang', ['081125066288']];
        yield 'bracketed area code' => ['Kantor (021) 5555 1234', ['(021) 5555 1234']];
        yield 'landline' => ['Call 020 7946 0958', ['020 7946 0958']];
        yield 'pairs of digits' => ['Appelez le 01 23 45 67 89', ['01 23 45 67 89']];
        yield 'followed by a year' => ['WA 0811-2506-6288 2024', ['0811-2506-6288']];
        yield 'fullwidth digits' => ['WA ０８１１-２５０６-６２８８', ['０８１１-２５０６-６２８８']];
        // An international candidate too long to be a number is not a number, and must not take
        // the local one after it down with it.
        // Only the group glued to the word is lost, not the number before it.
        yield 'followed by a group glued to a word' => ['Call 07700 900123 24h', ['07700 900123']];
        yield 'after an overlong international candidate' => ['Hash +1234567890123456 0811-2506-6288', ['0811-2506-6288']];
    }

    #[Test]
    #[DataProvider('localPhoneNumbers')]
    public function it_finds_local_phone_numbers(string $content, array $expected): void
    {
        $this->assertSame($expected, PhoneDetector::extractLocalPhoneNumbers($content));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notLocalPhoneNumbers(): iterable
    {
        yield 'short code' => ['Call 1500888 any time'];
        yield 'day-first date and time' => ['Posted 01-02-2024 10:00'];
        yield 'day-first date, dotted' => ['Updated 01.02.2024 and again 03.04.2025'];
        yield 'iso date' => ['Released 2008-12-01 12:30'];
        yield 'version' => ['Upgrade to 0.12.3456 7890'];
        yield 'too short' => ['Dial 0811 2506'];
        yield 'too long' => ['Hash 012345678901234567'];
        yield 'run of zeros' => ['Balance 0000 0000 0000'];
        yield 'glued to a word' => ['ID0811-2506-6288'];
        yield 'labelled id' => ['ID:081125066288'];
        yield 'hashed id' => ['Order #081125066288'];
        yield 'dashed id' => ['INV-081125066288'];
        yield 'path segment' => ['GET /api/081125066288'];
        yield 'decimal' => ['Rate 3.0811250662'];
        yield 'ip address' => ['Server 010.123.456.789'];
        yield 'single-digit groups' => ['Steps 0 1 2 3 4 5 6 7 8 9'];
        yield 'query string' => ['See /page?ref=081125066288'];
        yield 'glued to letters after' => ['Ref 08123456789AB'];
        yield 'glued to an underscore after' => ['Ref 08123456789_x'];
        yield 'list of pairs' => ['Scores 05 12 23 34 45 56'];
        yield 'bracketed labelled id' => ['Invoice:(081125066288)'];
        // International numbers are the other extractor's: counting them again would score them twice.
        yield 'international with a trunk zero' => ['Call +62 0811-2506-6288'];
        yield 'international' => ['Call +44 20 7946 0958'];
    }

    #[Test]
    #[DataProvider('notLocalPhoneNumbers')]
    public function it_leaves_other_numbers_alone_when_looking_for_local_ones(string $content): void
    {
        $this->assertSame([], PhoneDetector::extractLocalPhoneNumbers($content));
    }

    #[Test]
    public function many_short_candidates_glued_to_a_word_are_read_in_linear_time(): void
    {
        // Each `0` after a space can start a candidate; a check that fails after the whole run
        // would read the rest of it again from every one of them.
        $content = rtrim(str_repeat('01 ', 21000)).'x';

        $start = microtime(true);
        $this->assertSame([], PhoneDetector::extractLocalPhoneNumbers($content));
        $this->assertLessThan(0.5, microtime(true) - $start);
    }

    #[Test]
    public function many_international_numbers_are_blanked_in_linear_time(): void
    {
        $content = str_repeat('+44 20 7946 0958 ', 3700);

        $start = microtime(true);
        $this->assertSame([], PhoneDetector::extractLocalPhoneNumbers($content));
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    #[Test]
    public function a_huge_run_of_digits_does_not_break_the_local_pattern(): void
    {
        $content = 'WA 0811-2506-6288 now. 0'.str_repeat('9', 100000);

        $this->assertSame(['0811-2506-6288'], PhoneDetector::extractLocalPhoneNumbers($content));
    }
}
