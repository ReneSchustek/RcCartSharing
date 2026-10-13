<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use DateTimeImmutable;
use DateTimeZone;
use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartCollection;
use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Holt den gespeicherten Warenkorb zu einer Kennung.
 *
 * Ein abgelaufener Warenkorb wird behandelt, als gäbe es die Kennung nicht. Der Unterschied wäre
 * eine Auskunft: „abgelaufen" verrät, dass diese Kennung einmal gültig war, und damit, dass
 * Raten grundsätzlich zum Ziel führt. Für den Kunden ändert sich nichts, denn beide Fälle enden
 * an derselben Stelle.
 *
 * Gesucht wird über die Kennung und den Verkaufskanal, in dem der Warenkorb gespeichert wurde. In
 * einem anderen Kanal verhält sich ein Verweis wie eine unbekannte Kennung, denn Preise,
 * Sichtbarkeit und Sprache hängen am Kanal.
 */
final class SharedCartLoader
{
    /**
     * @param EntityRepository<SharedCartCollection> $sharedCartRepository
     */
    public function __construct(
        private readonly EntityRepository $sharedCartRepository,
    ) {
    }

    /**
     * @return SharedCartEntity|null null für eine leere, unbekannte oder abgelaufene Kennung
     */
    public function loadByToken(string $token, SalesChannelContext $salesChannelContext): ?SharedCartEntity
    {
        if ($token === '') {
            return null;
        }

        // Ein Warenkorb gilt in dem Verkaufskanal, in dem er gespeichert wurde. Preise, Sichtbarkeit
        // und Sprache hängen am Kanal; in einem anderen wäre der Verweis ein anderer Warenkorb.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('token', $token));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannelId()));
        $criteria->setLimit(1);

        $sharedCart = $this->sharedCartRepository->search($criteria, $salesChannelContext->getContext())->getEntities()->first();

        if (!$sharedCart instanceof SharedCartEntity) {
            return null;
        }

        return $this->isExpired($sharedCart) ? null : $sharedCart;
    }

    /**
     * Die Aufräum-Aufgabe läuft täglich. Ein abgelaufener Warenkorb steht also bis zu ihrem
     * nächsten Lauf in der Tabelle, bei stehender Warteschlange auch länger. In dieser Zeit muss die
     * Prüfung hier greifen, sonst wäre das Ablaufdatum eine Empfehlung.
     */
    private function isExpired(SharedCartEntity $sharedCart): bool
    {
        $expiresAt = $sharedCart->getExpiresAt();

        return $expiresAt !== null && $expiresAt < new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
