<?php

use PHPUnit\Framework\TestCase;

final class RevisionDiffTest extends TestCase {

    public function testDiffMarksAddedAndRemovedLines(): void {
        $html = Revision::diffHtml("one\ntwo\nthree", "one\ntwo and a half\nthree");
        $this->assertStringContainsString("diff-del", $html);
        $this->assertStringContainsString("diff-add", $html);
        $this->assertStringContainsString("two and a half", $html);
    }

    public function testIdenticalTextHasNoChanges(): void {
        $html = Revision::diffHtml("same", "same");
        $this->assertStringNotContainsString("diff-add", $html);
        $this->assertStringNotContainsString("diff-del", $html);
    }

    public function testDiffEscapesHtml(): void {
        $html = Revision::diffHtml("", "<script>alert(1)</script>");
        $this->assertStringNotContainsString("<script>", $html);
    }
}
