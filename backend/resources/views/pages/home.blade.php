<x-layout>
    <div class="space-y-8">
        <section class="rounded-lg bg-indigo-600 px-6 py-12 text-center text-white shadow-sm">
            <h1 class="text-3xl font-bold sm:text-4xl">Find what you lost. Return what you found.</h1>
            <p class="mx-auto mt-3 max-w-2xl text-indigo-100">FindBack connects people within a community to reunite lost items with their owners through smart matching and verified handovers.</p>
            <div class="mt-6 flex justify-center gap-3">
                <a href="{{ route('reports.index') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow hover:bg-indigo-50">Browse reports</a>
                @guest
                    <a href="{{ route('login') }}" class="rounded-md border border-white/70 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">Log in</a>
                @elseguest
                    <a href="{{ route('reports.create') }}" class="rounded-md border border-white/70 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">Create a report</a>
                @endguest
            </div>
        </section>

        <livewire:report-list />
    </div>
</x-layout>