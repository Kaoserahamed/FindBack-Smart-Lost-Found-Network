@props([
    'interactive' => false,
    'padding' => 'default',
])

@php
    $baseClasses = 'rounded-lg border border-gray-200 bg-white shadow-sm';
    
    if ($interactive) {
        $baseClasses .= ' card-interactive cursor-pointer';
    }
    
    $paddings = [
        'none' => '',
        'sm' => 'p-4',
        'default' => 'p-6',
        'lg' => 'p-8',
    ];
    
    $classes = $baseClasses . ' ' . ($paddings[$padding] ?? $paddings['default']);
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</div>
