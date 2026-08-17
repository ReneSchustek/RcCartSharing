<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Tests\Unit\Service;

use LogicException;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCartSharing\Service\ShareMailer;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Geprüft wird, was den Shop verlässt: an wen, mit welchem Betreff, mit welchem Inhalt — und dass
 * aus dem frei eingegebenen Namen des Warenkorbs nichts in die Kopfzeilen der Nachricht gerät.
 */
final class ShareMailerTest extends TestCase
{
    public function testTheMessageCarriesRecipientSubjectAndLink(): void
    {
        $versandt = [];
        $mailer = new ShareMailer($this->mailService($versandt), $this->translator());

        $mailer->send('kunde@example.test', 'https://shop.test/rc-cart-sharing/load/abc', 'Geländer Hofeinfahrt', $this->salesChannelContext());

        self::assertSame(['kunde@example.test' => 'kunde@example.test'], $versandt[0]['recipients']);
        self::assertSame('rcCartSharing.mail.subject', $versandt[0]['subject']);
        self::assertStringContainsString('https://shop.test/rc-cart-sharing/load/abc', $versandt[0]['contentPlain']);
        self::assertStringContainsString('Geländer Hofeinfahrt', $versandt[0]['contentPlain']);
    }

    public function testAnUnnamedCartGetsAName(): void
    {
        $versandt = [];
        $mailer = new ShareMailer($this->mailService($versandt), $this->translator());

        $mailer->send('kunde@example.test', 'https://shop.test/x', null, $this->salesChannelContext());

        self::assertStringContainsString('rcCartSharing.mail.unnamedCart', $versandt[0]['contentPlain']);
    }

    public function testLineBreaksInTheNameDoNotReachTheMessage(): void
    {
        // Zeilenumbrüche in einem Betreff sind der klassische Weg, fremde Kopfzeilen
        // einzuschleusen. Der Name kommt aus einem Eingabefeld — also einzeilig machen.
        $versandt = [];
        $mailer = new ShareMailer($this->mailService($versandt), $this->translator());

        $mailer->send('kunde@example.test', 'https://shop.test/x', "Harmlos\r\nBcc: fremder@example.test", $this->salesChannelContext());

        self::assertStringNotContainsString("\r", $versandt[0]['contentPlain']);
        self::assertStringNotContainsString("\n", $versandt[0]['contentPlain']);
        self::assertStringContainsString('Harmlos Bcc: fremder@example.test', $versandt[0]['contentPlain']);
    }

    public function testAnOverlongNameIsShortened(): void
    {
        $versandt = [];
        $mailer = new ShareMailer($this->mailService($versandt), $this->translator());

        $mailer->send('kunde@example.test', 'https://shop.test/x', str_repeat('a', 500), $this->salesChannelContext());

        self::assertStringContainsString(str_repeat('a', 80), $versandt[0]['contentPlain']);
        self::assertStringNotContainsString(str_repeat('a', 81), $versandt[0]['contentPlain']);
    }

    /**
     * @param array<int, array<string, mixed>> $versandt
     */
    private function mailService(array &$versandt): AbstractMailService
    {
        return new class ($versandt) extends AbstractMailService {
            /**
             * @param array<int, array<string, mixed>> $versandt
             */
            public function __construct(private array &$versandt)
            {
                // Die Eigenschaft wird nur geschrieben — gelesen wird sie über die Referenz im
                // Test. Genau das ist ihr Zweck.
                $this->versandt = &$versandt;
            }

            /**
             * @param array<string, mixed> $data
             * @param array<string, mixed> $templateData
             */
            public function send(array $data, Context $context, array $templateData = []): ?\Symfony\Component\Mime\Email
            {
                $this->versandt[] = $data;

                return null;
            }

            public function getDecorated(): AbstractMailService
            {
                throw new LogicException('Wird im Test nicht gebraucht.');
            }
        };
    }

    private function translator(): TranslatorInterface
    {
        return new class () implements TranslatorInterface {
            /**
             * Gibt den Schlüssel und die eingesetzten Werte zurück. Der Test prüft, **was** in
             * der Nachricht landet, nicht wie die Übersetzung formuliert ist — die steht in den
             * Textbausteinen und ändert sich, ohne dass dieser Test rot werden soll.
             *
             * @param array<mixed> $parameters
             */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $werte = array_map(static fn (mixed $value): string => (string) $value, $parameters);

                return trim($id . ' ' . implode(' ', $werte));
            }

            public function getLocale(): string
            {
                return 'de-DE';
            }
        };
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('verkaufskanal');
        $context->method('getContext')->willReturn(Context::createCLIContext());

        return $context;
    }
}
