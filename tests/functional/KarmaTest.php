<?php

use PHPUnit\Framework\TestCase;

final class KarmaTest extends TestCase {

    protected function setUp(): void {
        if (!hasDatabase()) $this->markTestSkipped("no database configured");
    }

    public function testAwardAndRevoke(): void {
        $user = User::create("karma_" . bin2hex(random_bytes(4)), "karma_" . bin2hex(random_bytes(4)) . "@example.com", "a-very-long-password");
        $before = (int)$user->karma;

        Karma::award($user->id(), "answer_upvote", 10, "answer", 1, 999);
        $user->refresh();
        $this->assertSame($before + 10, (int)$user->karma);

        Karma::revoke($user->id(), "answer_upvote", "answer", 1, 999);
        $user->refresh();
        $this->assertSame($before, (int)$user->karma);
    }

    public function testKarmaNeverDropsBelowOne(): void {
        $user = User::create("karma2_" . bin2hex(random_bytes(4)), "karma2_" . bin2hex(random_bytes(4)) . "@example.com", "a-very-long-password");
        Karma::award($user->id(), "moderation", -500, "none", null, null);
        $user->refresh();
        $this->assertSame(1, (int)$user->karma);
    }

    public function testDailyCapLimitsPositivePoints(): void {
        $user = User::create("karma3_" . bin2hex(random_bytes(4)), "karma3_" . bin2hex(random_bytes(4)) . "@example.com", "a-very-long-password");
        $cap = Config::int("karma_daily_cap", 200);

        $applied = 0;
        for ($i = 0; $i < 40; $i++) {
            $applied += Karma::award($user->id(), "answer_upvote", 10, "answer", $i, 500 + $i);
        }
        $this->assertLessThanOrEqual($cap, $applied);
    }
}
