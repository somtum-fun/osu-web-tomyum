<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Libraries\OrderCheckout;
use App\Models\Store\Order;
use Auth;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Request;

/**
 * TomYum: paying for a store order (osu!supporter tags) with TrueMoney or a card, instead of PayPal and
 * Xsolla. payments-service takes the payment and tells the website the order is paid
 * (TomYumInterOpController::storeOrderPaid), which fulfils it like any osu!store order. The player's
 * requests go to it from here, with the shared secret and who's signed in, so it never faces the
 * internet itself; only Stripe's webhook reaches it directly.
 */
class TomYumCheckoutController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        if (!$this->isAllowRestrictedUsers()) {
            $this->middleware('check-user-restricted');
        }
        $this->middleware('verify-user');

        parent::__construct();
    }

    /** A TrueMoney gift voucher for exactly the order's total: redeemed, and the order paid at once. */
    public function truemoney($id)
    {
        $voucher = trim((string) Request::input('voucher'));
        if ($voucher === '') {
            return $this->failed($id, 'Paste the link of a TrueMoney gift voucher.');
        }
        [$order, $error] = $this->beginCheckout($id, Order::PROVIDER_TRUEMONEY);
        if ($error !== null) {
            return $error;
        }

        $response = $this->payments('/store/truemoney/redeem', $order, ['voucher_code' => $voucher]);
        if ($response?->json('status') === 'success') {
            return ujs_redirect(route('store.invoice.show', ['invoice' => $order->getKey(), 'thanks' => 1]));
        }

        // Nothing was taken (a wrong amount or a bad voucher is refused before redeeming): back to paying.
        $this->release($order, Order::PROVIDER_TRUEMONEY);

        return $this->failed($id, $this->message($response, 'The voucher couldn\'t be used. Nothing was charged.'));
    }

    /** A card payment on Stripe's checkout page; Stripe's webhook marks the order paid. */
    public function stripe($id)
    {
        [$order, $error] = $this->beginCheckout($id, Order::PROVIDER_STRIPE);
        if ($error !== null) {
            return $error;
        }

        $response = $this->payments('/store/stripe/checkout', $order, [
            'success_url' => route('store.checkout.tomyum.stripe-return', ['checkout' => $order->getKey(), 'result' => 'success']),
            'cancel_url' => route('store.checkout.tomyum.stripe-return', ['checkout' => $order->getKey(), 'result' => 'cancel']),
        ]);
        $url = $response?->json('url');
        if (is_string($url) && str_starts_with($url, 'https://checkout.stripe.com/')) {
            return redirect()->away($url);
        }

        $this->release($order, Order::PROVIDER_STRIPE);

        return $this->failed($id, $this->message($response, 'Card payments aren\'t available right now.'));
    }

    /** Back from Stripe: paid (the webhook may still be on its way) or cancelled. */
    public function stripeReturn($id)
    {
        $order = Auth::user()->orders()->findOrFail($id);

        if (Request::input('result') === 'success') {
            // Complete it now if Stripe has the payment, rather than waiting for its webhook.
            $this->payments('/store/stripe/sync', $order, []);
            $order = $order->fresh();
            if ($order->isPaymentRequested()) {
                (new OrderCheckout($order, Order::PROVIDER_STRIPE))->completeCheckout();
            }

            return ujs_redirect(route('store.invoice.show', ['invoice' => $order->getKey(), 'thanks' => 1]));
        }

        $this->release($order, Order::PROVIDER_STRIPE);

        return ujs_redirect(route('store.checkout.show', ['checkout' => $order->getKey()]));
    }

    /** [the order, now awaiting payment by $provider; or null and where to send the player]. */
    private function beginCheckout($id, string $provider): array
    {
        $order = Auth::user()->orders()->whereCanCheckout()->with('items.product')->find($id);
        if ($order === null || $order->isEmpty()) {
            return [null, ujs_redirect(route('store.cart.show'))];
        }
        if (Request::boolean('hide_from_activity')) {
            $order->setGiftsHidden(true);
        }

        $checkout = new OrderCheckout($order, $provider);
        if (!empty($checkout->validate())) {
            return [null, $this->failed($id, osu_trans('store.checkout.cart_problems'))];
        }
        $checkout->beginCheckout();

        return [$order->fresh(), null];
    }

    private function failed($id, string $message)
    {
        return ujs_redirect(route('store.checkout.show', ['checkout' => $id]))->with('checkout.error.message', $message);
    }

    /** payments-service's message for a refusal, else $fallback (never its internal details). */
    private function message(?Response $response, string $fallback): string
    {
        $message = $response?->json('message');

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    private function payments(string $path, Order $order, array $body): ?Response
    {
        $url = $GLOBALS['cfg']['tomyum']['payments']['url'];
        if ($url === null) {
            return null;
        }

        try {
            return Http::timeout(30)->acceptJson()->withHeaders([
                'X-Internal-Token' => $GLOBALS['cfg']['osu']['legacy']['shared_interop_secret'],
                'X-User-Id' => (string) Auth::user()->getKey(),
            ])->post(rtrim($url, '/').$path, [
                'order_id' => $order->getKey(),
                'amount_thb' => (float) $order->getTotal(),
                // What the player was shown and pays by card: baht in Thailand, else US$.
                'charge_currency' => store_currency(),
                'charge_amount' => store_currency() === 'usd'
                    ? round((float) $order->getTotal() / \App\Models\SupporterTag::BAHT_PER_DOLLAR, 2)
                    : (float) $order->getTotal(),
                'description' => implode(', ', $order->items->map->getDisplayName()->all()),
                ...$body,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Back to the cart's checkout (payment not taken), if it's still waiting for payment. */
    private function release(Order $order, string $provider): void
    {
        $order = $order->fresh();
        if ($order?->isPaymentRequested()) {
            $order = (new OrderCheckout($order, $provider))->failCheckout();
            // Back into the cart, where the player left it.
            $order->status = Order::STATUS_INCART;
            $order->saveOrExplode();
        }
    }
}
