@props(['type' => 'ok'])
@php $tone = ['ok' => 'bg-ok/10 border-ok/30 text-[#0d4c33]', 'error' => 'bg-coral/10 border-coral/30 text-[#7a1f2a]', 'warn' => 'bg-amber/10 border-amber/30 text-[#6b4300]'][$type]; @endphp
<div {{ $attributes->merge(['class' => "rounded-2xl border px-5 py-4 text-[14.5px] {$tone}", 'role' => 'status']) }}>{{ $slot }}</div>
