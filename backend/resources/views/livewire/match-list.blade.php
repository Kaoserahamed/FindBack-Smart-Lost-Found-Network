<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Potential Matches</h1>
        <p class="mt-1 text-sm text-gray-500">
            For your {{ ucfirst($report->type) }} report: <span class="font-medium text-gray-700">{{ $report->title }}</span>
        </p>
    </div>

    @if ($matches->count())
        <div class="space-y-4">
            @foreach ($matches as $match)
                @php($other = $match->lost_report_id === $report->id ? $match->foundReport : $match->lostReport)
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ $other?->title ?? 'Report' }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($other?->description ?? '', 140) }}</p>
                        </div>
                        <div class="ml-4 flex flex-col items-center">
                            <span @class([
                                'inline-flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold',
                                'bg-green-100 text-green-700' => $match->score >= 70,
                                'bg-yellow-100 text-yellow-700' => $match->score >= 40 && $match->score < 70,
                                'bg-gray-100 text-gray-600' => $match->score < 40,
                            ])>{{ $match->score }}</span>
                            <span class="mt-1 text-xs text-gray-400">/ 100</span>
                        </div>
                    </div>

                    <div class="mt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Why this matched</p>
                        <ul class="mt-1 flex flex-wrap gap-2">
                            @forelse (($match->reasons ?? []) as $reason)
                                <li class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ $reason }}</li>
                            @empty
                                <li class="text-xs text-gray-400">No reasons recorded.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 text-xs text-gray-400">
                        <span>Status: <span class="font-medium text-gray-600">{{ ucfirst($match->status) }}</span></span>
                        <span>Algorithm {{ $match->algorithm_version }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No potential matches yet. We'll notify you when one is found.</p>
        </div>
    @endif
</div>
