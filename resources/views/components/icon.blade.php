@props(['name' => 'info', 'size' => 17, 'stroke' => 1.9])

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round"
     {{ $attributes }}>{!! \App\Support\Icon::path($name) !!}</svg>
