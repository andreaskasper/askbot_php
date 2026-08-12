<?php

use PHPUnit\Framework\TestCase;

/**
 * The renderer is the main defence against stored XSS, so most of these cases
 * are attacks rather than formatting.
 */
final class MarkdownTest extends TestCase {

    public function testBasicFormatting(): void {
        $html = Markdown::render("This is **bold** and *italic* and `code`.");
        $this->assertStringContainsString("<strong>bold</strong>", $html);
        $this->assertStringContainsString("<em>italic</em>", $html);
        $this->assertStringContainsString("<code>code</code>", $html);
    }

    public function testHeadingsStartAtLevelThree(): void {
        // h1 and h2 belong to the page, not to a post.
        $this->assertStringContainsString("<h3>Title</h3>", Markdown::render("# Title"));
        $this->assertStringContainsString("<h4>Sub</h4>", Markdown::render("## Sub"));
    }

    public function testFencedCodeKeepsContentVerbatim(): void {
        $html = Markdown::render("```php\n<?php echo \"<b>x</b>\";\n```");
        $this->assertStringContainsString('<pre><code class="language-php">', $html);
        $this->assertStringContainsString("&lt;b&gt;x&lt;/b&gt;", $html);
        $this->assertStringNotContainsString("<b>x</b>", $html);
    }

    public function testRawHtmlIsEscaped(): void {
        $html = Markdown::render("<script>alert(1)</script>");
        $this->assertStringNotContainsString("<script>", $html);
        $this->assertStringContainsString("&lt;script&gt;", $html);
    }

    public function testImageAttributeBreakoutIsNotPossible(): void {
        // The quotes are escaped before the image rule ever sees them, so the
        // input stays plain text - no tag, no attribute, no handler.
        $html = Markdown::render('![a](" onerror="alert(1))');
        $this->assertStringNotContainsString("<img", $html);
        $this->assertStringNotContainsString('onerror="', $html);
    }

    /** @dataProvider dangerousUrls */
    public function testDangerousLinkSchemesAreDropped(string $url): void {
        $html = Markdown::render("[click](" . $url . ")");
        $this->assertStringNotContainsString("href=\"" . $url, $html);
        $this->assertStringNotContainsString("javascript", strtolower($html));
    }

    public static function dangerousUrls(): array {
        return [
            ["javascript:alert(1)"],
            ["JavaScript:alert(1)"],
            ["data:text/html;base64,PHNjcmlwdD4="],
            ["vbscript:msgbox(1)"],
        ];
    }

    public function testExternalLinksGetNofollowAndNoopener(): void {
        $html = Markdown::render("[ok](https://example.com)");
        $this->assertStringContainsString('rel="nofollow ugc noopener"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public function testListsAndQuotes(): void {
        $html = Markdown::render("- one\n- two\n\n> quoted");
        $this->assertStringContainsString("<ul>", $html);
        $this->assertStringContainsString("<blockquote>", $html);
    }

    public function testTable(): void {
        $html = Markdown::render("| a | b |\n|---|---|\n| 1 | 2 |");
        $this->assertStringContainsString("<table", $html);
        $this->assertStringContainsString("<th>a</th>", $html);
        $this->assertStringContainsString("<td>2</td>", $html);
    }

    public function testToTextStripsMarkup(): void {
        $text = Markdown::toText("# Title\n\nSome **bold** text with `code`.", 40);
        $this->assertStringNotContainsString("**", $text);
        $this->assertStringNotContainsString("#", $text);
        $this->assertLessThanOrEqual(40, mb_strlen($text));
    }
}
