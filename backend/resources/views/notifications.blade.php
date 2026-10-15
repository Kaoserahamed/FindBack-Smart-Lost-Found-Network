<x-layout>
    <div class="space-y-4">
        <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>

        @php($items = auth()->user()->notifications()->paginate(15))

        @if ($items->count())
            <ul class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                @foreach ($items as $notification)
                    @php($data = $notification->data)
                    <li @class(['px-4 py-3', 'bg-indigo-50/40' => is_null($notification->read_at)])>
                        <p class="text-sm text-gray-800">{{ $data['summary'] ?? 'You have a new notification.' }}</p>
                        @if (!empty($data['report_uuid']) && !empty($data['report_title']))
                            <p class="mt-0.5 text-xs text-gray-500">Related: {{ $data['report_title'] }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </li>
                @endforeach
            </ul>
            <div>{{ $items->links() }}</div>
        @else
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center">
                <p class="text-sm text-gray-500">No notifications yet.</p>
            </div>
        @endif
    </div>
</x-layout>