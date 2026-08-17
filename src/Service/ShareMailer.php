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
 * Nachricht" wäre bequem und wäre die Lücke — ein Formular, das beliebigen Text an beliebige
 * Adressen schickt, ist ein Spam-Verteiler mit der Absenderadresse des Betriebs und dessen Ruf
 * als Pfand.
 *
 * Auch der Name des Warenkorbs geht nicht ungeprüft mit: Er stammt aus einem Eingabefeld und
 * wird auf eine Zeile gekürzt. Zeilenumbrüche in einem Betreff sind der klassische Weg, fremde
 * Kopfzeilen einzuschleusen.
 */
final class ShareMailer
{
    private const MAX_NAME_LENGTH = 80;

    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function send(string $recipient, string $shareUrl, ?string $cartName, SalesChannelContext $salesChannelContext): void
    {
        $name = $this->sanitizeName($cartName);

        $data = [
            'recipients' => [$recipient => $recipient],
            'senderName' => '{{ salesChannel.name }}',
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            'contentHtml' => $this->translator->trans('rcCartSharing.mail.contentHtml', [
                '%name%' => $name,
                '%url%' => $shareUrl,
            ]),
            'contentPlain' => $this->translator->trans('rcCartSharing.mail.contentPlain', [
                '%name%' => $name,
                '%url%' => $shareUrl,
            ]),
            'subject' => $this->translator->trans('rcCartSharing.mail.subject'),
        ];

        $this->mailService->send($data, $salesChannelContext->getContext());
    }

    private function sanitizeName(?string $cartName): string
    {
        if ($cartName === null || trim($cartName) === '') {
            return $this->translator->trans('rcCartSharing.mail.unnamedCart');
        }

        // Zeilenumbrüche raus, dann kürzen: erst danach ist sicher, dass der Wert einzeilig ist.
        $singleLine = preg_replace('/\s+/u', ' ', trim($cartName)) ?? '';

        return mb_substr($singleLine, 0, self::MAX_NAME_LENGTH);
    }
}
