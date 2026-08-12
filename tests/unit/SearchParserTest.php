<?php

use PHPUnit\Framework\TestCase;

final class SearchParserTest extends TestCase {

    public function testOperatorsAreExtracted(): void {
        $filter = Search::parse('tag:php user:42 answers:0 how do I cache');
        $this->assertSame("php", $filter["tag"]);
        $this->assertSame(42, $filter["user"]);
        $this->assertSame("unanswered", $filter["scope"]);
        $this->assertSame("how do I cache", $filter["q"]);
    }

    public function testPlainQueryStaysIntact(): void {
        $filter = Search::parse("mysql index performance");
        $this->assertSame("mysql index performance", $filter["q"]);
        $this->assertSame("", $filter["tag"]);
    }

    public function testIsOperator(): void {
        $this->assertSame("accepted", Search::parse("is:accepted x")["scope"]);
    }
}
