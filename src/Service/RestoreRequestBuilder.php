<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use Ruhrcoder\RcCartSharing\Struct\RestoreRequest;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Übersetzt den gespeicherten Abzug in das, was die Positions-Fabrik des Kerns erwartet.
 *
 * Reine Umformung: keine Datenbank, keine Dienste, kein Zustand. Was beim Lesen eines alten oder
 * beschädigten Abzugs schiefgehen kann, lässt sich damit ohne laufenden Shop prüfen; ob es die
 * Artikel noch gibt, entscheidet erst der {@see CartRestorer}.
 *
 * Die Kennung der Position wird durchgereicht statt neu erzeugt. Sie entsteht im Javascript aus
 * den Kundeneingaben und entscheidet, welche Positionen verschmelzen; nachgebaut in PHP wäre sie
 * die fehleranfälligste Stelle des Plugins. Die Fabrik verlangt für `id` nur einen String, also
 * genügt es, sie mitzunehmen.
 *
 * Eine Position, die sich nicht lesen lässt, fällt still weg, statt den ganzen Warenkorb
 * scheitern zu lassen.
 */
final class RestoreRequestBuilder
{
    /**
     * Positionsarten, die aus einem geteilten Warenkorb wieder entstehen dürfen.
     *
     * Rabatte, Gutscheine und Werbepositionen fehlen hier: Sie entstehen aus Regeln, die für den
     * Empfänger andere sein können. Wer sie mitnähme, verschenkte einen Rabatt an jemanden, der
     * ihn nicht hat, und der Kern entfernte ihn beim nächsten Rechnen wieder, was aussieht, als
     * sei etwas kaputt. Jede andere Art fällt ebenso weg, solange sie hier nicht steht.
     *
     * @var list<string>
     */
    private const RESTORABLE_TYPES = [
        LineItem::PRODUCT_LINE_ITEM_TYPE,
    ];

    /**
     * @param list<array<string, mixed>> $snapshot
     *
     * @return list<RestoreRequest>
     */
    public function build(array $snapshot): array
    {
        $requests = [];

        foreach ($snapshot as $item) {
            $request = $this->buildItem($item);
            if ($request !== null) {
                $requests[] = $request;
            }
        }

        return $requests;
    }

    /**
     * Die Artikelkennungen aller Positionen samt Kindern, ohne Doppelte. Die Vorprüfung im
     * {@see CartRestorer} fragt damit in einer Abfrage, welche Artikel es noch gibt.
     *
     * Nur Artikelpositionen: Bei einem Gutschein steht in `referencedId` der Code, und ein Code in
     * der Abfrage nach Kennungen bricht sie mit einer Ausnahme ab, statt ihn als fehlend zu melden.
     *
     * @param list<array<string, mixed>> $snapshot
     *
     * @return list<string>
     */
    public function collectProductIds(array $snapshot): array
    {
        $ids = [];

        foreach ($snapshot as $item) {
            $referencedId = $item['referencedId'] ?? null;
            if (($item['type'] ?? null) === LineItem::PRODUCT_LINE_ITEM_TYPE && is_string($referencedId) && Uuid::isValid($referencedId)) {
                $ids[] = $referencedId;
            }

            foreach ($this->collectProductIds($this->readChildren($item)) as $childId) {
                $ids[] = $childId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function buildItem(array $item): ?RestoreRequest
    {
        $type = $item['type'] ?? null;
        if (!is_string($type) || !in_array($type, self::RESTORABLE_TYPES, true)) {
            return null;
        }

        $id = $item['id'] ?? null;
        $referencedId = $item['referencedId'] ?? null;
        if (!is_string($id) || $id === '' || !is_string($referencedId) || $referencedId === '') {
            return null;
        }

        return new RestoreRequest(
            [
                'id' => $id,
                'type' => $type,
                'referencedId' => $referencedId,
                'quantity' => $this->readQuantity($item),
                'payload' => $this->readPayload($item),
            ],
            $this->readProductNumber($item),
            $this->buildChildren($item),
        );
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return list<RestoreRequest>
     */
    private function buildChildren(array $item): array
    {
        return $this->build($this->readChildren($item));
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return list<array<string, mixed>>
     */
    private function readChildren(array $item): array
    {
        $children = $item['children'] ?? [];
        if (!is_array($children)) {
            return [];
        }

        $readChildren = [];
        foreach ($children as $child) {
            if (is_array($child)) {
                /** @var array<string, mixed> $child */
                $readChildren[] = $child;
            }
        }

        return $readChildren;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function readQuantity(array $item): int
    {
        $quantity = $item['quantity'] ?? 1;
        if (!is_int($quantity) || $quantity < 1) {
            // Eine Menge unter eins ist keine Bestellung. Sie kann aus einem alten Abzug stammen
            // oder aus einer von Hand veränderten Zeile; in beiden Fällen ist eins die einzige
            // Lesart, die nicht rät.
            return 1;
        }

        return $quantity;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function readPayload(array $item): array
    {
        $payload = $item['payload'] ?? [];

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function readProductNumber(array $item): ?string
    {
        $productNumber = $item['productNumber'] ?? null;

        return is_string($productNumber) && $productNumber !== '' ? $productNumber : null;
    }
}
