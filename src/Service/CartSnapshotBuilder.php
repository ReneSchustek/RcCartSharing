<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * Macht aus einem Warenkorb den Abzug, der gespeichert und geteilt wird.
 *
 * Der Abzug ist schmal: Kennung, Art, Verweiskennung, Artikelnummer, Menge, Nutzlast und Kinder
 * der Position. Was sich beim Wiederherstellen ohnehin neu ergibt, also Preise, Bestand und
 * Anzeigetexte, bleibt draußen. Ein gespeicherter Preis wäre eine Zusage, die niemand einlösen
 * will, sobald sie eine Woche alt ist.
 *
 * Die Nutzlast wird dagegen vollständig übernommen, abzüglich der Sperrliste. Das ist die
 * tragende Entscheidung dieses Plugins: An unseren Positionen hängen Meterlänge, Farbe,
 * Kundeneingaben und Aufteilung, verteilt auf fünf Erweiterungen. Eine Positivliste müsste jede
 * davon kennen, und eine neue Erweiterung fiele still heraus. Ein geteilter Warenkorb, der eine
 * Kundeneingabe verliert, ist aber genau der Schaden, den das Plugin verhindern soll. Unbekannte
 * Schlüssel überleben deshalb im Zweifel.
 */
final class CartSnapshotBuilder
{
    /**
     * Schlüssel, die Shopware beim Aufbau des Warenkorbs selbst wieder setzt.
     *
     * Sie mitzuspeichern wäre nicht falsch, aber nutzlos: Der ProductCartProcessor schreibt sie
     * beim Anreichern der Position neu, `features` kommt aus dem ProductFeatureBuilder. Sie blähen
     * den Datensatz auf und lassen beim Lesen der Tabelle offen, welcher Wert nun gilt, der
     * gespeicherte oder der gerechnete. Kommt im Kern ein Schlüssel hinzu, wird er nur unnötig
     * mitgespeichert; verloren geht dabei nichts.
     *
     * @var list<string>
     */
    private const VOLATILE_PAYLOAD_KEYS = [
        'categoryIds',
        'createdAt',
        'customFields',
        'features',
        'isCloseout',
        'isNew',
        'manufacturerId',
        'markAsTopseller',
        'optionIds',
        'options',
        'parentId',
        'productNumber',
        'propertyIds',
        'purchasePrices',
        'releaseDate',
        'stock',
        'streamIds',
        'tagIds',
        'taxId',
    ];

    /**
     * @return list<array{id: string, type: string, referencedId: string|null, productNumber: string|null, quantity: int, payload: array<string, mixed>, children: list<mixed>}>
     */
    public function build(Cart $cart): array
    {
        return $this->buildLineItems($cart->getLineItems());
    }

    /**
     * @return list<array{id: string, type: string, referencedId: string|null, productNumber: string|null, quantity: int, payload: array<string, mixed>, children: list<mixed>}>
     */
    private function buildLineItems(LineItemCollection $lineItems): array
    {
        $snapshot = [];

        foreach ($lineItems as $lineItem) {
            $snapshot[] = $this->buildLineItem($lineItem);
        }

        return $snapshot;
    }

    /**
     * @return array{id: string, type: string, referencedId: string|null, productNumber: string|null, quantity: int, payload: array<string, mixed>, children: list<mixed>}
     */
    private function buildLineItem(LineItem $lineItem): array
    {
        $payload = $lineItem->getPayload();

        return [
            // Die Kennung der Position wird mitgenommen, und das ist die wichtigste Zeile dieses
            // Dienstes. Sie entsteht im Javascript aus den Kundeneingaben: Gleiche Eingaben
            // ergeben dieselbe Kennung und verschmelzen zu einer Position, verschiedene bleiben
            // getrennt. Beim Wiederherstellen dieselbe Kennung zu setzen, hält dieses Verhalten
            // ein, ohne das Verfahren in PHP nachbauen zu müssen — und ein Nachbau wäre die
            // fehleranfälligste Stelle des ganzen Plugins.
            'id' => $lineItem->getId(),
            'type' => $lineItem->getType(),
            'referencedId' => $lineItem->getReferencedId(),
            // Die Artikelnummer steht oben im Abzug, aus der Nutzlast fliegt sie mit der Sperrliste.
            // Sie ist das, was der Kunde in der Meldung über fehlende Artikel wiedererkennt.
            'productNumber' => $this->readProductNumber($payload),
            'quantity' => $lineItem->getQuantity(),
            'payload' => $this->cleanPayload($payload),
            // Unsere Erweiterungen legen keine Kinder an, die Aufteilung der Meterware steht in der
            // Nutzlast. Der Kern kennt Kinder trotzdem (Bündel, Rabatte in Sets), und sie hier
            // wegzulassen hieße, einen Warenkorb still um einen Teil zu kürzen.
            'children' => $this->buildLineItems($lineItem->getChildren()),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readProductNumber(array $payload): ?string
    {
        $productNumber = $payload['productNumber'] ?? null;

        return is_string($productNumber) && $productNumber !== '' ? $productNumber : null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function cleanPayload(array $payload): array
    {
        foreach (self::VOLATILE_PAYLOAD_KEYS as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }
}
