<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Tests\Unit\Service;

use LogicException;
use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCartSharing\Service\ShareMailer;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Prüft, was den Shop verlässt: an wen, mit welchem Betreff und mit welchem Inhalt. Der frei
 * eingegebene Name des Warenkorbs gelangt nur als eine kurze Zeile hinein, und nie in den Text der
 * Vorlage selbst. Der Kern rendert den Inhalt als Twig; ein Name im Vorlagentext würde ausgeführt.
 */
final class ShareMailerTest extends TestCase
{
    public function testTheMessageCarriesRecipientSubjectAndLink(): void
    {
        $sent = [];
        $mailer = new ShareMailer($this->mailService($sent), $this->translator());

        $mailer->send('kunde@example.test', 'https://shop.test/rc-cart-sharing/load/abc', 'Geländer Hofeinfahrt', $this->salesChannelContext());

        self::assertSame(['kunde@example.test' => 'kunde@example.test'], $sent[0]['data']['recipients']);
        self::assertSame('rcCartSharing.mail.subject', $sent[0]['data']['subject']);
        self::assertSame('https://shop.test/rc-cart-sharing/load/abc', $sent[0]['templateData']['rcSharedCart']['url']);
        self::assertSame('Geländer Hofeinfahrt', $sent[0]['templateData']['rcSharedCart']['name']);
        self::assertStringContainsString('{{ rcSharedCart.name }}', $sent[0]['data']['contentPlain']);
        self::assertStringContainsString('{{ rcSharedCart.url }}', $sent[0]['data']['contentHtml']);
    }

    /**
     * Was: Ein Name mit Twig-Ausdruck und HTML.
     * Warum: Stünde er im Vorlagentext, führte der Kern `{{ … }}` aus und setzte das HTML ungefiltert
     *        in die Mail an einen fremden Empfänger.
     * Erwartet: Der Vorlagentext enthält nur den Platzhalter, der Name steht unverändert als Variable.
     */
    public function testANameNeverBecomesPartOfTheTemplate(): void
    {
        $sent = [];
        $mailer = new ShareMailer($this->mailService($sent), $this->translator());
        $name = '{{ salesChannel.name }} <a href="https://example.test">hier</a>';

        $mailer->send('kunde@example.test', 'https://shop.test/x', $name, $this->salesChannelContext());

        foreach (['contentHtml', 'contentPlain'] as $field) {
            self::assertStringNotContainsString('salesChannel.name', $sent[0]['data'][$field]);
            self::assertStringNotContainsString('<a href', $sent[0]['data'][$field]);
        }
        self::assertSame($name, $sent[0]['templateData']['rcSharedCart']['name']);
    }

    public function testAnUnnamedCartGetsAName(): void
    {
        $sent = [];
        $mailer = new ShareMailer($this->mailService($sent), $this->translator());

        $mailer->send('kunde@example.test', 'https://shop.test/x', null, $this->salesChannelContext());

        self::assertSame('rcCartSharing.mail.unnamedCart', $sent[0]['templateData']['rcSharedCart']['name']);
    }

    public function testLineBreaksInTheNameDoNotReachTheMessage(): void
    {
        // Der Name kommt aus einem Eingabefeld und steht im Inhalt, nicht im Betreff. Einzeilig
        // gemacht taugt er nicht dazu, einen eigenen Absatz in die Nachricht zu setzen.
        $sent = [];
        $mailer = new ShareMailer($this->mailService($sent), $this->translator());

        $mailer->send('kunde@example.test', 'https://shop.test/x', "Harmlos\r\nBcc: fremder@example.test", $this->salesChannelContext());

        self::assertSame('Harmlos Bcc: fremder@example.test', $sent[0]['templateData']['rcSharedCart']['name']);
    }

    public function testAnOverlongNameIsShortened(): void
    {
        $sent = [];
        $mailer = new ShareMailer($this->mailService($sent), $this->translator());

        $mailer->send('kunde@example.test', 'https://shop.test/x', str_repeat('a', 500), $this->salesChannelContext());

        // 80 ist ShareMailer::MAX_NAME_LENGTH; die Konstante ist privat, daher die Zahl.
        self::assertSame(str_repeat('a', 80), $sent[0]['templateData']['rcSharedCart']['name']);
    }

    /**
     * @param list<array{data: array<string, mixed>, templateData: array<string, mixed>}> $sent
     */
    private function mailService(array &$sent): AbstractMailService
    {
        return new class ($sent) extends AbstractMailService {
            /**
             * @param list<array{data: array<string, mixed>, templateData: array<string, mixed>}> $sent
             */
            public function __construct(private array &$sent)
            {
                // Gelesen wird über die Referenz im Test; die Zuweisung per Referenz hält die
                // Verbindung sichtbar.
                $this->sent = &$sent;
            }

            /**
             * @param array<string, mixed> $data
             * @param array<string, mixed> $templateData
             */
            public function send(array $data, Context $context, array $templateData = []): ?Email
            {
                $this->sent[] = ['data' => $data, 'templateData' => $templateData];

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
             * Gibt den Schlüssel und die eingesetzten Werte zurück. Der Test prüft, was in der
             * Nachricht landet, nicht wie die Übersetzung formuliert ist.
             *
             * @param array<mixed> $parameters
             */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $values = array_map(static fn (mixed $value): string => (string) $value, $parameters);

                return trim($id . ' ' . implode(' ', $values));
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
