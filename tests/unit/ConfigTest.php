<?php

use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase {

    public function testDefaultsAreUsedWhenNothingIsStored(): void {
        $this->assertSame(30, Config::int("questions_per_page"));
        $this->assertSame("Askbot", Config::get("site_title"));
        $this->assertNull(Config::get("does_not_exist"));
        $this->assertSame("fallback", Config::get("does_not_exist", "fallback"));
    }

    public function testBooleanParsing(): void {
        $this->assertTrue(Config::bool("registration_open"));
        $this->assertFalse(Config::bool("does_not_exist"));
    }

    public function testBaseUrlHasNoTrailingSlash(): void {
        putenv("BASE_URL=https://example.com/");
        $this->assertSame("https://example.com", Config::baseUrl());
        putenv("BASE_URL");
    }
}
