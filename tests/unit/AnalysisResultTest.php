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

use FoF\AntiSpam\ContentFilter\AnalysisResult;
use FoF\AntiSpam\ContentFilter\SpamScore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AnalysisResultTest extends TestCase
{
    private function analysis(SpamScore ...$scores): AnalysisResult
    {
        $named = [];
        foreach ($scores as $i => $score) {
            $named["Detector {$i}"] = $score;
        }

        return new AnalysisResult(50, $named, true, true, false);
    }

    #[Test]
    public function the_flag_names_what_tripped_each_reason()
    {
        $result = $this->analysis(new SpamScore(50, ['Contains a phone number', 'Other'], [], [0 => '+1234567890']));

        $this->assertSame(['[Detector 0] Contains a phone number: +1234567890', '[Detector 0] Other'], $result->getFlagReasons());
    }

    #[Test]
    public function the_reasons_that_are_logged_leave_it_out()
    {
        $result = $this->analysis(new SpamScore(50, ['Contains a phone number'], [], [0 => '+1234567890']));

        $this->assertSame(['[Detector 0] Contains a phone number'], $result->getAllReasons());
    }

    #[Test]
    public function merging_keeps_evidence_with_its_reason()
    {
        $score = new SpamScore(30, ['First'], [], [0 => 'a']);
        $score->merge(new SpamScore(30, ['Second', 'Third'], [], [1 => 'c']));

        $this->assertSame([0 => 'a', 2 => 'c'], $score->getEvidence());
    }
}
