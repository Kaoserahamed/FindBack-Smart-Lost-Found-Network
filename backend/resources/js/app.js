//

// ============================================================================
// Alpine.js Components
// ============================================================================

document.addEventListener('alpine:init', () => {
    
    // ------------------------------------------------------------------------
    // Map Picker Component
    // ------------------------------------------------------------------------
    window.Alpine.data('mapPicker', () => ({
        map: null,
        marker: null,

        init() {
            const lat = parseFloat(this.$el.dataset.lat);
            const lng = parseFloat(this.$el.dataset.lng);
            const hasCoords = !isNaN(lat) && !isNaN(lng);

            const startLat = hasCoords ? lat : 24.9186; // default: SUST, Sylhet
            const startLng = hasCoords ? lng : 91.8315;

            this.map = L.map(this.$refs.map, { scrollWheelZoom: false })
                .setView([startLat, startLng], hasCoords ? 16 : 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);

            if (hasCoords) {
                this.marker = L.marker([startLat, startLng], { draggable: true }).addTo(this.map);
                this.bindMarkerDrag();
            }

            // Click anywhere to drop/move the pin and sync to Livewire.
            this.map.on('click', (e) => this.setMarkerAt(e.latlng.lat, e.latlng.lng));

            // Leaflet needs a nudge to size correctly once the container is visible.
            setTimeout(() => this.map.invalidateSize(), 150);
        },

        setMarkerAt(lat, lng) {
            if (this.marker) {
                this.marker.setLatLng([lat, lng]);
            } else {
                this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                this.bindMarkerDrag();
            }

            this.$wire.set('latitude', lat.toFixed(7));
            this.$wire.set('longitude', lng.toFixed(7));
        },

        bindMarkerDrag() {
            this.marker.on('dragend', () => {
                const ll = this.marker.getLatLng();
                this.$wire.set('latitude', ll.lat.toFixed(7));
                this.$wire.set('longitude', ll.lng.toFixed(7));
            });
        },

        clear() {
            if (this.marker) {
                this.map.removeLayer(this.marker);
                this.marker = null;
            }

            this.$wire.set('latitude', null);
            this.$wire.set('longitude', null);
        },
    }));
    
    // ------------------------------------------------------------------------
    // Toast Notification Component
    // ------------------------------------------------------------------------
    window.Alpine.data('toastManager', () => ({
        toasts: [],
        nextId: 1,
        
        init() {
            // Listen for custom toast events
            window.addEventListener('toast', (event) => {
                this.show(event.detail);
            });
            
            // Listen for Livewire events
            if (window.Livewire) {
                Livewire.on('toast', (data) => {
                    this.show(data);
                });
            }
        },
        
        show(options) {
            const toast = {
                id: this.nextId++,
                message: options.message || options,
                type: options.type || 'info',
                duration: options.duration || 5000,
            };
            
            this.toasts.push(toast);
            
            if (toast.duration > 0) {
                setTimeout(() => this.remove(toast.id), toast.duration);
            }
        },
        
        remove(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) {
                toast.removing = true;
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 300);
            }
        },
    }));
    
    // ------------------------------------------------------------------------
    // Mobile Menu Component
    // ------------------------------------------------------------------------
    window.Alpine.data('mobileMenu', () => ({
        open: false,
        
        toggle() {
            this.open = !this.open;
            // Prevent body scroll when menu is open
            document.body.style.overflow = this.open ? 'hidden' : '';
        },
        
        close() {
            this.open = false;
            document.body.style.overflow = '';
        },
    }));
    
    // ------------------------------------------------------------------------
    // Image Gallery Component
    // ------------------------------------------------------------------------
    window.Alpine.data('imageGallery', (images = []) => ({
        images: images,
        currentIndex: 0,
        isOpen: false,
        
        open(index = 0) {
            this.currentIndex = index;
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        close() {
            this.isOpen = false;
            document.body.style.overflow = '';
        },
        
        next() {
            this.currentIndex = (this.currentIndex + 1) % this.images.length;
        },
        
        prev() {
            this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
        },
        
        get currentImage() {
            return this.images[this.currentIndex];
        },
    }));
    
    // ------------------------------------------------------------------------
    // Modal Component
    // ------------------------------------------------------------------------
    window.Alpine.data('modal', (isOpen = false) => ({
        open: isOpen,
        
        show() {
            this.open = true;
            document.body.style.overflow = 'hidden';
        },
        
        hide() {
            this.open = false;
            document.body.style.overflow = '';
        },
        
        toggle() {
            this.open ? this.hide() : this.show();
        },
    }));
    
    // ------------------------------------------------------------------------
    // Dropdown Component
    // ------------------------------------------------------------------------
    window.Alpine.data('dropdown', () => ({
        open: false,
        
        toggle() {
            this.open = !this.open;
        },
        
        close() {
            this.open = false;
        },
    }));
    
    // ------------------------------------------------------------------------
    // Copy to Clipboard Component
    // ------------------------------------------------------------------------
    window.Alpine.data('copyToClipboard', () => ({
        copied: false,
        
        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        },
    }));
});

// ============================================================================
// Global Helper Functions
// ============================================================================

// Dispatch toast notification
window.showToast = function(message, type = 'info', duration = 5000) {
    window.dispatchEvent(new CustomEvent('toast', {
        detail: { message, type, duration }
    }));
};

// Format date helper
window.formatDate = function(date, format = 'short') {
    const d = new Date(date);
    if (format === 'relative') {
        return new Intl.RelativeTimeFormat('en', { numeric: 'auto' }).format(
            Math.round((d - new Date()) / (1000 * 60 * 60 * 24)),
            'day'
        );
    }
    return new Intl.DateTimeFormat('en-US', {
        dateStyle: format === 'long' ? 'long' : 'medium',
    }).format(d);
};

// ============================================================================
// Livewire Hooks
// ============================================================================

// Show loading indicator for Livewire requests
document.addEventListener('livewire:init', () => {
    Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
        // Show a subtle loading indicator
        document.body.style.cursor = 'wait';
        
        succeed(() => {
            document.body.style.cursor = '';
        });
        
        fail(() => {
            document.body.style.cursor = '';
        });
    });
});

