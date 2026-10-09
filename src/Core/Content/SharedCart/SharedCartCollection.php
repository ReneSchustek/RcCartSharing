<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Core\Content\SharedCart;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * Typisierte Sammlung für {@see SharedCartEntity}, damit Aufrufer ohne Prüfung auf den Eintragstyp
 * an Kennung und Positionen kommen.
 *
 * @extends EntityCollection<SharedCartEntity>
 */
final class SharedCartCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getTokens(): array
    {
        return array_values($this->fmap(
            static fn (SharedCartEntity $sharedCart): string => $sharedCart->getToken()
        ));
    }

    protected function getExpectedClass(): string
    {
        return SharedCartEntity::class;
    }
}
