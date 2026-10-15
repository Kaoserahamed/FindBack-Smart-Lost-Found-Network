//

// Register the Leaflet-backed map picker used by the ReportCreate form.
// Loaded on the `alpine:init` event so it is available when Alpine starts
// (Alpine is bundled with Livewire and initialises later in the page).
document.addEventListener('alpine:init', () => {
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
});

