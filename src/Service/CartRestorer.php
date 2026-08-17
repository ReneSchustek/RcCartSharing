<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Service;

use Ruhrcoder\RcCartSharing\Core\Content\SharedCart\SharedCartEntity;
use Ruhrcoder\RcCartSharing\Struct\RestoreRequest;
use Ruhrcoder\RcCartSharing\Struct\RestoreResult;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Legt die Positionen eines gespeicherten Warenkorbs in den Warenkorb der aktuellen Sitzung.
 *
 * Preise werden nicht übernommen, sondern gerechnet: Die Fabrik legt die Position an, der Kern
 * bestimmt den Preis. Ein gespeicherter Preis wäre eine Zusage, die niemand einlösen will, sobald
 * sie eine Woche alt ist — im Abzug steht deshalb gar keiner.
 */
final class CartRestorer
{
    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly RestoreRequestBuilder $requestBuilder,
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly CartService $cartService,
        private readonly SalesChannelRepository $productRepository,
    ) {
    }

    public function restore(SharedCartEntity $sharedCart, SalesChannelContext $salesChannelContext): RestoreResult
    {
        $snapshot = $sharedCart->getLineItems();
        $requests = $this->requestBuilder->build($snapshot);

        if ($requests === []) {
            return new RestoreResult(0, []);
        }

        $availableProductIds = $this->findAvailableProductIds(
            $this->requestBuilder->collectProductIds($snapshot),
            $salesChannelContext
        );

        $lineItems = [];
        $missingProductNumbers = [];

        foreach ($requests as $request) {
            if (!in_array($request->getReferencedId(), $availableProductIds, true)) {
                $missingProductNumbers[] = $request->getProductNumber() ?? '';

                continue;
            }

            $lineItems[] = $this->createLineItem($request, $availableProductIds, $salesChannelContext);
        }

        if ($lineItems !== []) {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
            $this->cartService->add($cart, $lineItems, $salesChannelContext);
        }

        return new RestoreResult(count($lineItems), $missingProductNumbers);
    }

    /**
     * @param list<string> $availableProductIds
     */
    private function createLineItem(RestoreRequest $request, array $availableProductIds, SalesChannelContext $salesChannelContext): LineItem
    {
        $lineItem = $this->lineItemFactory->create($request->getData(), $salesChannelContext);

        foreach ($request->getChildren() as $child) {
            // Ein Kind, dessen Artikel es nicht mehr gibt, fällt weg — die Elternposition bleibt.
            // Sie ohne dieses Kind anzulegen ist näher am Gewollten als gar nichts anzulegen.
            if (!in_array($child->getReferencedId(), $availableProductIds, true)) {
                continue;
            }

            $lineItem->addChild($this->createLineItem($child, $availableProductIds, $salesChannelContext));
        }

        return $lineItem;
    }

    /**
     * Welche der gespeicherten Artikel gibt es im Verkaufskanal noch?
     *
     * Über das Verkaufskanal-Repository, nicht über das Verwaltungs-Repository: Sichtbarkeit und
     * Freigabe für genau diesen Kanal entscheiden mit. Ein Artikel, den es nur in einem anderen
     * Kanal gibt, ist für diesen Kunden nicht vorhanden.
     *
     * @param list<string> $productIds
     *
     * @return list<string>
     */
    private function findAvailableProductIds(array $productIds, SalesChannelContext $salesChannelContext): array
    {
        if ($productIds === []) {
            return [];
        }

        $criteria = new Criteria($productIds);
        $criteria->setLimit(count($productIds));

        /** @var list<string> $found */
        $found = $this->productRepository->searchIds($criteria, $salesChannelContext)->getIds();

        return $found;
    }
}
