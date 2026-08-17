<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Storefront\Controller;

use Ruhrcoder\RcCartSharing\Service\SharedCartManager;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Die gespeicherten Warenkörbe im Kundenkonto: ansehen, umbenennen, löschen.
 *
 * Jede Handlung geht über den {@see SharedCartManager}, und der prüft die Zugehörigkeit. Ein
 * fremder Warenkorb verhält sich hier wie ein nicht vorhandener — dieselbe Meldung, derselbe Weg
 * zurück. Alles andere verriete, dass es ihn gibt.
 */
#[Route(defaults: ['_routeScope' => ['storefront'], '_loginRequired' => true])]
final class SharedCartAccountController extends StorefrontController
{
    public function __construct(
        private readonly SharedCartManager $manager,
        private readonly GenericPageLoaderInterface $genericPageLoader,
    ) {
    }

    #[Route(
        path: '/account/saved-carts',
        name: 'frontend.account.rc-cart-sharing.page',
        methods: ['GET'],
    )]
    public function index(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $customer = $this->requireCustomer($salesChannelContext);

        $page = $this->genericPageLoader->load($request, $salesChannelContext);

        return $this->renderStorefront('@RcCartSharing/storefront/page/account/saved-carts/index.html.twig', [
            'page' => $page,
            'rcSharedCarts' => $this->manager->listForCustomer($customer->getId(), $salesChannelContext->getContext()),
        ]);
    }

    #[Route(
        path: '/account/saved-carts/rename',
        name: 'frontend.account.rc-cart-sharing.rename',
        methods: ['POST'],
    )]
    public function rename(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $customer = $this->requireCustomer($salesChannelContext);

        $id = (string) $request->request->get('sharedCartId', '');
        $name = (string) $request->request->get('name', '');

        $renamed = $this->manager->rename($id, $customer->getId(), $name, $salesChannelContext->getContext());

        $this->addFlash(
            $renamed ? self::SUCCESS : self::DANGER,
            $this->trans($renamed ? 'rcCartSharing.account.renamed' : 'rcCartSharing.account.notFound')
        );

        return $this->redirectToRoute('frontend.account.rc-cart-sharing.page');
    }

    #[Route(
        path: '/account/saved-carts/delete',
        name: 'frontend.account.rc-cart-sharing.delete',
        methods: ['POST'],
    )]
    public function delete(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $customer = $this->requireCustomer($salesChannelContext);

        $id = (string) $request->request->get('sharedCartId', '');

        $deleted = $this->manager->delete($id, $customer->getId(), $salesChannelContext->getContext());

        $this->addFlash(
            $deleted ? self::SUCCESS : self::DANGER,
            $this->trans($deleted ? 'rcCartSharing.account.deleted' : 'rcCartSharing.account.notFound')
        );

        return $this->redirectToRoute('frontend.account.rc-cart-sharing.page');
    }

    /**
     * `_loginRequired` hält den nicht angemeldeten Besucher schon vor dem Aufruf ab. Diese Prüfung
     * ist die zweite Absicherung — und sie macht aus „vermutlich angemeldet" einen Typ, mit dem
     * sich weiterarbeiten lässt.
     */
    private function requireCustomer(SalesChannelContext $salesChannelContext): CustomerEntity
    {
        $customer = $salesChannelContext->getCustomer();

        if (!$customer instanceof CustomerEntity) {
            throw $this->createAccessDeniedException('Für diesen Weg ist eine Anmeldung nötig.');
        }

        return $customer;
    }
}
