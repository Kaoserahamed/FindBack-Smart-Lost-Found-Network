<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="FindBack - Smart Lost & Found Network. Report and recover lost items in your community.">

    <title>{{ $title ?? config('app.name', 'FindBack') }}</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <!-- Skip to main content link -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-md focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to main content
    </a>

    <div class="flex min-h-screen flex-col" x-data="mobileMenu()">
        <!-- Enhanced Navigation -->
        <nav class="sticky top-0 z-40 border-b border-gray-200 bg-white shadow-sm">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <!-- Logo -->
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-indigo-600 transition-colors hover:text-indigo-700">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.85-5.4a7.25 7.25 0 11-14.5 0 7.25 7.25 0 0114.5 0z" />
                    </svg>
                    <span>FindBack</span>
                </a>

                <!-- Desktop Navigation -->
                <div class="hidden items-center gap-1 lg:flex">
                    <x-nav-link :href="route('home')" :active="request()->routeIs('home')">Home</x-nav-link>
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">Discover</x-nav-link>
                    @auth
                        <x-nav-link :href="route('reports.create')" :active="request()->routeIs('reports.create')">Create Report</x-nav-link>
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>
                        <x-nav-link :href="route('notifications')" :active="request()->routeIs('notifications')">
                            <span class="flex items-center gap-1.5">
                                <span>Notifications</span>
                                @php($unread = auth()->user()->unreadNotifications()->count())
                                @if ($unread > 0)
                                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-xs font-semibold text-white ring-2 ring-white">
                                        {{ $unread > 9 ? '9+' : $unread }}
                                    </span>
                                @endif
                            </span>
                        </x-nav-link>
                    @endauth
                </div>

                <!-- Desktop Auth Actions -->
                <div class="hidden items-center gap-3 lg:flex">
                    @auth
                        <div x-data="dropdown()" class="relative">
                            <button 
                                @click="toggle()" 
                                @click.away="close()"
                                class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100"
                                aria-expanded="false"
                                aria-haspopup="true"
                            >
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                                    {{ substr(auth()->user()->name, 0, 1) }}
                                </div>
                                <span class="hidden md:block">{{ auth()->user()->name }}</span>
                                <svg class="h-4 w-4" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            
                            <div 
                                x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                x-cloak
                                class="absolute right-0 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                role="menu"
                            >
                                <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Dashboard</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100" role="menuitem">
                                        Log out
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100">
                            Log in
                        </a>
                    @endauth
                </div>

                <!-- Mobile menu button -->
                <button 
                    @click="toggle()" 
                    class="rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:hidden"
                    aria-label="Toggle menu"
                >
                    <svg x-show="!open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="open" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile Navigation Menu -->
            <div 
                x-show="open" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                x-cloak
                class="border-t border-gray-200 bg-white lg:hidden"
            >
                <div class="space-y-1 px-4 pb-3 pt-2">
                    <a href="{{ route('home') }}" class="block rounded-md px-3 py-2 text-base font-medium {{ request()->routeIs('home') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        Home
                    </a>
                    <a href="{{ route('reports.index') }}" class="block rounded-md px-3 py-2 text-base font-medium {{ request()->routeIs('reports.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        Discover
                    </a>
                    @auth
                        <a href="{{ route('reports.create') }}" class="block rounded-md px-3 py-2 text-base font-medium {{ request()->routeIs('reports.create') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                            Create Report
                        </a>
                        <a href="{{ route('dashboard') }}" class="block rounded-md px-3 py-2 text-base font-medium {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('notifications') }}" class="block rounded-md px-3 py-2 text-base font-medium {{ request()->routeIs('notifications') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100' }}">
                            <span class="flex items-center justify-between">
                                <span>Notifications</span>
                                @php($unread = auth()->user()->unreadNotifications()->count())
                                @if ($unread > 0)
                                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-xs font-semibold text-white">
                                        {{ $unread > 9 ? '9+' : $unread }}
                                    </span>
                                @endif
                            </span>
                        </a>
                        <div class="border-t border-gray-200 pt-2">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-md px-3 py-2 text-left text-base font-medium text-gray-700 hover:bg-gray-100">
                                    Log out
                                </button>
                            </form>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="block rounded-md px-3 py-2 text-base font-medium text-gray-700 hover:bg-gray-100">
                            Log in
                        </a>
                    @endauth
                </div>
            </div>
        </nav>

        <!-- Toast Notifications -->
        <x-ui.toast />

        <!-- Flash Messages converted to toasts -->
        @if (session('success'))
            <div x-data="{ shown: false }" x-init="if (!shown) { shown = true; showToast('{{ session('success') }}', 'success'); }"></div>
        @endif

        @if (session('error'))
            <div x-data="{ shown: false }" x-init="if (!shown) { shown = true; showToast('{{ session('error') }}', 'error'); }"></div>
        @endif

        <!-- Main Content -->
        <main id="main-content" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        <!-- Enhanced Footer -->
        <footer class="border-t border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <p class="text-sm text-gray-500">
                        &copy; {{ date('Y') }} FindBack — Smart Lost &amp; Found Network
                    </p>
                    <div class="flex gap-6 text-sm">
                        <a href="#" class="text-gray-500 transition hover:text-gray-900">About</a>
                        <a href="#" class="text-gray-500 transition hover:text-gray-900">Privacy</a>
                        <a href="#" class="text-gray-500 transition hover:text-gray-900">Terms</a>
                        <a href="#" class="text-gray-500 transition hover:text-gray-900">Contact</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    @livewireScripts
</body>
</html>
