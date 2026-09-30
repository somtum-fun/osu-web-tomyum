{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: paying on Stripe's checkout page (payments-service). --}}
<form method="POST" action="{{ route('store.checkout.tomyum.stripe', ['checkout' => $order->getKey()]) }}" class="store-payment-method js-tomyum-payment-form" data-turbo="false">
    @csrf
    <input type="hidden" name="hide_from_activity" value="0">

    <div class="store-payment-method__cell">
        <button type="submit" class="store-payment-button store-payment-button--stripe" title="Pay {{ currency($order->getTotal()) }} with Stripe"></button>
    </div>

    <div class="store-payment-method__cell">
        <div class="store-text store-text--header">Pay with Card{{ store_currency() === 'thb' ? ' / PromptPay' : '' }}</div>

        <div class="store-text store-text--block">
            Pay <span class="store-text store-text--emphasis">{{ currency($order->getTotal()) }}</span>
            on Stripe's secure checkout page. You'll come back here when it's done.
        </div>

        <div class="store-text store-text--block store-text--emphasis">Accepted through Stripe</div>
        <div class="store-payment-method__provider-list">
            <img class="store-payment-method__provider store-payment-method__provider--light" src="/images/store/providers/visa.svg" alt="Visa">
            <img class="store-payment-method__provider store-payment-method__provider--light" src="/images/store/providers/mastercard.svg" alt="Mastercard">
            @if (store_currency() === 'thb')
                {{-- Stripe only offers PromptPay for payments in baht. --}}
                <img class="store-payment-method__provider store-payment-method__provider--light" src="/images/store/providers/promptpay.png" alt="PromptPay">
            @endif
        </div>
    </div>
</form>

<script>
    // The "hide from my activity" switch sits outside the payment forms: send its state with them.
    document.querySelectorAll('.js-tomyum-payment-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var hide = document.querySelector('.js-hide-from-activity');
            form.querySelector('[name="hide_from_activity"]').value = hide != null && hide.checked ? '1' : '0';
        });
    });
</script>
