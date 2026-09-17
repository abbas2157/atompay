{{-- One order's plan. Variables come from PaymentScheduleService::plan(). --}}
@php $labels = ['paid' => 'Paid', 'late' => 'Late', 'due' => 'Due soon', 'upcoming' => 'Upcoming']; @endphp
<article class="card px-6 py-6 mb-4">
    <div class="flex justify-between items-start gap-4 flex-wrap">
        <div>
            <h3 class="text-[17px]">{{ $product?->title ?? 'AtomShop order' }}</h3>
            <p class="font-mono text-[11.5px] text-muted mt-1">
                AtomShop order #{{ $order->reference }} &middot; Ordered {{ $order->created_at?->format('d M Y') }}
                &middot; {{ $order->status->label() }}
            </p>
        </div>
        @if ($has_late)
            <x-status-pill state="late">Payment overdue</x-status-pill>
        @elseif ($paid_count === $total_count && $total_count > 0)
            <x-status-pill state="ok">Completed</x-status-pill>
        @else
            <x-status-pill state="ok">On track</x-status-pill>
        @endif
    </div>

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
</article>
