<?php

/**
 * Revision - edit history for questions, answers and tag wikis.
 */
class Revision {

    /** Store the state of a post. Call before applying an edit. */
    public static function save(string $postType, int $postId, int $revision, ?int $userId, string $bodyMd, string $title = "", string $tags = "", string $comment = ""): void {
        $db = new SQL(0);
        $db->CreateUpdate("post_revisions", [
            "post_type" => $postType,
            "post_id"   => $postId,
            "revision"  => $revision,
            "user_id"   => $userId,
            "title"     => mb_substr($title, 0, 300),
            "body_md"   => $bodyMd,
            "tags"      => mb_substr($tags, 0, 255),
            "comment"   => mb_substr($comment, 0, 255),
        ], ["user_id", "title", "body_md", "tags", "comment"]);
    }

    public static function all(string $postType, int $postId): array {
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT r.*, u.username, u.slug FROM post_revisions r LEFT JOIN users u ON u.id = r.user_id
             WHERE r.post_type = "{0}" AND r.post_id = "{1}" ORDER BY r.revision DESC',
            [$postType, $postId]
        );
    }

    public static function get(string $postType, int $postId, int $revision): array {
        $db = new SQL(0);
        return $db->cmdrow(
            'SELECT * FROM post_revisions WHERE post_type = "{0}" AND post_id = "{1}" AND revision = "{2}" LIMIT 0,1',
            [$postType, $postId, $revision]
        );
    }

    /**
     * Line based diff between two texts, rendered as HTML.
     * Small enough to keep in house, good enough for post sized documents.
     */
    public static function diffHtml(string $before, string $after): string {
        $a = explode("\n", str_replace("\r\n", "\n", $before));
        $b = explode("\n", str_replace("\r\n", "\n", $after));

        $matrix = [];
        for ($i = 0; $i <= count($a); $i++) $matrix[$i][0] = 0;
        for ($j = 0; $j <= count($b); $j++) $matrix[0][$j] = 0;
        for ($i = 1; $i <= count($a); $i++) {
            for ($j = 1; $j <= count($b); $j++) {
                $matrix[$i][$j] = ($a[$i - 1] === $b[$j - 1])
                    ? $matrix[$i - 1][$j - 1] + 1
                    : max($matrix[$i - 1][$j], $matrix[$i][$j - 1]);
            }
        }

        $lines = [];
        $i = count($a); $j = count($b);
        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) { $lines[] = [" ", $a[$i - 1]]; $i--; $j--; }
            elseif ($matrix[$i][$j - 1] >= $matrix[$i - 1][$j]) { $lines[] = ["+", $b[$j - 1]]; $j--; }
            else { $lines[] = ["-", $a[$i - 1]]; $i--; }
        }
        while ($i > 0) { $lines[] = ["-", $a[$i - 1]]; $i--; }
        while ($j > 0) { $lines[] = ["+", $b[$j - 1]]; $j--; }
        $lines = array_reverse($lines);

        $html = '<div class="revision-diff">';
        foreach ($lines as [$marker, $line]) {
            $class = $marker === "+" ? "diff-add" : ($marker === "-" ? "diff-del" : "diff-same");
            $html .= '<div class="' . $class . '">' . htmlspecialchars($marker . " " . $line, ENT_QUOTES, "UTF-8") . '</div>';
        }
        return $html . '</div>';
    }
}
