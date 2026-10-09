<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Verschickt die Adresse eines gespeicherten Warenkorbs.
 *
 * Die Nachricht steht fest: Name des Warenkorbs und Adresse, sonst nichts. Ein Feld „Ihre
 * Nachricht" wäre bequem und wäre die Lücke. Ein Formular, das beliebigen Text an beliebige
 * Adressen schickt, ist ein Spam-Verteiler mit der Absenderadresse des Betriebs und dessen Ruf
 * als Pfand.
 *
 * Der Name des Warenkorbs ist der einzige freie Text, der mitgeht. Er steht nur im Inhalt, nicht
 * im Betreff, und wird auf eine kurze Zeile gebracht, damit er kein Ersatz für das fehlende
 * Nachrichtenfeld wird.
 *
 * Der MailService rendert Betreff, Absendername und Inhalt als Twig-Vorlage. Daher steht beim
 * Absender `{{ salesChannel.name }}`, das erst dort aufgelöst wird. Aus demselben Grund stehen Name
 * und Adresse nicht im Text, sondern kommen als Variablen dazu: Ein Name wie `{{ … }}` würde sonst
 * als Vorlage ausgeführt, und HTML darin landete ungefiltert in der Mail. Als Variable maskiert der
 * Kern den HTML-Teil selbst und gibt den Klartext so aus, wie er ist.
 */
final class ShareMailer
{
    /** Genug für „Geländer Hofeinfahrt, zweite Variante“, zu wenig für einen Werbetext mit Verweis. */
    private const MAX_NAME_LENGTH = 80;

    private const TEMPLATE_VARIABLE = 'rcSharedCart';

    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function send(string $recipient, string $shareUrl, ?string $cartName, SalesChannelContext $salesChannelContext): void
    {
        $placeholders = [
            '%name%' => '{{ ' . self::TEMPLATE_VARIABLE . '.name }}',
            '%url%' => '{{ ' . self::TEMPLATE_VARIABLE . '.url }}',
        ];

        $data = [
            'recipients' => [$recipient => $recipient],
            'senderName' => '{{ salesChannel.name }}',
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            'contentHtml' => $this->translator->trans('rcCartSharing.mail.contentHtml', $placeholders),
            'contentPlain' => $this->translator->trans('rcCartSharing.mail.contentPlain', $placeholders),
            'subject' => $this->translator->trans('rcCartSharing.mail.subject'),
        ];

        $templateData = [
            self::TEMPLATE_VARIABLE => [
                'name' => $this->sanitizeName($cartName),
                'url' => $shareUrl,
            ],
        ];

        $this->mailService->send($data, $salesChannelContext->getContext(), $templateData);
    }

    private function sanitizeName(?string $cartName): string
    {
        if ($cartName === null || trim($cartName) === '') {
            return $this->translator->trans('rcCartSharing.mail.unnamedCart');
        }

        // Erst jeden Leerraum samt Zeilenumbruch zu einem Leerzeichen, dann kürzen. Umgekehrt
        // zählte ein Block aus Umbrüchen gegen die Länge, bevor er verschwindet.
        $singleLine = preg_replace('/\s+/u', ' ', trim($cartName)) ?? '';

        return mb_substr($singleLine, 0, self::MAX_NAME_LENGTH);
    }
}
