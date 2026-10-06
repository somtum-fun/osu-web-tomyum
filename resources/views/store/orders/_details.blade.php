{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
<div class="store-page">
    <div class="store-page__invoice-header">
        <div>
            <h1 class="store-text store-text--title">{{ osu_trans('store.invoice.title') }}</h1>
            <div>
                {{ osu_trans('store.invoice.date') }}
                @if($order->shipped_at)
                    {{ $order->shipped_at->toDateString() }}
                @elseif($order->paid_at)
                    {{ $order->paid_at->toDateString() }}
                @else
                    {{ $order->updated_at->toDateString() }}
                @endif
            </div>
        </div>
        <div>
            {{-- TomYum: who sells, and how to reach them (Admin panel > Branding). --}}
            @if (present($invoiceSeller = App\Libraries\TomYumBranding::text('invoice_seller')))
                <p>
                    <em class="store-text store-text--emphasis">{{ $invoiceSeller }}</em>
                </p>
            @endif

            @if (present($invoiceContact = App\Libraries\TomYumBranding::text('invoice_contact')))
                <p>{{ osu_trans('store.invoice.contact') }} {{ $invoiceContact }}</p>
            @endif
        </div>
    </div>

    <hr />

    @if($order->address !== null)
        <div class="store-page__address">
            <div>
                <h4 class="store-text store-text--title store-text--title-small">{{ osu_trans('store.invoice.sent_via') }}</h4>

                <div class='address'>
                    osu!store
                </div>
            </div>

            <div>
                <h4 class="store-text store-text--title store-text--title-small">{{ osu_trans('store.invoice.shipping_to') }}</h4>

                @include('store.objects.address', ['data' => $order->address])
            </div>
        </div>
    @endif

    <div class="store-page__order-details">
        <h3 class="store-text store-text--title">{{ osu_trans('store.order.details.title') }}</h3>

        @include('store.objects.order', [
            'order' => $order,
            'weight' => true,
            'checkout' => false,
        ])

        @if ($order->isHideSupporterFromActivity())
            {{ osu_trans('store.invoice.hide_from_activity') }}
        @endif
    </div>

    @if ($order->address !== null)
        @php
            $showTrackingCode = ($order->isShipped() || $order->isDelivered() || Auth::user()->isAdmin()) && $order->tracking_code;

            $transactionDetails = [
                osu_trans('store.order.details.salesperson') => 'osu!store',
                osu_trans('store.order.details.order_number') => "#{$order->order_id}",
                osu_trans('store.order.details.shipping_method') => $showTrackingCode ? 'EMS ('.trim($order->tracking_code).')' : 'N/A',
                osu_trans('store.order.details.shipping_terms') => 'FOB Japan',
                osu_trans('store.order.details.payment_terms') => studly_case($order->getPaymentProvider()).' ('.$order->getPaymentStatusText().')',
            ];
        @endphp
        <dl class="store-transaction-info">
            @foreach ($transactionDetails as $key => $value)
                <dt class="store-transaction-info__entry store-transaction-info__entry--term">
                    {{ $key }}
                </dt>
                <dd class="store-transaction-info__entry">
                    {{ $value }}
                </dd>
            @endforeach
        </dl>
    @endif
</div>
