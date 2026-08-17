<?php

declare(strict_types=1);

namespace Ruhrcoder\RcCartSharing\Storefront\Controller;

use Ruhrcoder\RcCartSharing\Service\CartRestorer;
use Ruhrcoder\RcCartSharing\Service\SharedCartLoader;
use Ruhrcoder\RcCartSharing\Service\SharedCartWriter;
use Ruhrcoder\RcCartSharing\Service\ShareMailer;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Die zwei Wege der Erweiterung: Warenkorb speichern und Warenkorb aufrufen.
 *
 * Beide kommen ohne Javascript aus — ein gewöhnliches Formular und eine gewöhnliche Adresse. Wer
 * seinen Warenkorb an einen Kollegen schickt, sitzt oft in einem Umfeld, in dem Skripte blockiert
 * sind; ein Knopf, der dann nichts tut, ist schlimmer als keiner.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
final class SharedCartController extends StorefrontController
{
    /**
     * Der Grenzwert bremst den einzelnen Absender, nicht alle Besucher gemeinsam. Ohne ihn legt
     * ein Skript in einer Minute so viele Abzüge an, wie es Anfragen schafft.
     */
    private const SAVE_LIMIT_ROUTE = 'rc_cart_sharing_save';

    /** Enger als beim Speichern: Eine Nachricht verlässt den Shop, ein Abzug bleibt darin. */
    private const SEND_LIMIT_ROUTE = 'rc_cart_sharing_send';

    public function __construct(
        private readonly CartService $cartService,
        private readonly SharedCartWriter $writer,
        private readonly SharedCartLoader $loader,
        private readonly CartRestorer $restorer,
        private readonly RateLimiter $rateLimiter,
        private readonly ShareMailer $mailer,
    ) {
    }

    #[Route(
        path: '/rc-cart-sharing/save',
        name: 'frontend.rc-cart-sharing.save',
        methods: ['POST'],
    )]
    public function save(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        if ($cart->getLineItems()->count() === 0) {
            $this->addFlash(self::DANGER, $this->trans('rcCartSharing.save.emptyCart'));

            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        try {
            $this->rateLimiter->ensureAccepted(self::SAVE_LIMIT_ROUTE, $this->limiterKey($request, $salesChannelContext));
        } catch (RateLimitExceededException) {
            $this->addFlash(self::INFO, $this->trans('rcCartSharing.save.tooManyRequests'));

            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $name = $request->request->get('rcCartSharingName');
        $token = $this->writer->save($cart, $salesChannelContext, is_string($name) ? $name : null);

        // Die Adresse steht danach als Kasten über dem Warenkorb, nicht in einer Meldung, die
        // nach ein paar Sekunden verschwindet: Sie ist das Ergebnis der Handlung.
        return $this->redirectToRoute('frontend.checkout.cart.page', ['rcSharedCart' => $token]);
    }

    #[Route(
        path: '/rc-cart-sharing/load/{token}',
        name: 'frontend.rc-cart-sharing.load',
        methods: ['GET'],
    )]
    public function load(string $token, SalesChannelContext $salesChannelContext): Response
    {
        $sharedCart = $this->loader->loadByToken($token, $salesChannelContext->getContext());

        if ($sharedCart === null) {
            // Unbekannt und abgelaufen sind hier derselbe Fall. Der Unterschied wäre eine
            // Auskunft darüber, dass eine geratene Kennung einmal gültig war.
            $this->addFlash(self::DANGER, $this->trans('rcCartSharing.load.unknownToken'));

            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $result = $this->restorer->restore($sharedCart, $salesChannelContext);

        if ($result->isEmpty()) {
            $this->addFlash(self::DANGER, $this->trans('rcCartSharing.load.nothingRestored'));

            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        if ($result->hasMissingItems()) {
            // Die Artikelnummern stehen ausdrücklich in der Meldung: Ein Warenkorb, der stumm um
            // zwei Positionen kürzer zurückkommt, lässt den Kunden etwas anderes bestellen als
            // besprochen — und er merkt es nicht.
            $this->addFlash(self::WARNING, $this->trans('rcCartSharing.load.missingItems', [
                '%products%' => implode(', ', array_filter(
                    $result->getMissingProductNumbers(),
                    static fn (string $productNumber): bool => $productNumber !== ''
                )),
            ]));
        }

        return $this->redirectToRoute('frontend.checkout.cart.page');
    }

    #[Route(
        path: '/rc-cart-sharing/send',
        name: 'frontend.rc-cart-sharing.send',
        methods: ['POST'],
    )]
    public function send(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $token = (string) $request->request->get('token', '');
        $recipient = trim((string) $request->request->get('recipient', ''));

        // Von hier an ist die Antwort für jeden Ausgang dieselbe: Ob eine Kennung gilt und ob
        // eine Adresse existiert, geht den Absender nichts an. Wer beides ausprobieren kann,
        // benutzt das Formular als Auskunftsdienst.
        $answer = $this->redirectToRoute('frontend.checkout.cart.page');
        $this->addFlash(self::INFO, $this->trans('rcCartSharing.send.done'));

        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return $answer;
        }

        try {
            $this->rateLimiter->ensureAccepted(self::SEND_LIMIT_ROUTE, $this->limiterKey($request, $salesChannelContext));
        } catch (RateLimitExceededException) {
            return $answer;
        }

        $sharedCart = $this->loader->loadByToken($token, $salesChannelContext->getContext());
        if ($sharedCart === null) {
            return $answer;
        }

        $this->mailer->send(
            $recipient,
            $this->generateUrl('frontend.rc-cart-sharing.load', ['token' => $sharedCart->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            $sharedCart->getName(),
            $salesChannelContext
        );

        return $answer;
    }

    /**
     * Schlüssel der Begrenzung ist die aufrufende Adresse — nicht die Sitzung: Eine neue Sitzung
     * ist einen Aufruf entfernt und wäre keine Hürde.
     */
    private function limiterKey(Request $request, SalesChannelContext $salesChannelContext): string
    {
        return ($request->getClientIp() ?? 'unbekannt') . '-' . $salesChannelContext->getSalesChannelId();
    }
}
