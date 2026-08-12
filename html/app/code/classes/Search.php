<?php

/**
 * Search - full text search over questions and answers.
 *
 * Supports the search operators people expect from a Q&A site:
 *
 *     tag:php user:12 answers:0 is:accepted score:5 "exact phrase" words
 */
class Search {

    public static function parse(string $query): array {
        $filter = ["q" => "", "tag" => "", "user" => 0, "scope" => "", "min_score" => null];
        $words = [];

        foreach (preg_split('/\s+/u', trim($query)) ?: [] as $token) {
            if ($token === "") continue;
            if (preg_match('/^tag:(.+)$/i', $token, $m))            { $filter["tag"] = Slug::tag($m[1]); continue; }
            if (preg_match('/^user:([0-9]+)$/i', $token, $m))       { $filter["user"] = (int)$m[1]; continue; }
            if (preg_match('/^answers:0$/i', $token))               { $filter["scope"] = "unanswered"; continue; }
            if (preg_match('/^is:(accepted|closed|bounty)$/i', $token, $m)) { $filter["scope"] = strtolower($m[1]); continue; }
            if (preg_match('/^score:(-?[0-9]+)$/i', $token, $m))    { $filter["min_score"] = (int)$m[1]; continue; }
            $words[] = $token;
        }
        $filter["q"] = trim(implode(" ", $words));
        return $filter;
    }

    /**
     * @return array{items:array,total:int,page:int,pages:int,answers:array}
     */
    public static function run(string $query, array $options = []): array {
        $filter = array_merge(self::parse($query), $options);
        $result = Question::search($filter);

        // Answers are searched separately so a hit in an answer still surfaces
        // its question.
        $result["answers"] = [];
        if (($filter["q"] ?? "") !== "" && ($options["page"] ?? 1) == 1) {
            $db = new SQL(0);
            $result["answers"] = $db->cmdrows(
                'SELECT a.id, a.question_id, a.score, q.title, q.slug FROM answers a
                 JOIN questions q ON q.id = a.question_id
                 WHERE a.deleted_at IS NULL AND q.deleted_at IS NULL
                   AND MATCH(a.body_md) AGAINST ("{0}" IN NATURAL LANGUAGE MODE)
                 ORDER BY a.score DESC LIMIT 5',
                [$filter["q"]]
            );
        }
        return $result;
    }

    /** Search suggestions for the header field. */
    public static function suggest(string $term, int $limit = 8): array {
        $term = trim($term);
        if (mb_strlen($term) < 2) return [];
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT id, title, slug, answer_count, score FROM questions
             WHERE deleted_at IS NULL AND title LIKE "%{0}%" ORDER BY score DESC, view_count DESC LIMIT ' . SQL::int($limit),
            [$term]
        );
    }
}
