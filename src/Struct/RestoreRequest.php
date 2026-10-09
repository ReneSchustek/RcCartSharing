<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Struct;

/**
 * Eine Position aus dem Abzug, übersetzt in das, was die Positions-Fabrik des Kerns braucht, und
 * dazu die Angaben, die für den Bericht nötig sind.
 *
 * Die Artikelnummer steht neben den Fabrikdaten und nicht darin: Die Fabrik prüft nur die
 * Schlüssel, die sie kennt, und ein zusätzlicher Schlüssel wäre bestenfalls wirkungslos. Für die
 * Meldung „diese Position gibt es nicht mehr" ist die Nummer aber das Einzige, womit der Kunde
 * etwas anfangen kann.
 */
final class RestoreRequest
{
    /**
     * @param array<string, mixed> $data
     * @param list<RestoreRequest> $children
     */
    public function __construct(
        private readonly array $data,
        private readonly ?string $productNumber,
        private readonly array $children = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /** Leer, wenn die Fabrikdaten keine Verweiskennung tragen; der Abgleich findet dann nichts. */
    public function getReferencedId(): string
    {
        $referencedId = $this->data['referencedId'] ?? '';

        return is_string($referencedId) ? $referencedId : '';
    }

    public function getProductNumber(): ?string
    {
        return $this->productNumber;
    }

    /**
     * @return list<RestoreRequest>
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}
