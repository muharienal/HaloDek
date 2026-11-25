@props(['report'])
{{-- Prioritas = teks berwarna dengan penanda level (1–4 batang), bukan kapsul, agar tidak mirip tombol. --}}
@php $level = array_search($report->priority, array_keys(config('halodek.priorities')), true); $level = $level === false ? 1 : $level + 1; @endphp
<span {{ $attributes->class(['prio', 'txt-'.$report->priorityTone()]) }} title="Prioritas {{ $report->priorityLabel() }}"><span class="prio-bars" data-level="{{ $level }}" aria-hidden="true"><i></i><i></i><i></i><i></i></span>{{ $report->priorityLabel() }}</span>
