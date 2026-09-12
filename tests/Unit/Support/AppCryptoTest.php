<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Support;

use Mt2Cms\Support\AppCrypto;
use PHPUnit\Framework\TestCase;

final class AppCryptoTest extends TestCase
{
    private string $previousKey;

    protected function setUp(): void
    {
        $this->previousKey = (string) ($_ENV['APP_KEY'] ?? '');
        $_ENV['APP_KEY'] = AppCrypto::generateKey();
        putenv('APP_KEY=' . $_ENV['APP_KEY']);
        \Mt2Cms\Support\Env::$instance = null;
        \Mt2Cms\Support\Env::load();
    }

    protected function tearDown(): void
    {
        if ($this->previousKey !== '') {
            $_ENV['APP_KEY'] = $this->previousKey;
            putenv('APP_KEY=' . $this->previousKey);
        } else {
            unset($_ENV['APP_KEY']);
            putenv('APP_KEY');
        }

        \Mt2Cms\Support\Env::$instance = null;
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plain = 'JBSWY3DPEHPK3PXP';
        $encrypted = AppCrypto::encrypt($plain);

        self::assertTrue(AppCrypto::isEncrypted($encrypted));
        self::assertSame($plain, AppCrypto::decrypt($encrypted));
    }

    public function testEncryptedTotpSecretExceedsLegacyVarchar64(): void
    {
        $encrypted = AppCrypto::encrypt(\Mt2Cms\Auth\Totp::generateSecret());

        self::assertGreaterThan(64, strlen($encrypted));
        self::assertLessThanOrEqual(255, strlen($encrypted));
    }

    public function testMigrationsStoreTotpSecretAsVarchar255(): void
    {
        foreach (glob(BASE_DIR . '/src/Setup/migrations/*.sql') ?: [] as $file) {
            $sql = (string) file_get_contents($file);

            self::assertDoesNotMatchRegularExpression(
                '/totp_secret\s+VARCHAR\(64\)/i',
                $sql,
                basename($file) . ' totp_secret must fit AppCrypto ciphertext',
            );
        }
    }

    public function testTamperedPayloadFails(): void
    {
        $encrypted = AppCrypto::encrypt('secret');
        $tampered = substr($encrypted, 0, -4) . 'XXXX';

        $this->expectException(\RuntimeException::class);
        AppCrypto::decrypt($tampered);
    }

    public function testPlaintextIsNotEncrypted(): void
    {
        self::assertFalse(AppCrypto::isEncrypted('JBSWY3DPEHPK3PXP'));
    }
}
