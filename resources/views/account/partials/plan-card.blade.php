{{-- One order's plan. Variables come from PaymentScheduleService::plan(). --}}
@php
    $labels = ['paid' => 'Paid', 'late' => 'Late', 'due' => 'Due soon', 'upcoming' => 'Upcoming'];
    [$pillTone, $pillText] = match ($state) {
        'late'       => ['late', 'Payment overdue'],
        'completed'  => ['ok', 'Completed'],
        'on_track'   => ['ok', 'On track'],
        'cancelled'  => ['muted', 'Cancelled'],
        'pending'    => ['pending', $order->status === \App\Models\Enums\OrderStatus::Verification ? 'In verification' : 'Awaiting approval'],
        default      => ['pending', $order->status->label()],   // processing: approved, schedule not set yet
    };
    // Orders without a schedule yet say what happens next instead of showing an empty table.
    $note = match ($state) {
        'pending'    => 'AtomShop is reviewing this order. Your instalment schedule appears here once it is approved and delivered.',
        'processing' => $order->status === \App\Models\Enums\OrderStatus::Delivered
            ? 'Delivered. Your instalment schedule will appear here shortly.'
            : 'Approved and being prepared. Your instalment schedule starts once it is delivered.',
        'cancelled'  => 'This order was cancelled.',
        default      => null,
    };
@endphp
<article class="card px-6 py-6 mb-4">
    <div class="flex justify-between items-start gap-4 flex-wrap">
        <div>
            <h3 class="text-[17px]">{{ $product?->title ?? 'AtomShop order' }}</h3>
            <p class="font-mono text-[11.5px] text-muted mt-1">
                AtomShop order #{{ $order->reference }} &middot; Ordered {{ $order->created_at?->format('d M Y') }}
                &middot; {{ $order->status->label() }}
            </p>
        </div>
        <x-status-pill :state="$pillTone">{{ $pillText }}</x-status-pill>
    </div>

    @if ($total_count === 0)
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-[13.5px]">
            <div><span class="block text-muted text-[12px]">Price</span>@pkr($order->total_deal_price)</div>
            <div><span class="block text-muted text-[12px]">Advance</span>@pkr($order->advance_price)</div>
            <div><span class="block text-muted text-[12px]">On instalments</span>@pkr($order->financed_amount)</div>
            <div><span class="block text-muted text-[12px]">Tenure</span>{{ $order->instalment_tenure ? $order->instalment_tenure.' months' : '—' }}</div>
        </div>
        @if ($note)
            <p class="mt-4 rounded-xl bg-paper border border-line px-4 py-3 text-[13.5px] text-muted">{{ $note }}</p>
        @endif
    @else

    <div class="mt-4">
        <div class="h-1.5 rounded-md bg-line overflow-hidden" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Instalments paid">
            <div class="h-full rounded-md bg-royal" style="width: {{ $progress }}%"></div>
        </div>
        <div class="flex justify-between text-[12.5px] text-muted mt-2">
            <span>{{ $paid_count }} of {{ $total_count }} instalments paid</span>
            <span>@pkr($paid_amount) of @pkr($total_amount) paid</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="sched w-full border-collapse mt-2 text-[13.8px] min-w-[560px]">
            <thead><tr><th>Instalment</th><th>Due date</th><th>Amount</th><th>Paid on</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($instalments as $i => $row)
                    <tr>
                        <td class="font-semibold">{{ $i + 1 }} of {{ $total_count }}<span class="block font-mono text-[11px] text-muted font-normal mt-0.5">{{ $row->month }}</span></td>
                        <td>{{ $row->installment_date?->format('d M Y') ?? '—' }}</td>
                        <td>@pkr($row->installment_price)</td>
                        <td>{{ $row->installment_paid_date?->format('d M Y') ?? '—' }}</td>
                        <td><span class="dot dot-{{ $row->state }}"></span>{{ $labels[$row->state] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</article>
