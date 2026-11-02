@props(['tone' => 'gray', 'plain' => false])
<span {{ $attributes->class(['badge', 'tone-'.$tone, 'plain' => $plain]) }}>{{ $slot }}</span>
