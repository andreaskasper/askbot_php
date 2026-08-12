<?php

/**
 * Upload - image uploads for the post editor and avatars.
 *
 * Only real images are accepted (checked with getimagesize, not by extension),
 * files are re-encoded to strip metadata and possible payloads, and they are
 * stored under html/uploads/YYYY/MM/ with a random name.
 */
class Upload {

    public const MAX_BYTES = 8 * 1024 * 1024;
    private const ALLOWED = [
        IMAGETYPE_JPEG => "jpg",
        IMAGETYPE_PNG  => "png",
        IMAGETYPE_GIF  => "gif",
        IMAGETYPE_WEBP => "webp",
    ];

    /**
     * @param array $file one entry of $_FILES
     * @return array{url:string,path:string,bytes:int}
     * @throws \RuntimeException
     */
    public static function image(array $file, ?int $userId = null): array {
        if (!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])) {
            throw new \RuntimeException("No file received");
        }
        if (($file["size"] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException("File is larger than " . (self::MAX_BYTES / 1024 / 1024) . " MB");
        }
        $info = @getimagesize($file["tmp_name"]);
        if ($info === false || !isset(self::ALLOWED[$info[2]])) {
            throw new \RuntimeException("Only JPEG, PNG, GIF and WebP images are allowed");
        }
        $extension = self::ALLOWED[$info[2]];

        $relative = "uploads/" . gmdate("Y/m");
        $directory = $_ENV["webroot"] . "/" . $relative;
        if (!is_dir($directory) && !@mkdir($directory, 0775, true)) {
            throw new \RuntimeException("Upload directory is not writable");
        }

        $name = bin2hex(random_bytes(12)) . "." . $extension;
        $target = $directory . "/" . $name;

        // Re-encode when GD is available: this removes EXIF and any appended payload.
        if (function_exists("imagecreatefromstring")) {
            $image = @imagecreatefromstring((string)file_get_contents($file["tmp_name"]));
            if ($image === false) throw new \RuntimeException("Image could not be decoded");
            $image = self::limitSize($image, 2000, 2000);
            match ($extension) {
                "jpg"  => imagejpeg($image, $target, 85),
                "png"  => imagepng($image, $target, 6),
                "gif"  => imagegif($image, $target),
                "webp" => imagewebp($image, $target, 85),
            };
            imagedestroy($image);
        } elseif (!move_uploaded_file($file["tmp_name"], $target)) {
            throw new \RuntimeException("File could not be stored");
        }
        @chmod($target, 0644);

        $bytes = (int)filesize($target);
        $db = new SQL(0);
        $db->Create("uploads", [
            "user_id"  => $userId,
            "filename" => mb_substr((string)($file["name"] ?? $name), 0, 255),
            "path"     => $relative . "/" . $name,
            "mime"     => (string)($info["mime"] ?? "image/" . $extension),
            "bytes"    => $bytes,
        ]);

        return ["url" => url($relative . "/" . $name), "path" => $relative . "/" . $name, "bytes" => $bytes];
    }

    private static function limitSize(\GdImage $image, int $maxWidth, int $maxHeight): \GdImage {
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w <= $maxWidth && $h <= $maxHeight) return $image;
        $ratio = min($maxWidth / $w, $maxHeight / $h);
        $resized = imagescale($image, (int)round($w * $ratio), (int)round($h * $ratio));
        if ($resized === false) return $image;
        imagedestroy($image);
        return $resized;
    }
}
