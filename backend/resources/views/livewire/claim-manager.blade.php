<div class="mx-auto max-w-4xl space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ ucfirst($report->type) }} Report</h1>
        <p class="mt-1 text-sm text-gray-500">
            <span class="font-medium text-gray-700">{{ $report->title }}</span> ·
            {{ $report->community?->name }} · {{ $report->category?->name }}
        </p>
    </div>

    {{-- Submit a claim (only for found reports, eligible users) --}}
    @if ($canSubmit)
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Claim this item</h2>
            <p class="mt-1 text-sm text-gray-500">Answer the private verification questions. Your answers are only visible to reviewers.</p>

            <form wire:submit="submitClaim" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Verification answers</label>
                    <p class="text-xs text-gray-400">Add details only the true owner would know (e.g. serial number, unique marks).</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($answers as $i => $answer)
                            <div class="flex gap-2">
                                <input type="text" wire:model.blur="answers.{{ $i }}" placeholder="Answer {{ $loop->iteration }}"
                                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="button" wire:click="$set('answers.{{ $i }}', '')"
                                        class="text-sm text-gray-400 hover:text-red-600">Clear</button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="$set('answers.{{ count($answers) }}', '')"
                            class="mt-2 text-sm font-medium text-indigo-600 hover:text-indigo-500">+ Add another answer</button>
                    @error('answers') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('answers.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Evidence <span class="text-gray-400">(optional)</span></label>
                    <input type="file" wire:model="evidence" accept="image/jpeg,image/png,image/webp,application/pdf"
                           class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="evidence" class="mt-1 text-xs text-gray-500">Uploading…</div>
                    @error('evidence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60">
                    Submit Claim
                </button>
            </form>
        </div>
    @endif

    {{-- Review panel (reporters / moderators) --}}
    @if ($claims->count())
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Claims to review</h2>
            @foreach ($claims as $claim)
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">By {{ $claim->claimant?->name ?? 'User' }}</span>
                        <span @class([
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-yellow-100 text-yellow-700' => $claim->status === 'submitted',
                            'bg-green-100 text-green-700' => $claim->status === 'approved',
                            'bg-red-100 text-red-700' => $claim->status === 'rejected',
                            'bg-gray-100 text-gray-600' => in_array($claim->status, ['completed', 'under_review']),
                        ])>{{ ucfirst(str_replace('_', ' ', $claim->status)) }}</span>
                    </div>

                    <div class="mt-3 text-sm text-gray-600">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Private answers</p>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach (($claim->answers ?? []) as $answer)
                                <li>{{ $answer }}</li>
                            @endforeach
                        </ul>
                    </div>

                    @if ($claim->status === 'submitted')
                        <div class="mt-4 border-t border-gray-100 pt-4">
                            <label class="block text-xs font-medium text-gray-600">Decision reason</label>
                            <input type="text" wire:model.blur="reviewReason.{{ $claim->id }}" placeholder="Why approve/reject…"
                                   class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('reviewReason.' . $claim->id) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="mt-3 flex gap-2">
                                <button type="button" wire:click="review({{ $claim->id }}, 'approved')"
                                        class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-green-500">Approve</button>
                                <button type="button" wire:click="review({{ $claim->id }}, 'rejected')"
                                        class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-500">Reject</button>
                            </div>
                        </div>
                    @elseif ($claim->decision_reason)
                        <p class="mt-3 text-xs text-gray-500">Reason: {{ $claim->decision_reason }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>