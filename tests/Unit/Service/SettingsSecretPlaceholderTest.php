<?php

declare(strict_types=1);

namespace Mt2Cms\Tests\Unit\Service;

use Mt2Cms\Service\SettingsService;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class SettingsSecretPlaceholderTest extends TestCase
{
    public function testPostedSecretTreatsBlankAndPlaceholderAsUnchanged(): void
    {
        self::assertNull(SettingsService::postedSecret(''));
        self::assertNull(SettingsService::postedSecret('   '));
        self::assertNull(SettingsService::postedSecret(SettingsService::SECRET_UI_PLACEHOLDER));
    }

    public function testPostedSecretKeepsARealSecret(): void
    {
        self::assertSame('live-secret', SettingsService::postedSecret('  live-secret  '));
    }

    public function testPaypalSecretInputShowsPlaceholderWhenSet(): void
    {
        $html = $this->renderPaypalForm(true);

        self::assertStringContainsString(
            'value="' . SettingsService::SECRET_UI_PLACEHOLDER . '"',
            $html,
        );
    }

    public function testPaypalSecretInputStaysEmptyWhenUnset(): void
    {
        $html = $this->renderPaypalForm(false);

        self::assertStringContainsString('name="paypal_client_secret"', $html);
        self::assertStringNotContainsString(
            'value="' . SettingsService::SECRET_UI_PLACEHOLDER . '"',
            $html,
        );
    }

    private function renderPaypalForm(bool $secretSet): string
    {
        $twig = new Environment(new FilesystemLoader(BASE_DIR . '/themes/admin/templates'), [
            'autoescape' => 'html',
        ]);
        $twig->addFunction(new TwigFunction('t', static fn (string $key): string => $key));

        return $twig->render('pages/payment-method-paypal.twig', [
            'formId' => 'admin-paypal-form-paypal',
            'csrf' => 'token',
            'paypalMode' => 'sandbox',
            'paypalSecretSet' => $secretSet,
            'secretPlaceholder' => SettingsService::SECRET_UI_PLACEHOLDER,
        ]);
    }
}
