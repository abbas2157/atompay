{{-- Summary of one step on the review page. $rows: label => Alpine expression. --}}
<div class="rounded-2xl border border-line overflow-hidden">
    <div class="flex items-center justify-between px-5 py-3 bg-paper border-b border-line">
        <b class="text-[14px]">{{ $title }}</b>
        <button type="button" class="font-mono text-[11px] tracking-[.06em] uppercase text-violet hover:underline" @click="go({{ $step }})">Edit</button>
    </div>
    <dl class="px-5 py-2 grid sm:grid-cols-2 gap-x-6">
        @foreach ($rows as $label => $expr)
            <div class="flex justify-between gap-4 text-[13.5px] py-2 border-b border-line2 last:border-b-0 sm:[&:nth-last-child(2)]:border-b-0">
                <dt class="text-muted shrink-0">{{ $label }}</dt>
                <dd class="font-medium text-right truncate" x-text="{{ $expr }}">—</dd>
            </div>
        @endforeach
    </dl>
</div>
