<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use DateTimeImmutable;
use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartCollection;
use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Holt den gespeicherten Warenkorb zu einer Kennung.
 *
 * Ein abgelaufener Warenkorb wird behandelt, als gäbe es die Kennung nicht. Der Unterschied wäre
 * eine Auskunft: „abgelaufen" verrät, dass diese Kennung einmal gültig war — und damit, dass
 * Raten grundsätzlich zum Ziel führt. Für den Kunden ändert sich nichts, denn beide Fälle enden
 * an derselben Stelle.
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

    public function loadByToken(string $token, Context $context): ?SharedCartEntity
    {
        if ($token === '') {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('token', $token));
        $criteria->setLimit(1);

        $sharedCart = $this->sharedCartRepository->search($criteria, $context)->getEntities()->first();

        if (!$sharedCart instanceof SharedCartEntity) {
            return null;
        }

        return $this->isExpired($sharedCart) ? null : $sharedCart;
    }

    /**
     * Die Aufräum-Aufgabe läuft täglich; zwischen Ablauf und Löschung liegen also bis zu vierund-
     * zwanzig Stunden. In dieser Zeit muss die Prüfung hier greifen, sonst wäre das Ablaufdatum
     * eine Empfehlung.
     */
    private function isExpired(SharedCartEntity $sharedCart): bool
    {
        $expiresAt = $sharedCart->getExpiresAt();

        return $expiresAt !== null && $expiresAt < new DateTimeImmutable();
    }
}
