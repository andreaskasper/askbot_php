<?php

use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase {

    public function testGermanUmlautsBecomeReadableAscii(): void {
        $this->assertSame("koennen-wir-ueber-strassen-reden", Slug::make("Können wir über Straßen reden"));
    }

    public function testSlugIsUrlSafeAndTrimmed(): void {
        $this->assertSame("hello-world", Slug::make("  Hello, World!  "));
        $this->assertSame("post", Slug::make("???"));
        $this->assertLessThanOrEqual(20, strlen(Slug::make(str_repeat("a", 100), 20)));
    }

    public function testTagNormalisation(): void {
        $this->assertSame("c++", Slug::tag("C++"));
        $this->assertSame("asp.net", Slug::tag("ASP.NET"));
        $this->assertSame("machine-learning", Slug::tag("Machine Learning"));
        $this->assertSame("", Slug::tag("   "));
    }
}
