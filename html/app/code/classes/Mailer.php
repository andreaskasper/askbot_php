<?php

/**
 * Mailer - queues and delivers mail.
 *
 * Web requests only ever call Mailer::queue() so a slow SMTP server can never
 * make a page hang. The "mailer" bot drains the queue:
 *
 *     php html/app/app.php bot -t mailer
 *
 * Delivery talks SMTP directly (with optional STARTTLS/SMTPS) and falls back
 * to PHP's mail() when no MAIL_HOST is configured.
 */
class Mailer {

    /** Put a message into the queue. Returns the queue id. */
    public static function queue(string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText = ""): int {
        if ($bodyText === "") $bodyText = trim(html_entity_decode(strip_tags(str_replace(["<br>", "</p>"], "\n", $bodyHtml)), ENT_QUOTES, "UTF-8"));
        $db = new SQL(0);
        return $db->Create("mail_queue", [
            "to_email"  => $toEmail,
            "to_name"   => $toName,
            "subject"   => $subject,
            "body_html" => $bodyHtml,
            "body_text" => $bodyText,
        ]);
    }

    /**
     * Render one of the templates in app/design/default/mail/ and queue it.
     *
     * @param string $template e.g. "verify_email"
     */
    public static function queueTemplate(string $toEmail, string $toName, string $subject, string $template, array $params = []): int {
        $params["subject"] = $subject;
        $params["to_name"] = $toName;
        $body = PageEngine::fetch("mail/" . $template, $params);
        if ($body === "") $body = "<p>" . htmlspecialchars($subject, ENT_QUOTES, "UTF-8") . "</p>";
        $html = PageEngine::fetch("mail/layout", ["subject" => $subject, "body" => $body]);
        return self::queue($toEmail, $toName, $subject, $html !== "" ? $html : $body);
    }

    /** Send up to $limit queued messages. Returns [sent, failed]. */
    public static function drain(int $limit = 25): array {
        $db = new SQL(0);
        $rows = $db->cmdrows('SELECT * FROM mail_queue WHERE status = "pending" AND attempts < 5 ORDER BY id ASC LIMIT ' . SQL::int($limit));
        $sent = 0; $failed = 0;
        foreach ($rows as $row) {
            try {
                self::send($row["to_email"], $row["to_name"], $row["subject"], $row["body_html"], $row["body_text"]);
                $db->Update("mail_queue", ["status" => "sent", "sent_at" => gmdate("Y-m-d H:i:s")], (int)$row["id"]);
                $sent++;
            } catch (\Throwable $e) {
                $attempts = (int)$row["attempts"] + 1;
                $db->Update("mail_queue", [
                    "attempts"   => $attempts,
                    "status"     => $attempts >= 5 ? "failed" : "pending",
                    "last_error" => mb_substr($e->getMessage(), 0, 500),
                ], (int)$row["id"]);
                $failed++;
            }
        }
        return [$sent, $failed];
    }

    /** Deliver immediately. Throws on failure. */
    public static function send(string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText = ""): bool {
        $fromEmail = (string)Config::env("MAIL_FROM", "noreply@localhost");
        $fromName  = (string)Config::env("MAIL_FROM_NAME", Config::get("site_title", "Askbot"));
        $host      = Config::env("MAIL_HOST");

        $boundary = "=_" . bin2hex(random_bytes(12));
        $headers = [
            "Date"         => gmdate("D, d M Y H:i:s") . " +0000",
            "From"         => self::address($fromEmail, $fromName),
            "To"           => self::address($toEmail, $toName),
            "Subject"      => self::encodeHeader($subject),
            "Message-ID"   => "<" . bin2hex(random_bytes(16)) . "@" . (parse_url(Config::baseUrl(), PHP_URL_HOST) ?: "localhost") . ">",
            "MIME-Version" => "1.0",
            "Content-Type" => 'multipart/alternative; boundary="' . $boundary . '"',
            "Auto-Submitted" => "auto-generated",
        ];

        $body  = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($bodyText !== "" ? $bodyText : strip_tags($bodyHtml)));
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($bodyHtml));
        $body .= "--" . $boundary . "--\r\n";

        if ($host === null || $host === "") {
            $raw = "";
            foreach ($headers as $k => $v) {
                if ($k === "To" || $k === "Subject") continue;
                $raw .= $k . ": " . $v . "\r\n";
            }
            if (!@mail($toEmail, self::encodeHeader($subject), $body, $raw)) {
                throw new \RuntimeException("mail() failed and no MAIL_HOST is configured");
            }
            return true;
        }

        return self::smtp($host, $fromEmail, $toEmail, $headers, $body);
    }

    private static function address(string $email, string $name): string {
        $name = trim($name);
        return $name === "" ? $email : self::encodeHeader($name) . " <" . $email . ">";
    }

    private static function encodeHeader(string $value): string {
        $value = str_replace(["\r", "\n"], "", $value);
        if (preg_match('/^[\x20-\x7e]*$/', $value)) return $value;
        return "=?UTF-8?B?" . base64_encode($value) . "?=";
    }

    /** Minimal but correct SMTP conversation. */
    private static function smtp(string $host, string $from, string $to, array $headers, string $body): bool {
        $port     = (int)Config::env("MAIL_PORT", 25);
        $user     = (string)Config::env("MAIL_USER", "");
        $password = (string)Config::env("MAIL_PASSWORD", "");
        $security = strtolower((string)Config::env("MAIL_SECURITY", ""));   // "", "tls", "ssl"
        $timeout  = 15;

        $target = ($security === "ssl" ? "ssl://" : "") . $host . ":" . $port;
        $socket = @stream_socket_client($target, $errno, $errstr, $timeout);
        if (!$socket) throw new \RuntimeException("SMTP connect failed: " . $errstr);
        stream_set_timeout($socket, $timeout);

        $read = function () use ($socket): string {
            $data = "";
            while (($line = fgets($socket, 1024)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === " ") break;
            }
            return $data;
        };
        $expect = function (string $response, string $code, string $step): void {
            if (!str_starts_with(trim($response), $code)) {
                throw new \RuntimeException("SMTP " . $step . " failed: " . trim($response));
            }
        };
        $write = function (string $command) use ($socket, $read): string {
            fwrite($socket, $command . "\r\n");
            return $read();
        };

        $expect($read(), "220", "greeting");
        $hostname = parse_url(Config::baseUrl(), PHP_URL_HOST) ?: "localhost";
        $response = $write("EHLO " . $hostname);
        $expect($response, "250", "EHLO");

        if ($security === "tls") {
            $expect($write("STARTTLS"), "220", "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException("STARTTLS negotiation failed");
            }
            $expect($write("EHLO " . $hostname), "250", "EHLO after STARTTLS");
        }

        if ($user !== "") {
            $expect($write("AUTH LOGIN"), "334", "AUTH");
            $expect($write(base64_encode($user)), "334", "AUTH user");
            $expect($write(base64_encode($password)), "235", "AUTH password");
        }

        $expect($write("MAIL FROM:<" . $from . ">"), "250", "MAIL FROM");
        $expect($write("RCPT TO:<" . $to . ">"), "250", "RCPT TO");
        $expect($write("DATA"), "354", "DATA");

        $message = "";
        foreach ($headers as $k => $v) $message .= $k . ": " . $v . "\r\n";
        $message .= "\r\n" . preg_replace('/^\./m', "..", $body);
        fwrite($socket, $message . "\r\n.\r\n");
        $expect($read(), "250", "message body");

        $write("QUIT");
        fclose($socket);
        return true;
    }
}
