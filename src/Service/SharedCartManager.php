<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartCollection;
use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * Die gespeicherten Warenkörbe eines Kunden: auflisten, umbenennen, löschen.
 *
 * Jede Handlung prüft die Zugehörigkeit, und ein fremder Datensatz verhält sich wie ein nicht
 * vorhandener. Der Unterschied wäre eine Auskunft: „gehört Ihnen nicht" verrät, dass es ihn gibt,
 * und macht aus einer geratenen Kennung eine Information.
 */
final class SharedCartManager
{
    /**
     * @param EntityRepository<SharedCartCollection> $sharedCartRepository
     */
    public function __construct(
        private readonly EntityRepository $sharedCartRepository,
    ) {
    }

    public function listForCustomer(string $customerId, Context $context): SharedCartCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        return $this->sharedCartRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @return bool true, wenn umbenannt wurde. false heißt: gibt es nicht oder gehört jemand
     *              anderem — nach außen derselbe Fall
     */
    public function rename(string $sharedCartId, string $customerId, string $name, Context $context): bool
    {
        if (!$this->belongsToCustomer($sharedCartId, $customerId, $context)) {
            return false;
        }

        $this->sharedCartRepository->update([[
            'id' => $sharedCartId,
            'name' => $this->normalizeName($name),
        ]], $context);

        return true;
    }

    /**
     * @return bool true, wenn gelöscht wurde. false heißt: gibt es nicht oder gehört jemand
     *              anderem
     */
    public function delete(string $sharedCartId, string $customerId, Context $context): bool
    {
        if (!$this->belongsToCustomer($sharedCartId, $customerId, $context)) {
            return false;
        }

        $this->sharedCartRepository->delete([['id' => $sharedCartId]], $context);

        return true;
    }

    /**
     * Gesucht wird über beide Angaben zugleich, nicht erst geladen und dann verglichen. Ein
     * Datensatz, der dem Kunden nicht gehört, kommt so gar nicht erst zurück — und kann auch
     * nicht versehentlich weiterverwendet werden.
     */
    private function belongsToCustomer(string $sharedCartId, string $customerId, Context $context): bool
    {
        if ($sharedCartId === '' || $customerId === '') {
            return false;
        }

        $criteria = new Criteria([$sharedCartId]);
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        return $this->sharedCartRepository->searchIds($criteria, $context)->getTotal() === 1;
    }

    /**
     * Ein Warenkorb ohne Namen ist zulässig — er heißt in der Liste dann nach seinem Datum. Eine
     * leere Eingabe soll ihn nicht in eine Zeile verwandeln, die aussieht, als fehle etwas.
     */
    private function normalizeName(string $name): ?string
    {
        $trimmed = trim($name);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 255);
    }

    public function loadOwned(string $sharedCartId, string $customerId, Context $context): ?SharedCartEntity
    {
        if (!$this->belongsToCustomer($sharedCartId, $customerId, $context)) {
            return null;
        }

        $sharedCart = $this->sharedCartRepository->search(new Criteria([$sharedCartId]), $context)
            ->getEntities()
            ->first();

        return $sharedCart instanceof SharedCartEntity ? $sharedCart : null;
    }
}
