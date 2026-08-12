<?php

/**
 * Tag - topic labels with their own wiki page and synonyms.
 */
class Tag {

    /** Clean a list of raw tag inputs and resolve synonyms. */
    public static function normalizeList(array $tags): array {
        $out = [];
        foreach ($tags as $tag) {
            foreach (preg_split('/[,\s]+/u', (string)$tag) ?: [] as $part) {
                $name = Slug::tag($part);
                if ($name === "") continue;
                $name = self::resolveSynonym($name);
                if (!in_array($name, $out, true)) $out[] = $name;
            }
        }
        return array_slice($out, 0, Config::int("max_tags_per_question", 5));
    }

    public static function resolveSynonym(string $name): string {
        if ($name === "") return "";
        $db = new SQL(0);
        $target = $db->cmdvalue(
            'SELECT t.name FROM tag_synonyms s JOIN tags t ON t.id = s.target_tag_id WHERE s.source_name = "{0}" LIMIT 0,1',
            [$name]
        );
        if ($target === null) return $name;
        $db->cmd('UPDATE tag_synonyms SET usage_count = usage_count + 1 WHERE source_name = "{0}"', [$name]);
        return (string)$target;
    }

    public static function id(string $name, bool $create = true): int {
        $name = Slug::tag($name);
        if ($name === "") return 0;
        $db = new SQL(0);
        $id = $db->cmdvalue('SELECT id FROM tags WHERE name = "{0}" LIMIT 0,1', [$name]);
        if ($id !== null) return (int)$id;
        if (!$create) return 0;
        return $db->Create("tags", [
            "name"       => $name,
            "slug"       => Slug::make($name, 64),
            "created_by" => MyUser::id() ?: null,
        ], true);
    }

    /** Make the tags of a question match the given list exactly. */
    public static function sync(int $questionId, array $names): void {
        $db = new SQL(0);
        $current = $db->cmdrows('SELECT tag_id FROM question_tags WHERE question_id = "{0}"', [$questionId]);
        $currentIds = array_map(fn($r) => (int)$r["tag_id"], $current);

        $wantedIds = [];
        foreach ($names as $name) {
            $id = self::id($name);
            if ($id > 0) $wantedIds[] = $id;
        }

        foreach (array_diff($wantedIds, $currentIds) as $id) {
            $db->cmd('INSERT IGNORE INTO question_tags (question_id, tag_id) VALUES ("{0}", "{1}")', [$questionId, $id]);
        }
        foreach (array_diff($currentIds, $wantedIds) as $id) {
            $db->cmd('DELETE FROM question_tags WHERE question_id = "{0}" AND tag_id = "{1}"', [$questionId, $id]);
        }
        foreach (array_unique(array_merge($wantedIds, $currentIds)) as $id) {
            self::recount($id);
        }
    }

    public static function recount(int $tagId): void {
        $db = new SQL(0);
        $db->cmd(
            'UPDATE tags SET question_count = (
                SELECT COUNT(*) FROM question_tags qt JOIN questions q ON q.id = qt.question_id
                WHERE qt.tag_id = "{0}" AND q.deleted_at IS NULL
             ) WHERE id = "{0}"',
            [$tagId]
        );
    }

    public static function byName(string $name): array {
        $db = new SQL(0);
        return $db->cmdrow('SELECT * FROM tags WHERE name = "{0}" LIMIT 0,1', [Slug::tag($name)]);
    }

    /**
     * @param string $sort popular|name|newest
     */
    public static function all(string $sort = "popular", string $query = "", int $page = 1, int $perPage = 36): array {
        $db = new SQL(0);
        $offset = max(0, ($page - 1) * $perPage);
        $where = "1=1";
        $values = [];
        if ($query !== "") {
            $where = 'name LIKE "%{0}%"';
            $values[] = Slug::tag($query);
        }
        $order = match ($sort) {
            "name"   => "name ASC",
            "newest" => "id DESC",
            default  => "question_count DESC, name ASC",
        };
        $total = $db->cmdint('SELECT COUNT(*) FROM tags WHERE ' . $where, $values);
        $rows = $db->cmdrows(
            'SELECT * FROM tags WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . SQL::int($offset) . ',' . SQL::int($perPage),
            $values
        );
        return ["items" => $rows, "total" => $total, "page" => $page, "pages" => (int)ceil($total / $perPage)];
    }

    /** Tag cloud for the sidebar. */
    public static function popular(int $limit = 30): array {
        return WebCache::remember("tags:popular:" . $limit, 600, function () use ($limit) {
            $db = new SQL(0);
            return $db->cmdrows('SELECT name, question_count FROM tags WHERE question_count > 0 ORDER BY question_count DESC LIMIT ' . SQL::int($limit));
        });
    }

    /** Autocomplete for the ask form. */
    public static function suggest(string $term, int $limit = 10): array {
        $term = Slug::tag($term);
        if ($term === "") return self::popular($limit);
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT name, question_count FROM tags WHERE name LIKE "{0}%" ORDER BY question_count DESC LIMIT ' . SQL::int($limit),
            [$term]
        );
    }

    public static function saveWiki(string $name, string $descriptionMd, int $userId): void {
        $tagId = self::id($name);
        if ($tagId === 0) throw new \InvalidArgumentException(__("Unknown tag."));
        $db = new SQL(0);
        $row = $db->cmdrow('SELECT revision FROM tags WHERE id = "{0}"', [$tagId]);
        $revision = (int)($row["revision"] ?? 1) + 1;
        $db->Update("tags", [
            "description_md"   => $descriptionMd,
            "description_html" => Markdown::render($descriptionMd),
            "revision"         => $revision,
        ], $tagId);
        Revision::save("tag", $tagId, $revision, $userId, $descriptionMd, $name);
        Badge::awardKey($userId, "organizer");
        Audit::log("tag.wiki", "tag:" . $name, [], $userId);
    }

    public static function addSynonym(string $source, string $target, int $userId): void {
        $source = Slug::tag($source);
        $targetId = self::id($target, false);
        if ($source === "" || $targetId === 0) throw new \InvalidArgumentException(__("Unknown tag."));
        if ($source === Slug::tag($target)) throw new \InvalidArgumentException(__("A tag cannot be a synonym of itself."));
        $db = new SQL(0);
        $db->CreateUpdate("tag_synonyms", [
            "source_name"   => $source,
            "target_tag_id" => $targetId,
            "created_by"    => $userId,
        ], ["target_tag_id"]);
        Audit::log("tag.synonym", $source . "=>" . $target, [], $userId);
    }

    public static function synonyms(string $name): array {
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT s.*, t.name AS target_name FROM tag_synonyms s JOIN tags t ON t.id = s.target_tag_id WHERE t.name = "{0}" ORDER BY s.source_name',
            [Slug::tag($name)]
        );
    }

    /** Stable colour class so a tag always looks the same. */
    public static function colorClass(string $name): string {
        $hash = crc32($name);
        return "tag-c" . ($hash % 8);
    }
}
