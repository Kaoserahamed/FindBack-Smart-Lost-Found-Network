<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'FindBack') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <div class="flex min-h-screen flex-col">
        <nav class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-indigo-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.85-5.4a7.25 7.25 0 11-14.5 0 7.25 7.25 0 0114.5 0z" />
                    </svg>
                    FindBack
                </a>

                <div class="hidden items-center gap-1 sm:flex">
                    <x-nav-link :href="route('home')" :active="request()->routeIs('home')">Home</x-nav-link>
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">Discover</x-nav-link>
                    @auth
                        <x-nav-link :href="route('reports.create')" :active="request()->routeIs('reports.create')">Create Report</x-nav-link>
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>
                        <x-nav-link :href="route('notifications')" :active="request()->routeIs('notifications')">
                            <span class="flex items-center gap-1">
                                Notifications
                                @php($unread = auth()->user()->unreadNotifications()->count())
                                @if ($unread > 0)
                                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-xs font-semibold text-white">
                                        {{ $unread > 9 ? '9+' : $unread }}
                                    </span>
                                @endif
                            </span>
                        </x-nav-link>
                    @endauth
                </div>

                <div class="flex items-center gap-3">
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900">
                                Log out
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900">Log in</a>
                    @endauth
                </div>
            </div>
        </nav>

        @if (session('success'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="alert">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mx-auto mt-4 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        <footer class="border-t border-gray-200 bg-white py-4">
            <div class="mx-auto max-w-7xl px-4 text-center text-sm text-gray-500 sm:px-6 lg:px-8">
                FindBack — Smart Lost &amp; Found Network
            </div>
        </footer>
    </div>

    @livewireScripts
</body>
</html>
