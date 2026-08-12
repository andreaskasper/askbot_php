<?php

use PHPUnit\Framework\TestCase;

/**
 * The API surface is checked over HTTP by scripts/smoke_test.sh; here we only
 * verify the parts that do not need a web server.
 */
final class ApiTest extends TestCase {

    public function testNeedThrowsForMissingParameter(): void {
        $this->expectException(APIException::class);
        API::need([], "id", "int");
    }

    public function testNeedCastsTypes(): void {
        $this->assertSame(7, API::need(["id" => "7"], "id", "int"));
        $this->assertTrue(API::need(["flag" => "1"], "flag", "bool"));
        $this->assertSame(["a", "b"], API::need(["tags" => "a,b"], "tags", "array"));
    }

    public function testOptionalFallsBackToDefault(): void {
        $this->assertSame("activity", API::optional([], "sort", "activity"));
        $this->assertSame("votes", API::optional(["sort" => "votes"], "sort", "activity"));
    }

    public function testEndpointClassesExistForEveryFile(): void {
        foreach (glob($_ENV["basepath"] . "/code/classes/API/*.php") as $file) {
            $name = basename($file, ".php");
            require_once $file;
            $this->assertTrue(class_exists("API\\" . $name), "missing class for " . $name);
        }
    }
}
