<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Auth;

use Mt2Cms\Auth\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    public function testGenerateSecretIsBase32(): void
    {
        $secret = Totp::generateSecret();

        self::assertGreaterThan(16, strlen($secret));
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testProvisioningUriContainsIssuerAndAccount(): void
    {
        $uri = Totp::provisioningUri('JBSWY3DPEHPK3PXP', 'admin', 'Mt2 CMS Admin');

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        self::assertStringContainsString('issuer=Mt2', $uri);
    }

    public function testVerifyAcceptsValidCode(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $counter = (int) floor(time() / 30);
        $method = new \ReflectionMethod(Totp::class, 'codeForCounter');
        /** @var string $code */
        $code = $method->invoke(null, $secret, $counter);

        self::assertTrue(Totp::verify($secret, $code));
        self::assertFalse(Totp::verify($secret, '000000'));
    }

    public function testQrDataUriReturnsSvgImage(): void
    {
        $uri = Totp::provisioningUri('JBSWY3DPEHPK3PXP', 'admin', 'Mt2 CMS Admin');
        $dataUri = Totp::qrDataUri($uri);

        self::assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
    }

    public function testRecoveryCodesAreUniqueStrings(): void
    {
        $codes = Totp::generateRecoveryCodes(4);

        self::assertCount(4, $codes);
        self::assertCount(4, array_unique($codes));
    }
}
