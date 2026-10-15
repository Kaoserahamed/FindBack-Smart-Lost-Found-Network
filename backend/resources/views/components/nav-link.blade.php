@props(['href', 'active' => false])

<a href="{{ $href }}"
   @class([
       'rounded-md px-3 py-2 text-sm font-medium transition',
       'bg-indigo-50 text-indigo-700' => $active,
       'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $active,
   ])>
    {{ $slot }}
</a>
