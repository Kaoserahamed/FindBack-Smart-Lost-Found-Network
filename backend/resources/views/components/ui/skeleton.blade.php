@props([
    'type' => 'text',
    'lines' => 1,
])

@if($type === 'text')
    @for($i = 0; $i < $lines; $i++)
        <div class="skeleton h-4 rounded {{ $i < $lines - 1 ? 'mb-2' : '' }} {{ $i === $lines - 1 ? 'w-3/4' : 'w-full' }}"></div>
    @endfor
@elseif($type === 'title')
    <div class="skeleton h-8 w-3/4 rounded"></div>
@elseif($type === 'avatar')
    <div class="skeleton h-12 w-12 rounded-full"></div>
@elseif($type === 'image')
    <div class="skeleton aspect-video w-full rounded-lg"></div>
@elseif($type === 'card')
    <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-6">
        <div class="skeleton h-6 w-3/4 rounded"></div>
        <div class="skeleton h-4 w-full rounded"></div>
        <div class="skeleton h-4 w-5/6 rounded"></div>
        <div class="flex gap-2">
            <div class="skeleton h-8 w-20 rounded"></div>
            <div class="skeleton h-8 w-20 rounded"></div>
        </div>
    </div>
@endif
