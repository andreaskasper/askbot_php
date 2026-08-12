<?php

/**
 * Markdown - renderer for post bodies.
 *
 * Deliberately safe by construction: the input is HTML escaped *first* and the
 * renderer only ever emits tags it generated itself. Raw HTML in a post is
 * therefore never executed, which removes the whole class of stored XSS bugs
 * that a sanitiser would otherwise have to catch.
 *
 * Supported: headings (h3-h6), fenced and indented code, inline code, bold,
 * italic, strikethrough, links, images, block quotes, ordered and unordered
 * lists, tables, horizontal rules, autolinks and @mentions.
 */
class Markdown {

    private const ALLOWED_SCHEMES = ["http", "https", "mailto", "ftp"];

    private array $codeBlocks = [];
    private array $codeSpans = [];

    public static function render(?string $text): string {
        $md = new self();
        return $md->toHtml((string)$text);
    }

    /** Plain text version, used for meta descriptions and mail. */
    public static function toText(?string $text, int $maxLength = 0): string {
        $t = (string)$text;
        $t = preg_replace('/```.*?```/s', " ", $t) ?? $t;
        $t = preg_replace('/`([^`]*)`/', '$1', $t) ?? $t;
        $t = preg_replace('/!\[[^\]]*\]\([^)]*\)/', " ", $t) ?? $t;
        $t = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $t) ?? $t;
        $t = preg_replace('/[#>*_~\-]+/', " ", $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', " ", $t) ?? $t);
        if ($maxLength > 0 && mb_strlen($t) > $maxLength) {
            $t = mb_substr($t, 0, $maxLength - 1) . "…";
        }
        return $t;
    }

    public function toHtml(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $text = $this->stashFencedCode($text);
        $text = $this->stashInlineCode($text);

        // Everything that is left is plain text - escape it once, here.
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");

        $html = $this->blocks($text);

        $html = $this->restoreInlineCode($html);
        $html = $this->restoreFencedCode($html);
        return $html;
    }

    // -----------------------------------------------------------------------
    // Code handling
    // -----------------------------------------------------------------------

    private function stashFencedCode(string $text): string {
        return preg_replace_callback('/^```([A-Za-z0-9_+-]*)\n(.*?)^```[ \t]*$/ms', function ($m) {
            $lang = strtolower($m[1]);
            $code = htmlspecialchars(rtrim($m[2], "\n"), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $class = $lang !== "" ? ' class="language-' . preg_replace('/[^a-z0-9+_-]/', "", $lang) . '"' : "";
            $key = "\x02CODEBLOCK" . count($this->codeBlocks) . "\x03";
            $this->codeBlocks[$key] = '<pre><code' . $class . '>' . $code . '</code></pre>';
            return "\n" . $key . "\n";
        }, $text) ?? $text;
    }

    private function stashInlineCode(string $text): string {
        return preg_replace_callback('/(`+)([^`]|[^`].*?[^`])\1(?!`)/s', function ($m) {
            $key = "\x02CODESPAN" . count($this->codeSpans) . "\x03";
            $this->codeSpans[$key] = '<code>' . htmlspecialchars(trim($m[2]), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . '</code>';
            return $key;
        }, $text) ?? $text;
    }

    private function restoreInlineCode(string $html): string {
        return $this->codeSpans === [] ? $html : strtr($html, $this->codeSpans);
    }

    private function restoreFencedCode(string $html): string {
        if ($this->codeBlocks === []) return $html;
        // A stashed block sitting alone in a paragraph does not need the <p>.
        foreach ($this->codeBlocks as $key => $block) {
            $html = str_replace("<p>" . $key . "</p>", $block, $html);
        }
        return strtr($html, $this->codeBlocks);
    }

    // -----------------------------------------------------------------------
    // Block level
    // -----------------------------------------------------------------------

    private function blocks(string $text): string {
        $lines = explode("\n", $text);
        $out = [];
        $paragraph = [];
        $listStack = [];   // ["ul"|"ol", ...]
        $inQuote = false;

        $flushParagraph = function () use (&$paragraph, &$out) {
            if ($paragraph === []) return;
            $out[] = "<p>" . $this->inline(implode("\n", $paragraph)) . "</p>";
            $paragraph = [];
        };
        $closeLists = function () use (&$listStack, &$out) {
            while ($listStack !== []) {
                $out[] = "</li></" . array_pop($listStack) . ">";
            }
        };
        $closeQuote = function () use (&$inQuote, &$out) {
            if ($inQuote) { $out[] = "</blockquote>"; $inQuote = false; }
        };

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            // Blank line
            if ($trimmed === "") {
                $flushParagraph();
                $closeLists();
                $closeQuote();
                continue;
            }

            // Stashed code block
            if (preg_match('/^\x02CODEBLOCK[0-9]+\x03$/', $trimmed)) {
                $flushParagraph(); $closeLists(); $closeQuote();
                $out[] = $trimmed;
                continue;
            }

            // Indented code block (4 spaces or a tab)
            if (preg_match('/^(?: {4}|\t)(.*)$/', $line, $m) && $paragraph === [] && $listStack === []) {
                $buffer = [$m[1]];
                while ($i + 1 < count($lines) && preg_match('/^(?: {4}|\t)(.*)$/', $lines[$i + 1], $m2)) {
                    $buffer[] = $m2[1];
                    $i++;
                }
                $out[] = "<pre><code>" . implode("\n", $buffer) . "</code></pre>";
                continue;
            }

            // Horizontal rule
            if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $trimmed)) {
                $flushParagraph(); $closeLists(); $closeQuote();
                $out[] = "<hr>";
                continue;
            }

            // Heading - h1/h2 are reserved for the page itself
            if (preg_match('/^(#{1,6})\s+(.*?)\s*#*$/', $trimmed, $m)) {
                $flushParagraph(); $closeLists(); $closeQuote();
                $level = min(6, max(3, strlen($m[1]) + 2));
                $out[] = "<h" . $level . ">" . $this->inline($m[2]) . "</h" . $level . ">";
                continue;
            }

            // Table
            if (str_contains($trimmed, "|") && isset($lines[$i + 1])
                && preg_match('/^\s*\|?[\s:|-]+\|[\s:|-]*$/', $lines[$i + 1])) {
                $flushParagraph(); $closeLists(); $closeQuote();
                $header = $this->tableCells($trimmed);
                $i += 2;
                $rows = [];
                while ($i < count($lines) && trim($lines[$i]) !== "" && str_contains($lines[$i], "|")) {
                    $rows[] = $this->tableCells(trim($lines[$i]));
                    $i++;
                }
                $i--;
                $html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
                $html .= "<thead><tr>";
                foreach ($header as $cell) $html .= "<th>" . $this->inline($cell) . "</th>";
                $html .= "</tr></thead><tbody>";
                foreach ($rows as $row) {
                    $html .= "<tr>";
                    foreach ($row as $cell) $html .= "<td>" . $this->inline($cell) . "</td>";
                    $html .= "</tr>";
                }
                $out[] = $html . "</tbody></table></div>";
                continue;
            }

            // Block quote
            if (preg_match('/^&gt;\s?(.*)$/', $trimmed, $m)) {
                $flushParagraph(); $closeLists();
                if (!$inQuote) { $out[] = "<blockquote>"; $inQuote = true; }
                $out[] = "<p>" . $this->inline($m[1]) . "</p>";
                continue;
            }

            // Lists
            if (preg_match('/^(\s*)([*+-]|[0-9]+\.)\s+(.*)$/', $line, $m)) {
                $flushParagraph();
                $closeQuote();
                $type = in_array($m[2], ["*", "+", "-"], true) ? "ul" : "ol";
                $depth = (int)floor(strlen(str_replace("\t", "    ", $m[1])) / 2);

                if ($listStack === []) {
                    $out[] = "<" . $type . "><li>";
                    $listStack[] = $type;
                } elseif ($depth >= count($listStack)) {
                    $out[] = "<" . $type . "><li>";
                    $listStack[] = $type;
                } else {
                    while (count($listStack) > $depth + 1) {
                        $out[] = "</li></" . array_pop($listStack) . ">";
                    }
                    $out[] = "</li><li>";
                }
                $out[] = $this->inline($m[3]);
                continue;
            }

            // Lazy continuation of a list item
            if ($listStack !== []) {
                $out[] = " " . $this->inline($trimmed);
                continue;
            }

            $paragraph[] = $trimmed;
        }

        $flushParagraph();
        $closeLists();
        $closeQuote();

        return implode("\n", $out);
    }

    private function tableCells(string $line): array {
        $line = trim($line, "| \t");
        return array_map("trim", explode("|", $line));
    }

    // -----------------------------------------------------------------------
    // Inline level
    // -----------------------------------------------------------------------

    private function inline(string $text): string {
        // Images: ![alt](src)
        $text = preg_replace_callback('/!\[([^\]]*)\]\(((?:[^()\s]|\([^()\s]*\))+)(?:\s+&quot;([^&]*)&quot;)?\)/', function ($m) {
            $src = $this->safeUrl($m[2]);
            if ($src === null) return $m[1];
            return '<img src="' . $src . '" alt="' . $m[1] . '" loading="lazy" class="img-fluid rounded">';
        }, $text) ?? $text;

        // Links: [text](href)
        $text = preg_replace_callback('/\[([^\]]+)\]\(((?:[^()\s]|\([^()\s]*\))+)(?:\s+&quot;([^&]*)&quot;)?\)/', function ($m) {
            $href = $this->safeUrl($m[2]);
            if ($href === null) return $m[1];
            $title = isset($m[3]) && $m[3] !== "" ? ' title="' . $m[3] . '"' : "";
            return '<a href="' . $href . '"' . $title . $this->linkRel($href) . '>' . $m[1] . '</a>';
        }, $text) ?? $text;

        // Bare URLs
        $text = preg_replace_callback('@(^|[\s(])(https?://[^\s<>"\')]+)@', function ($m) {
            $href = $this->safeUrl($m[2]);
            if ($href === null) return $m[0];
            $label = mb_strlen($m[2]) > 60 ? mb_substr($m[2], 0, 57) . "…" : $m[2];
            return $m[1] . '<a href="' . $href . '"' . $this->linkRel($href) . '>' . $label . '</a>';
        }, $text) ?? $text;

        // @mentions
        $text = preg_replace_callback('/(^|\s)@([A-Za-z0-9_.-]{2,64})/', function ($m) {
            return $m[1] . '<a href="' . htmlspecialchars(url("users?q=" . rawurlencode($m[2])), ENT_QUOTES, "UTF-8")
                 . '" class="mention">@' . $m[2] . '</a>';
        }, $text) ?? $text;

        $text = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $text) ?? $text;
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<![A-Za-z0-9])__(.+?)__(?![A-Za-z0-9])/s', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<![A-Za-z0-9_])_(?!\s)(.+?)(?<!\s)_(?![A-Za-z0-9_])/s', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $text) ?? $text;

        // Single newlines inside a paragraph become line breaks.
        return str_replace("\n", "<br>\n", $text);
    }

    /**
     * Validate a URL and return it HTML escaped, or null when the scheme is
     * not on the allow list (blocks javascript:, data:, vbscript: ...).
     */
    private function safeUrl(string $url): ?string {
        $url = html_entity_decode($url, ENT_QUOTES, "UTF-8");
        $url = trim($url);
        if ($url === "") return null;

        if (str_starts_with($url, "/") || str_starts_with($url, "#")) {
            return htmlspecialchars($url, ENT_QUOTES, "UTF-8");
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($scheme === "" || !in_array($scheme, self::ALLOWED_SCHEMES, true)) return null;
        if (preg_match('/[\x00-\x1f\x7f]/', $url)) return null;
        return htmlspecialchars($url, ENT_QUOTES, "UTF-8");
    }

    /** External links open in a new tab and never leak the referrer token. */
    private function linkRel(string $href): string {
        $host = parse_url(html_entity_decode($href), PHP_URL_HOST);
        if ($host === null || $host === false) return "";
        $ownHost = parse_url(Config::baseUrl(), PHP_URL_HOST);
        if ($host === $ownHost) return "";
        return ' rel="nofollow ugc noopener" target="_blank"';
    }
}
