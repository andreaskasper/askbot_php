<?php

/**
 * SQLException - thrown by the SQL layer when a query fails.
 *
 * The message never contains user input, the offending statement is kept in
 * ->statement so it can be logged without ending up in an HTTP response.
 */
class SQLException extends \Exception {

    public string $statement = "";

    public function __construct(string $message, int $code = 600, string $statement = "") {
        parent::__construct($message, $code);
        $this->statement = $statement;
    }
}
