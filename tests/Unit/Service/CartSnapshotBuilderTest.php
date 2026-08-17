<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Ruhrcoder\RcCartSharing\Service\CartSnapshotBuilder;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * Der Abzug ist nur so viel wert wie das, was er von den Positionen behält. Jede der fünf
 * Erweiterungen, die in die Nutzlast schreibt, bekommt hier ihre eigene Zusicherung — sonst fällt
 * ein Verlust erst auf, wenn ein Kunde einen geteilten Warenkorb bestellt und etwas anderes
 * bekommt als besprochen.
 */
final class CartSnapshotBuilderTest extends TestCase
{
    private CartSnapshotBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CartSnapshotBuilder();
    }

    public function testEmptyCartYieldsEmptySnapshot(): void
    {
        self::assertSame([], $this->builder->build(new Cart('test-token')));
    }

    public function testSnapshotKeepsTheLineItemIdentifier(): void
    {
        // Die Kennung trägt das Verschmelzungsverhalten: Gleiche Kundeneingaben ergeben dieselbe
        // Kennung und damit eine Position, verschiedene bleiben getrennt. Ginge sie verloren,
        // müsste das Verfahren beim Wiederherstellen in PHP nachgebaut werden.
        $cart = $this->cartWith($this->productLineItem('a1b2c3-aus-dem-javascript', 'product-id', 1, []));

        self::assertSame('a1b2c3-aus-dem-javascript', $this->builder->build($cart)[0]['id']);
    }

    public function testSnapshotKeepsTypeQuantityAndReference(): void
    {
        $cart = $this->cartWith($this->productLineItem('line-1', 'product-id', 3, [
            'productNumber' => 'SW10001',
        ]));

        $snapshot = $this->builder->build($cart);

        self::assertCount(1, $snapshot);
        self::assertSame(LineItem::PRODUCT_LINE_ITEM_TYPE, $snapshot[0]['type']);
        self::assertSame('product-id', $snapshot[0]['referencedId']);
        self::assertSame('SW10001', $snapshot[0]['productNumber']);
        self::assertSame(3, $snapshot[0]['quantity']);
    }

    public function testSnapshotKeepsTheMeterLength(): void
    {
        $cart = $this->cartWith($this->productLineItem('line-1', 'product-id', 1, [
            'meterLengthMm' => 5100,
            'rc_split_pieces' => [5000, 100],
            'rc_rounding_mode' => 'up',
        ]));

        $payload = $this->builder->build($cart)[0]['payload'];

        self::assertSame(5100, $payload['meterLengthMm']);
        self::assertSame([5000, 100], $payload['rc_split_pieces']);
        self::assertSame('up', $payload['rc_rounding_mode']);
    }

    public function testSnapshotKeepsTheChosenColour(): void
    {
        $cart = $this->cartWith($this->productLineItem('line-1', 'product-id', 1, [
            'rcColorPickerActive' => true,
            'rcColorPickerRal' => '7016',
            'rcColorPickerName' => 'Anthrazitgrau',
            'rcColorPickerHex' => '#383E42',
        ]));

        $payload = $this->builder->build($cart)[0]['payload'];

        self::assertSame('7016', $payload['rcColorPickerRal']);
        self::assertSame('Anthrazitgrau', $payload['rcColorPickerName']);
        self::assertSame('#383E42', $payload['rcColorPickerHex']);
    }

    public function testSnapshotKeepsCustomerInputs(): void
    {
        $cart = $this->cartWith($this->productLineItem('line-1', 'product-id', 1, [
            'rcCustomFields' => ['gravur' => 'Familie Meier', 'bohrung' => '8 mm'],
            'rcTmmsActive' => '1',
            'rc_tmms_inputs' => ['Wandstärke' => '120 mm'],
        ]));

        $payload = $this->builder->build($cart)[0]['payload'];

        self::assertSame(['gravur' => 'Familie Meier', 'bohrung' => '8 mm'], $payload['rcCustomFields']);
        self::assertSame(['Wandstärke' => '120 mm'], $payload['rc_tmms_inputs']);
    }

    public function testSnapshotKeepsUnknownPayloadKeys(): void
    {
        // Der Kern der Entscheidung gegen eine Positivliste: Was eine künftige Erweiterung
        // schreibt, kennt dieser Dienst nicht — und muss es auch nicht.
        $cart = $this->cartWith($this->productLineItem('line-1', 'product-id', 1, [
            'einNeuerSchluessel' => 'bleibt erhalten',
        ]));

        $payload = $this->builder->build($cart)[0]['payload'];

        self::assertSame('bleibt erhalten', $payload['einNeuerSchluessel']);
    }

    public function testSnapshotDropsWhatShopwareRecalculates(): void
    {
        $cart = $this->cartWith($this->productLineItem('line-1', 'product-id', 1, [
            'productNumber' => 'SW10001',
            'purchasePrices' => '{"net":10}',
            'stock' => 42,
            'isCloseout' => false,
            'customFields' => ['irgendwas' => 'vom Produkt'],
            'meterLengthMm' => 2000,
        ]));

        $payload = $this->builder->build($cart)[0]['payload'];

        self::assertArrayNotHasKey('purchasePrices', $payload);
        self::assertArrayNotHasKey('stock', $payload);
        self::assertArrayNotHasKey('isCloseout', $payload);
        self::assertArrayNotHasKey('customFields', $payload);
        self::assertArrayNotHasKey('productNumber', $payload);
        self::assertSame(2000, $payload['meterLengthMm'], 'Die eigene Nutzlast bleibt unberührt.');
    }

    public function testSnapshotKeepsSplitChildren(): void
    {
        $parent = $this->productLineItem('line-1', 'product-id', 1, ['meterLengthMm' => 5100]);
        $parent->addChild($this->productLineItem('child-1', 'product-id', 1, ['meterLengthMm' => 5000]));
        $parent->addChild($this->productLineItem('child-2', 'product-id', 1, ['meterLengthMm' => 100]));

        $snapshot = $this->builder->build($this->cartWith($parent));

        self::assertCount(2, $snapshot[0]['children']);
        self::assertSame(5000, $snapshot[0]['children'][0]['payload']['meterLengthMm']);
        self::assertSame(100, $snapshot[0]['children'][1]['payload']['meterLengthMm']);
    }

    public function testProductNumberIsNullWhenThePayloadHasNone(): void
    {
        $cart = $this->cartWith($this->productLineItem('line-1', 'product-id', 1, []));

        self::assertNull($this->builder->build($cart)[0]['productNumber']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function productLineItem(string $id, string $referencedId, int $quantity, array $payload): LineItem
    {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $referencedId, $quantity);
        $lineItem->setPayload($payload);

        return $lineItem;
    }

    private function cartWith(LineItem ...$lineItems): Cart
    {
        $cart = new Cart('test-token');
        $cart->setLineItems(new LineItemCollection($lineItems));

        return $cart;
    }
}
