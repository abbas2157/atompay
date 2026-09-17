@php use App\Models\Enums\AssessmentStatus; @endphp
<x-layouts.account title="Review queue" :wide="true">
    <div class="flex justify-between items-end flex-wrap gap-4 mb-6">
        <div>
            <span class="eyebrow">AtomPay staff</span>
            <h1 class="mt-2 text-[clamp(26px,3.5vw,34px)]">Applications</h1>
        </div>
        <nav class="flex gap-2 flex-wrap" aria-label="Filter by status">
            @foreach (AssessmentStatus::cases() as $case)
                <a href="{{ route('staff.assessments.index', ['status' => $case->value]) }}"
                   class="btn btn-sm {{ $status === $case ? 'btn-primary' : 'btn-ghost' }}">
                    {{ $case->label() }} <span class="opacity-60">{{ $counts[$case->value] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <div class="card overflow-x-auto">
        <table class="sched w-full border-collapse text-[13.8px] min-w-[760px]">
            <thead><tr><th>#</th><th>Customer</th><th>Submitted</th><th>KYC</th><th>Income</th><th>Provisional limit</th><th>Risk</th><th></th></tr></thead>
            <tbody>
                @forelse ($assessments as $a)
                    <tr>
                        <td class="font-mono text-muted">{{ $a->id }}</td>
                        <td>
                            <span class="font-semibold">{{ $a->user?->name }}</span>
                            <span class="block font-mono text-[11px] text-muted mt-0.5">{{ $a->user?->phone }}</span>
                        </td>
                        <td>{{ $a->created_at->format('d M Y') }}</td>
                        <td>{{ $a->user?->kycProfile?->verification_status->label() ?? 'None' }}</td>
                        <td>@pkr($a->monthly_income)</td>
                        <td>@pkr($a->approved_limit)</td>
                        <td><span class="dot dot-{{ ['low' => 'paid', 'medium' => 'due', 'high' => 'late'][$a->risk_category->value] }}"></span>{{ $a->risk_score }} &middot; {{ $a->risk_category->label() }}</td>
                        <td class="text-right"><a href="{{ route('staff.assessments.show', $a) }}" class="btn btn-ghost btn-sm">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-10">No {{ strtolower($status->label()) }} applications.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $assessments->links() }}</div>
</x-layouts.account>
