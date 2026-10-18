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
}
