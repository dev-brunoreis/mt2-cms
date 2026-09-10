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
        \Mt2Cms\Model\Env::$instance = null;
        \Mt2Cms\Model\Env::load();
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

        \Mt2Cms\Model\Env::$instance = null;
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plain = 'JBSWY3DPEHPK3PXP';
        $encrypted = AppCrypto::encrypt($plain);

        self::assertTrue(AppCrypto::isEncrypted($encrypted));
        self::assertSame($plain, AppCrypto::decrypt($encrypted));
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
