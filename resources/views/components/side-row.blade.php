@props(['label'])
<div class="flex justify-between text-[14px] py-2.5 border-b border-line2 last:border-b-0">
    <span class="text-muted">{{ $label }}</span>
    <b class="font-semibold text-right">{{ $slot }}</b>
</div>
