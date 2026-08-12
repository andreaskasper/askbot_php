<?php

namespace bots;

/**
 * mailer - deliver everything waiting in the mail queue.
 *
 * Schedule: every minute.
 */
class mailer {

    public static function run(array $data = []): string {
        [$sent, $failed] = \Mailer::drain(50);
        return "sent " . $sent . ", failed " . $failed;
    }
}
