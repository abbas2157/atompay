@props(['state' => 'ok'])
@php $tone = ['ok' => 'bg-ok/12 text-[#0d4c33]', 'late' => 'bg-coral/12 text-[#7a1f2a]', 'pending' => 'bg-amber/15 text-[#6b4300]'][$state]; @endphp
<span {{ $attributes->merge(['class' => "font-mono text-[10.5px] font-bold tracking-[.07em] uppercase px-3 py-1.5 rounded-full whitespace-nowrap {$tone}"]) }}>{{ $slot }}</span>
