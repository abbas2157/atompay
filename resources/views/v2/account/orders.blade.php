{{--
    My orders (v2). Every Atomshop order bought against the AtomPay limit, each
    one expandable to its Atomshop status track and full payment schedule.

    Plans come from PaymentScheduleService::forUser(), which gives per order:
    order, product, instalments, paid_count, total_count, paid_amount,
    total_amount, progress, is_active, has_late and a one-word `state`.
--}}
@extends('v2.layout.account')

@section('acct-nav', 'orders')
@section('acct-eyebrow', 'Orders')
@section('acct-title', 'My orders')
@section('acct-lead', 'Every Atomshop.pk order placed with your AtomPay limit, with each payment and the date it falls due.')

@section('acct')
    @php
        use App\Models\Enums\OrderStatus;

        // The tabs group the six plan states into the three questions a
        // customer actually asks: what am I paying, what is waiting, what is done.
        $group = fn (string $state) => match ($state) {
            'on_track', 'late' => 'repaying',
            'pending', 'processing' => 'awaiting',
            default => 'closed',
        };

        $counts = $plans->groupBy(fn ($plan) => $group($plan['state']))->map->count();
        $missed = $plans->where('has_late', true)->count();

        $statusFlow = [
            OrderStatus::Pending, OrderStatus::Verification, OrderStatus::Processing,
            OrderStatus::Delivered, OrderStatus::Instalments, OrderStatus::Completed,
        ];

        $rowLabels = ['paid' => 'Paid', 'late' => 'Late', 'due' => 'Due soon', 'upcoming' => 'Upcoming'];
        $rowTones = ['paid' => 'b-green', 'late' => 'b-rose', 'due' => 'b-amber', 'upcoming' => 'b-grey'];
    @endphp

    {{-- Three figures worth knowing before any order. --}}
    <div class="grid g-3">
        <div class="card stat-card">
            <span class="icon-tile t-rose" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 7.5h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 11h18M7 15.5h3.5"/></svg></span>
            <div><div class="stat-n">@rs($credit['available'])</div><div class="stat-l">Left of your limit</div></div>
        </div>
        <div class="card stat-card">
            <span class="icon-tile t-amber" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4.5" width="18" height="17" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/></svg></span>
            <div>
                <div class="stat-n">{{ $nextDue?->installment_date?->format('d M Y') ?? '—' }}</div>
                <div class="stat-l">{{ $nextDue ? 'Next payment · '.\App\Support\Money::format($nextDue->installment_price, 'Rs. ') : 'Nothing due' }}</div>
            </div>
        </div>
        <div class="card stat-card">
            <span class="icon-tile {{ $missed ? 't-rose' : 't-green' }}" aria-hidden="true">
                <svg viewBox="0 0 24 24">@if ($missed)<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>@else<path d="M20 6 9 17l-5-5"/>@endif</svg>
            </span>
            <div><div class="stat-n">{{ $missed }}</div><div class="stat-l">{{ $missed === 1 ? 'Order with a late payment' : 'Orders with a late payment' }}</div></div>
        </div>
    </div>

    <div>
        <div class="card-head">
            <div>
                <h2 class="h3">Your Atomshop orders</h2>
                <p class="body">Every order placed with your AtomPay limit, newest first.</p>
            </div>
            <a class="btn btn-p btn-sm" href="{{ $shopLink }}" target="_blank" rel="noopener">Shop on Atomshop.pk</a>
        </div>

        @if ($plans->isEmpty())
            <div class="card" style="text-align:center">
                <h3 class="h3">No orders yet</h3>
                <p class="body" style="margin-top:8px">Choose AtomPay at Atomshop.pk checkout on any eligible listing and the order will appear here, with its three payments.</p>
                <a class="btn btn-p" style="margin-top:18px" href="{{ $shopLink }}" target="_blank" rel="noopener">Browse Atomshop.pk</a>
            </div>
        @else
            <div class="tabbar" role="group" aria-label="Filter orders" data-filters="#order-list" data-empty="#order-empty">
                <button class="pill on" type="button" data-filter="all" aria-pressed="true">All <span class="n">{{ $plans->count() }}</span></button>
                @foreach (['repaying' => 'Repaying', 'awaiting' => 'Awaiting approval', 'closed' => 'Closed'] as $key => $label)
                    @if (($counts[$key] ?? 0) > 0)
                        <button class="pill" type="button" data-filter="{{ $key }}" aria-pressed="false">{{ $label }} <span class="n">{{ $counts[$key] }}</span></button>
                    @endif
                @endforeach
            </div>

            <div id="order-list">
                @foreach ($plans as $plan)
                    @php
                        $order = $plan['order'];
                        $product = $plan['product'];
                        $state = $plan['state'];

                        [$tone, $pill] = match ($state) {
                            'late' => ['b-rose', 'Payment overdue'],
                            'completed' => ['b-green', 'Completed'],
                            'on_track' => ['b-amber', 'Repaying'],
                            'cancelled' => ['b-grey', 'Cancelled'],
                            'pending' => ['b-blue', $order->status === OrderStatus::Verification ? 'In verification' : 'Awaiting approval'],
                            default => ['b-blue', $order->status->label()],
                        };

                        $note = match ($state) {
                            'pending' => 'Atomshop is reviewing this order. Your payment schedule appears here once it is approved and delivered, and nothing is due before then.',
                            'processing' => $order->status === OrderStatus::Delivered
                                ? 'Delivered. Your payment schedule will appear here shortly.'
                                : 'Approved and being prepared. Your payments start once it is delivered.',
                            'cancelled' => 'This order was cancelled. Nothing is owed on it.',
                            default => null,
                        };

                        $reached = array_search($order->status, $statusFlow, true);
                        $outstanding = max(0, $plan['total_amount'] - $plan['paid_amount']);
                    @endphp

                    <details class="order" data-state="{{ $group($state) }}" @if ($loop->first) open @endif>
                        <summary>
                            <span class="o-main">
                                <span class="thumb" aria-hidden="true">
                                    @if ($product?->picture)
                                        <img src="{{ rtrim(config('atompay.asset_url'), '/').'/'.ltrim($product->picture, '/') }}" alt="" loading="lazy" decoding="async">
                                    @else
                                        <svg viewBox="0 0 24 24"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                    @endif
                                </span>
                                <span style="min-width:0">
                                    <span class="o-meta">#{{ $order->reference }} &middot; Ordered {{ $order->created_at?->format('d M Y') }}</span>
                                    <span class="o-title">{{ $product?->title ?? 'Atomshop order' }}</span>
                                </span>
                            </span>

                            <span class="o-amt">
                                @if ($plan['total_count'] > 0)
                                    <b>@rs($plan['instalments']->first()?->installment_price)</b>
                                    <span>&times; {{ $plan['total_count'] }} months &middot; @rs($plan['total_amount'])</span>
                                @else
                                    <b>@rs($order->total_deal_price)</b>
                                    <span>Schedule not set yet</span>
                                @endif
                            </span>

                            <span class="badge {{ $tone }}">{{ $pill }}</span>
                            <span class="chev" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span>
                        </summary>

                        <div class="o-body">
                            @if ($order->status !== OrderStatus::Cancelled)
                                <ol class="track">
                                    @foreach ($statusFlow as $i => $step)
                                        <li @class([
                                            'done' => $reached !== false && $i < $reached,
                                            'now' => $reached !== false && $i === $reached,
                                        ])>
                                            {{ $step->label() }}
                                            @if ($reached !== false && $i === $reached)
                                                <small>{{ $plan['total_count'] > 0 ? $plan['paid_count'].' of '.$plan['total_count'].' paid' : 'In progress' }}</small>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            @endif

                            @if ($note)
                                <div class="note {{ $state === 'cancelled' ? '' : 'blue' }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.3l3 1.8"/></svg>
                                    <span class="nbody">{{ $note }}</span>
                                </div>
                            @endif

                            @if ($plan['total_count'] > 0)
                                <div class="bar-l">
                                    <span>{{ $plan['paid_count'] }} of {{ $plan['total_count'] }} payments made</span>
                                    <span>@rs($plan['paid_amount']) of @rs($plan['total_amount']) paid</span>
                                </div>
                                <div @class(['bar', 'green' => $state === 'completed', 'apay' => $state !== 'completed'])
                                     role="progressbar" aria-valuenow="{{ $plan['progress'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Payments made">
                                    <span style="width: {{ $plan['progress'] }}%"></span>
                                </div>
                            @endif

                            <dl class="kv" style="margin:20px 0">
                                <div><dt>Cash price</dt><dd>@rs($order->total_deal_price)</dd></div>
                                <div>
                                    <dt>Advance</dt>
                                    <dd>@rs($order->advance_price)@if (! $order->advance_price)<span class="sub">AtomPay takes none</span>@endif</dd>
                                </div>
                                <div><dt>On installments</dt><dd>@rs($order->financed_amount)</dd></div>
                                <div><dt>Term</dt><dd>{{ $order->instalment_tenure ? $order->instalment_tenure.' months' : '—' }}</dd></div>
                            </dl>

                            @if ($plan['total_count'] > 0)
                                <div class="table-box">
                                    <table class="t">
                                        <caption class="sr-only">Payment schedule for order {{ $order->reference }}</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">Payment</th>
                                                <th scope="col">Due date</th>
                                                <th scope="col" style="text-align:right">Amount</th>
                                                <th scope="col">Paid on</th>
                                                <th scope="col">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($plan['instalments'] as $i => $row)
                                                <tr>
                                                    <td>
                                                        <b>{{ $i + 1 }} of {{ $plan['total_count'] }}</b>
                                                        <span class="sub">{{ $row->month }}</span>
                                                    </td>
                                                    <td>{{ $row->installment_date?->format('d M Y') ?? '—' }}</td>
                                                    <td class="money" style="text-align:right">@rs($row->installment_price)</td>
                                                    <td>{{ $row->installment_paid_date?->format('d M Y') ?? '—' }}</td>
                                                    <td><span class="badge {{ $rowTones[$row->state] ?? 'b-grey' }}">{{ $rowLabels[$row->state] ?? $row->state }}</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="2">Total</td>
                                                <td style="text-align:right">@rs($plan['total_amount'])</td>
                                                <td colspan="2">{{ $outstanding > 0 ? \App\Support\Money::format($outstanding, 'Rs. ').' outstanding' : 'Cleared' }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>

            <div class="card" id="order-empty" hidden style="text-align:center">
                <p class="body">No orders in this group.</p>
            </div>

            <p class="body" style="margin-top:16px">
                Payments are recorded by the seller who delivered your order, or by Atomshop. If a payment you have made is not showing after two days, message us on WhatsApp.
            </p>
        @endif
    </div>
@endsection
