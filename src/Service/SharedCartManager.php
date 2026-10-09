<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Die gespeicherten Warenkörbe eines Kunden: auflisten, umbenennen, löschen.
 *
 * Jede Handlung außer dem Auflisten prüft zuerst die Zugehörigkeit, und ein fremder Datensatz
 * verhält sich wie ein nicht vorhandener. Der Unterschied wäre eine Auskunft: „gehört Ihnen nicht" verrät, dass es ihn gibt,
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

    /**
     * Neueste zuerst und ohne Obergrenze: Ein Kunde speichert von Hand, die Liste bleibt kurz.
     * Gefiltert wird nur nach dem Kunden, also über alle Verkaufskanäle.
     */
    public function listForCustomer(string $customerId, Context $context): SharedCartCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        return $this->sharedCartRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @return bool true, wenn umbenannt wurde. false heißt: gibt es nicht oder gehört jemand
     *              anderem, nach außen derselbe Fall
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
     * Datensatz, der dem Kunden nicht gehört, kommt so gar nicht erst zurück und kann auch nicht
     * versehentlich weiterverwendet werden. Ohne eigene Zählart liefert getTotal() die Zahl der
     * gefundenen Kennungen, bei Grenze 1 also 0 oder 1.
     */
    private function belongsToCustomer(string $sharedCartId, string $customerId, Context $context): bool
    {
        // Die Kennung kommt aus einem Formular. Was keine gültige Kennung ist, gehört niemandem; in
        // der Abfrage selbst bräche es mit einer Ausnahme ab.
        if (!Uuid::isValid($sharedCartId) || $customerId === '') {
            return false;
        }

        $criteria = new Criteria([$sharedCartId]);
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        return $this->sharedCartRepository->searchIds($criteria, $context)->getTotal() === 1;
    }

    /**
     * Ein Warenkorb ohne Namen ist zulässig; die Liste zeigt dann „Warenkorb ohne Namen“ und das
     * Datum. Eine leere Eingabe soll ihn nicht in eine Zeile verwandeln, die aussieht, als fehle
     * etwas. 255 Zeichen sind die Breite der Spalte `name`; dieselbe Regel steht im
     * {@see SharedCartWriter}.
     */
    private function normalizeName(string $name): ?string
    {
        $trimmed = trim($name);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 255);
    }
}
