<!-- Toast Container -->
<div 
    x-data="toastManager" 
    class="toast-container"
    aria-live="polite"
    aria-atomic="true"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div 
            x-show="!toast.removing"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="transform translate-x-full opacity-0"
            x-transition:enter-end="transform translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="transform translate-x-0 opacity-100"
            x-transition:leave-end="transform translate-x-full opacity-0"
            class="toast flex items-center gap-3 rounded-lg border p-4 shadow-lg"
            :class="{
                'bg-white border-gray-200': toast.type === 'info',
                'bg-green-50 border-green-200': toast.type === 'success',
                'bg-red-50 border-red-200': toast.type === 'error',
                'bg-yellow-50 border-yellow-200': toast.type === 'warning',
            }"
            role="alert"
        >
            <!-- Icon -->
            <div class="flex-shrink-0">
                <template x-if="toast.type === 'success'">
                    <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>
                <template x-if="toast.type === 'error'">
                    <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>
            </div>
            
            <!-- Message -->
            <div 
                class="flex-1 text-sm font-medium"
                :class="{
                    'text-gray-900': toast.type === 'info',
                    'text-green-900': toast.type === 'success',
                    'text-red-900': toast.type === 'error',
                    'text-yellow-900': toast.type === 'warning',
                }"
                x-text="toast.message"
            ></div>
            
            <!-- Close button -->
            <button 
                @click="remove(toast.id)" 
                class="flex-shrink-0 rounded-md p-1 transition-colors hover:bg-gray-200/50 focus:outline-none focus:ring-2 focus:ring-gray-400"
                :class="{
                    'hover:bg-gray-200': toast.type === 'info',
                    'hover:bg-green-200': toast.type === 'success',
                    'hover:bg-red-200': toast.type === 'error',
                    'hover:bg-yellow-200': toast.type === 'warning',
                }"
                aria-label="Close"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>
