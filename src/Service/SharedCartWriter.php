<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use DateTimeImmutable;
use DateTimeZone;
use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartCollection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Legt den Abzug eines Warenkorbs ab und gibt die Kennung zurück, unter der er wiederzufinden ist.
 *
 * Der Dienst schreibt, rechnet aber nicht: Der Abzug kommt vom {@see CartSnapshotBuilder}, die
 * Kennung vom {@see ShareTokenGenerator}, das Ablaufdatum vom {@see ExpiryCalculator}. Was hier
 * bleibt, ist das Zusammensetzen, und es ist die einzige Stelle, an der ein gespeicherter
 * Warenkorb entsteht.
 */
final class SharedCartWriter
{
    /** Das Feld `expiryDays` aus `config.xml`, je Verkaufskanal einstellbar. */
    public const CONFIG_EXPIRY_DAYS = 'RcCartSharing.config.expiryDays';

    /**
     * @param EntityRepository<SharedCartCollection> $sharedCartRepository
     */
    public function __construct(
        private readonly EntityRepository $sharedCartRepository,
        private readonly CartSnapshotBuilder $snapshotBuilder,
        private readonly ShareTokenGenerator $tokenGenerator,
        private readonly ExpiryCalculator $expiryCalculator,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * Speichert den Warenkorb und gibt die Kennung zurück.
     *
     * Der Kundenbezug wird nur gesetzt, wenn jemand angemeldet ist. Ein Warenkorb, der ohne Konto
     * gespeichert wird, hängt damit an keinem Kundenkonto und erscheint in keiner Liste. Er ist
     * nur über seine Kennung zu finden.
     *
     * Die Kennung wird nicht auf Eindeutigkeit geprüft; bei 160 Bit ist eine Wiederholung
     * ausgeschlossen, und der eindeutige Schlüssel der Spalte wiese sie ab.
     */
    public function save(Cart $cart, SalesChannelContext $salesChannelContext, ?string $name = null): string
    {
        $token = $this->tokenGenerator->generate();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $expiryDays = $this->expiryCalculator->readExpiryDays(
            $this->systemConfigService->get(self::CONFIG_EXPIRY_DAYS, $salesChannelId)
        );
        $expiresAt = $this->expiryCalculator->expiresAt(new DateTimeImmutable('now', new DateTimeZone('UTC')), $expiryDays);

        $this->sharedCartRepository->create([[
            'id' => Uuid::randomHex(),
            'token' => $token,
            'name' => $this->normalizeName($name),
            'salesChannelId' => $salesChannelId,
            'customerId' => $salesChannelContext->getCustomerId(),
            'lineItems' => $this->snapshotBuilder->build($cart),
            'expiresAt' => $expiresAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $salesChannelContext->getContext());

        return $token;
    }

    /**
     * Der Name ist frei eingegeben und erscheint später in der Liste im Kundenkonto. Leerraum am
     * Rand und eine leere Eingabe werden zu „kein Name“, sonst steht dort eine Zeile, die aussieht,
     * als fehle etwas. 255 Zeichen sind die Breite der Spalte `name`.
     */
    private function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $trimmed = trim($name);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 255);
    }
}
