<?php

/**
 * API - JSON interface at /api/<namespace>.<method>.<format>
 *
 *     GET  /api/question.list.json?tag=php&sort=votes
 *     POST /api/answer.create.json      (session cookie + CSRF token)
 *     GET  /api/user.me.json            (Authorization: Bearer <api key>)
 *
 * Envelope:
 *     { "result": ..., "err": {"id": 0, "msg": ""}, "runtime": 0.01,
 *       "timestamp": {"unix": ..., "iso8601": "..."} }
 *
 * Endpoint classes live in app/code/classes/API/, are written in lower case
 * and only ever return plain arrays - this class adds the envelope.
 */
class API {

    public const VERSION = "1.0";

    /** Methods that may be called without being signed in. */
    private const PUBLIC_METHODS = [
        "question.list", "question.get", "question.similar",
        "answer.list", "comment.list",
        "tag.list", "tag.suggest", "tag.get",
        "user.list", "user.get",
        "search.query", "search.suggest",
        "badge.list", "badge.get",
        "site.info", "site.stats",
    ];

    /**
     * Read only methods. Everything else counts as a write and therefore needs
     * POST plus a CSRF token - a new endpoint is protected by default.
     */
    private const READ_METHODS = [
        "list", "get", "me", "activity", "suggest", "similar", "info", "stats",
        "reasons", "queue", "statistics", "settings", "preview", "query", "revisions",
    ];

    private static ?User $_apiUser = null;

    public static function run(string $namespace, string $method, string $format = "json", array $data = []): void {
        $namespace = strtolower(preg_replace('/[^a-z0-9_]/i', "", $namespace) ?? "");
        $method    = strtolower(preg_replace('/[^a-z0-9_]/i', "", $method) ?? "");
        $format    = strtolower($format);

        header("Access-Control-Allow-Origin: " . Config::baseUrl());
        header("Vary: Origin");
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "OPTIONS") {
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization");
            http_response_code(204);
            exit;
        }

        try {
            // JSON request bodies are merged into $data for convenience.
            if (str_contains($_SERVER["CONTENT_TYPE"] ?? "", "application/json")) {
                $raw = file_get_contents("php://input");
                $json = json_decode((string)$raw, true);
                if (is_array($json)) $data = array_merge($data, $json);
            }

            self::authenticate();

            $class = "API\\" . $namespace;
            if (!class_exists($class)) throw new APIException("Unknown API namespace: " . $namespace, 404, 404);
            if (!method_exists($class, $method)) throw new APIException("Unknown API method: " . $namespace . "." . $method, 404, 404);

            $endpoint = $namespace . "." . $method;
            $isWrite = self::isWriteMethod($method);

            if ($isWrite) {
                if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
                    throw new APIException("This endpoint requires POST", 405, 405);
                }
                // API keys authenticate themselves, cookies need a CSRF token.
                if (self::$_apiUser === null && !Csrf::validate((string)($data["csrf_token"] ?? ""))) {
                    throw new APIException("Missing or invalid CSRF token", 403, 403);
                }
            }
            if (!in_array($endpoint, self::PUBLIC_METHODS, true) && !MyUser::isLoggedIn()) {
                throw new APIException("Please sign in", 401, 401);
            }
            if (!RateLimiter::check("api:" . (MyUser::id() ?: Firewall::ipHash()), $isWrite ? 120 : 600, 300)) {
                throw new APIException("Rate limit exceeded", 429, 429);
            }

            $result = call_user_func([$class, $method], $data);
            self::send(["result" => $result], $format);

        } catch (APIException $e) {
            self::sendError($e->getMessage(), $e->getCode(), $format, $e->httpCode);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            self::sendError($e->getMessage(), 400, $format, 400);
        } catch (\Throwable $e) {
            error_log("[askbot][api] " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
            $message = ($_ENV["STAGE"] ?? "") === "development" ? $e->getMessage() : "Internal server error";
            self::sendError($message, 500, $format, 500);
        }
    }

    private static function isWriteMethod(string $method): bool {
        return !in_array($method, self::READ_METHODS, true);
    }

    /** Bearer token or ?api_key= authenticates without a session. */
    private static function authenticate(): void {
        $token = "";
        $header = $_SERVER["HTTP_AUTHORIZATION"] ?? $_SERVER["REDIRECT_HTTP_AUTHORIZATION"] ?? "";
        if (preg_match('/^Bearer\s+(.+)$/i', (string)$header, $m)) $token = trim($m[1]);
        elseif (!empty($_GET["api_key"])) $token = (string)$_GET["api_key"];
        if ($token === "") return;

        $user = Auth::userByApiKey($token);
        if ($user === null) throw new APIException("Invalid API key", 401, 401);
        self::$_apiUser = $user;
        MyUser::login($user);
    }

    /** Throw from an endpoint: API::fail("Question not found", 404); */
    public static function fail(string $message, int $httpCode = 400, ?int $line = null): void {
        throw new APIException($message, $line ?? $httpCode, $httpCode);
    }

    /** Required parameter helper. */
    public static function need(array $data, string $key, string $type = "string") {
        if (!isset($data[$key]) || $data[$key] === "") {
            throw new APIException("Missing parameter: " . $key, 422, 422);
        }
        return match ($type) {
            "int"   => (int)$data[$key],
            "bool"  => in_array((string)$data[$key], ["1", "true", "yes", "on"], true),
            "array" => is_array($data[$key]) ? $data[$key] : explode(",", (string)$data[$key]),
            default => (string)$data[$key],
        };
    }

    public static function optional(array $data, string $key, $default = null, string $type = "string") {
        if (!isset($data[$key]) || $data[$key] === "") return $default;
        return self::need($data, $key, $type);
    }

    public static function send(array $payload, string $format = "json"): void {
        $payload["err"] = $payload["err"] ?? ["id" => 0, "msg" => ""];
        $payload["runtime"] = round(microtime(true) - ($_ENV["pgmstart"] ?? microtime(true)), 4);
        $payload["timestamp"] = ["unix" => time(), "iso8601" => gmdate("c")];

        switch ($format) {
            case "jsonac":
                header("Content-Type: application/json; charset=utf-8");
                echo json_encode($payload["result"] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
            case "successcode":
                header("Content-Type: text/plain; charset=utf-8");
                echo (int)$payload["err"]["id"];
                break;
            case "txt":
            case "plain":
                header("Content-Type: text/plain; charset=utf-8");
                print_r($payload["result"] ?? null);
                break;
            case "json":
            default:
                header("Content-Type: application/json; charset=utf-8");
                echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
                break;
        }
        exit;
    }

    public static function sendError(string $message, int $id = 400, string $format = "json", int $httpCode = 400): void {
        if (!headers_sent()) http_response_code($httpCode);
        self::send(["result" => null, "err" => ["id" => $id, "msg" => $message]], $format);
    }
}
