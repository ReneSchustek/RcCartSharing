<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCartSharing\Service\RestoreRequestBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

final class RestoreRequestBuilderTest extends TestCase
{
    private RestoreRequestBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new RestoreRequestBuilder();
    }

    public function testEmptySnapshotYieldsNoRequests(): void
    {
        self::assertSame([], $this->builder->build([]));
    }

    public function testTheLineItemIdentifierSurvives(): void
    {
        // Ohne diese Zusicherung müsste das Verfahren aus dem Javascript in PHP nachgebaut werden.
        $requests = $this->builder->build([$this->snapshotItem(['id' => 'kennung-aus-dem-javascript'])]);

        self::assertSame('kennung-aus-dem-javascript', $requests[0]->getData()['id']);
    }

    public function testCustomerInputsSurvive(): void
    {
        $requests = $this->builder->build([$this->snapshotItem(['payload' => [
            'meterLengthMm' => 5100,
            'rcColorPickerRal' => '7016',
            'rcCustomFields' => ['gravur' => 'Familie Meier'],
            'rc_tmms_inputs' => ['Wandstärke' => '120 mm'],
        ]])]);

        $payload = $requests[0]->getData()['payload'];

        self::assertSame(5100, $payload['meterLengthMm']);
        self::assertSame('7016', $payload['rcColorPickerRal']);
        self::assertSame(['gravur' => 'Familie Meier'], $payload['rcCustomFields']);
        self::assertSame(['Wandstärke' => '120 mm'], $payload['rc_tmms_inputs']);
    }

    public function testChildrenAreRestoredAsWell(): void
    {
        $requests = $this->builder->build([$this->snapshotItem([
            'children' => [$this->snapshotItem(['id' => 'kind-1'])],
        ])]);

        self::assertCount(1, $requests[0]->getChildren());
        self::assertSame('kind-1', $requests[0]->getChildren()[0]->getData()['id']);
    }

    public function testAnItemWithoutChildrenCarriesNoChildren(): void
    {
        $requests = $this->builder->build([$this->snapshotItem([])]);

        self::assertSame([], $requests[0]->getChildren());
    }

    public function testTheProductNumberTravelsBesideTheFactoryData(): void
    {
        // Sie steht nicht in den Fabrikdaten: Die Fabrik prüft, was sie bekommt. Für die Meldung
        // „diese Position gibt es nicht mehr" ist die Nummer aber das Einzige, womit ein Kunde
        // etwas anfangen kann.
        $requests = $this->builder->build([$this->snapshotItem(['productNumber' => 'SW10001'])]);

        self::assertSame('SW10001', $requests[0]->getProductNumber());
        self::assertArrayNotHasKey('productNumber', $requests[0]->getData());
    }

    public function testDiscountsAreNotRestored(): void
    {
        // Ein Rabatt entsteht aus Regeln, die für den Empfänger andere sein können. Mitgenommen
        // wäre er ein Versprechen, das der nächste Rechenlauf wieder einkassiert.
        $requests = $this->builder->build([
            $this->snapshotItem(['type' => LineItem::PROMOTION_LINE_ITEM_TYPE, 'id' => 'rabatt']),
            $this->snapshotItem(['id' => 'artikel']),
        ]);

        self::assertCount(1, $requests);
        self::assertSame('artikel', $requests[0]->getData()['id']);
    }

    public function testAnItemWithoutReferenceIsSkipped(): void
    {
        $requests = $this->builder->build([$this->snapshotItem(['referencedId' => null])]);

        self::assertSame([], $requests);
    }

    public function testAnImpossibleQuantityBecomesOne(): void
    {
        $requests = $this->builder->build([$this->snapshotItem(['quantity' => 0])]);

        self::assertSame(1, $requests[0]->getData()['quantity']);
    }

    public function testProductIdsAreCollectedAcrossChildrenAndWithoutDuplicates(): void
    {
        $ids = $this->builder->collectProductIds([
            $this->snapshotItem(['referencedId' => 'artikel-a']),
            $this->snapshotItem([
                'referencedId' => 'artikel-b',
                'children' => [$this->snapshotItem(['referencedId' => 'artikel-a'])],
            ]),
        ]);

        self::assertSame(['artikel-a', 'artikel-b'], $ids);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function snapshotItem(array $overrides): array
    {
        return array_merge([
            'id' => 'position-1',
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'referencedId' => 'artikel-a',
            'productNumber' => 'SW10001',
            'quantity' => 1,
            'payload' => [],
            'children' => [],
        ], $overrides);
    }
}
