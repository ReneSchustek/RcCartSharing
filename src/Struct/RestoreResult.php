<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Was beim Wiederherstellen herausgekommen ist.
 *
 * Der Bericht ist kein Beiwerk: Ein Warenkorb, der stumm um zwei Positionen kürzer zurückkommt,
 * lässt den Kunden etwas anderes bestellen als besprochen, ohne dass er es merkt. Deshalb wird
 * gezählt, was ankam, und benannt, was nicht.
 */
final class RestoreResult extends Struct
{
    /**
     * @param list<string> $missingProductNumbers
     */
    public function __construct(
        private readonly int $restoredCount,
        private readonly array $missingProductNumbers,
    ) {
    }

    public function getRestoredCount(): int
    {
        return $this->restoredCount;
    }

    /**
     * @return list<string>
     */
    public function getMissingProductNumbers(): array
    {
        return $this->missingProductNumbers;
    }

    public function hasMissingItems(): bool
    {
        return $this->missingProductNumbers !== [];
    }

    public function isEmpty(): bool
    {
        return $this->restoredCount === 0;
    }
}
