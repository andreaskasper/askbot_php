<?php

/**
 * APIException - anything an API method can fail with.
 */
class APIException extends \Exception {

    public int $httpCode;

    public function __construct(string $message, int $code = 400, int $httpCode = 400) {
        parent::__construct($message, $code);
        $this->httpCode = $httpCode;
    }
}
