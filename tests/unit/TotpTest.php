<?php

use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase {

    /**
     * RFC 6238 test vectors for the secret "12345678901234567890".
     * @dataProvider vectors
     */
    public function testRfc6238(int $timestamp, string $expected): void {
        $this->assertSame($expected, Totp::code("GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ", $timestamp));
    }

    public static function vectors(): array {
        return [
            [59, "287082"],
            [1111111109, "081804"],
            [1234567890, "005924"],
            [2000000000, "279037"],
        ];
    }

    public function testVerifyAcceptsCurrentCodeAndRejectsOthers(): void {
        $secret = Totp::generateSecret();
        $this->assertTrue(Totp::verify($secret, Totp::code($secret)));
        $this->assertFalse(Totp::verify($secret, "000000"));
        $this->assertFalse(Totp::verify($secret, "abc"));
    }

    public function testProvisioningUri(): void {
        $uri = Totp::provisioningUri("ABC234", "user@example.com", "Askbot");
        $this->assertStringStartsWith("otpauth://totp/Askbot:user%40example.com", $uri);
        $this->assertStringContainsString("secret=ABC234", $uri);
    }
}
