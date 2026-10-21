<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEcho } from '@/Composables/useEcho';

import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
L.Icon.Default.mergeOptions({ iconRetinaUrl, iconUrl, shadowUrl });

const props = defineProps({
    drivers_location: {
        type: Array,
        default: () => []
    }
});

const { getEcho, connectionState } = useEcho();
let driverLocationsChannel = null;

const driversList = ref([...props.drivers_location]);

watch(() => props.drivers_location, (newVal) => {
    driversList.value = [...newVal];
    renderMarkers(false);
}, { deep: true });

let mapInstance = null;
let locationInterval = null;
let streetLayer = null;
let satelliteLayer = null;

const isMapFullscreen = ref(false);
const isSatelliteView = ref(false);
const lastRefresh = ref(new Date());

const selectedDriverId = ref(null);
const currentZoomIndex = ref(0);
const zoomCycle = [7, 9, 12, 15, 18, 15, 12, 9, 7]; // Maju bertahap lalu mundur bertahap

const handleDriverClick = (driverId, lat, lng) => {
    if (!mapInstance || !lat || !lng) return;
    
    if (selectedDriverId.value === driverId) {
        currentZoomIndex.value++;
        if (currentZoomIndex.value >= zoomCycle.length) {
            // Tingkatan ke-6: Reset ke seluruh Indonesia (Zoom 5)
            selectedDriverId.value = null;
            mapInstance.flyTo([-2.5489, 118.0149], 5, { animate: true, duration: 1.5 });
        } else {
            // Lanjut zoom mendekat
            mapInstance.flyTo([lat, lng], zoomCycle[currentZoomIndex.value], { animate: true, duration: 1.5 });
        }
    } else {
        // Klik pertama kali: Mulai dari zoom Pulau (7)
        selectedDriverId.value = driverId;
        currentZoomIndex.value = 0;
        mapInstance.flyTo([lat, lng], zoomCycle[0], { animate: true, duration: 1.5 });
    }
    renderMarkers(false);
};

const trackingDistance = ref(10);
const isLoadingDistance = ref(true);

const fetchTrackingDistance = async () => {
    try {
        const res = await axios.get('/api/settings/tracking-distance');
        if (res.data && res.data.distance) {
            trackingDistance.value = parseInt(res.data.distance);
        }
    } catch (error) {
        console.error('Gagal mengambil pengaturan jarak GPS', error);
    } finally {
        isLoadingDistance.value = false;
    }
};

const saveTrackingDistance = async () => {
    try {
        await axios.post('/api/settings/tracking-distance', { distance: trackingDistance.value });
        alert('Pengaturan jarak berhasil disimpan!');
    } catch (error) {
        console.error('Gagal menyimpan pengaturan jarak GPS', error);
        alert('Gagal menyimpan pengaturan GPS');
    }
};

const activeDrivers = () => driversList.value.filter(d => d.last_lat && d.last_lng);

const toggleSatellite = () => {
    if (!mapInstance) return;
    isSatelliteView.value = !isSatelliteView.value;
    if (isSatelliteView.value) {
        streetLayer.remove();
        satelliteLayer.addTo(mapInstance);
    } else {
        satelliteLayer.remove();
        streetLayer.addTo(mapInstance);
    }
};

const toggleMapFullscreen = async () => {
    isMapFullscreen.value = !isMapFullscreen.value;
    await nextTick();
    if (mapInstance) mapInstance.invalidateSize();
};

const refreshLocation = () => {
    router.reload({ only: ['drivers_location'], preserveScroll: true, preserveState: true });
    lastRefresh.value = new Date();
};

const renderMarkers = (forceFitBounds = false) => {
    if (!mapInstance) return;
    mapInstance.eachLayer((layer) => {
        if (layer instanceof L.Marker) mapInstance.removeLayer(layer);
    });
    const active = activeDrivers();
    active.forEach(driver => {
        const isSelected = selectedDriverId.value === driver.id;
        const bgColor = isSelected ? '#3b82f6' : '#10b981'; // Biru jika dipilih, hijau jika normal
        const zIndex = isSelected ? 1000 : 0;
        
        // Buat custom icon truk menggunakan HTML
        const customIcon = L.divIcon({
            html: `
                <div style="background-color: ${bgColor}; width: 40px; height: 40px; border-radius: 50%; border: 3px solid white; display: flex; align-items: center; justify-content: center; box-shadow: ${isSelected ? '0 0 0 4px rgba(59,130,246,0.3), ' : ''}0 10px 15px -3px rgba(0, 0, 0, 0.3); transform: scale(${isSelected ? 1.15 : 1}); transition: all 0.3s ease;">
                    <i class="ri-truck-fill" style="color: white; font-size: 20px;"></i>
                </div>
                <div style="background: ${isSelected ? '#1e40af' : 'white'}; color: ${isSelected ? 'white' : '#374151'}; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 900; margin-top: 4px; text-align: center; white-space: nowrap; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); position: absolute; left: 50%; transform: translateX(-50%);">
                    ${driver.name}
                </div>
            `,
            className: '',
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -25]
        });

        const marker = L.marker([driver.last_lat, driver.last_lng], { icon: customIcon, zIndexOffset: zIndex }).addTo(mapInstance);
        const time = driver.last_location_update
            ? new Date(driver.last_location_update).toLocaleString('id-ID')
            : 'Belum update';
        marker.bindPopup(`<b>${driver.name}</b><br>${driver.vehicle_name}<br><span style="font-size:11px;color:#6b7280">Update: ${time}</span>`);
        
        if (isSelected) {
            // Beri jeda sedikit agar map selesai animasi pan sebelum open popup
            setTimeout(() => marker.openPopup(), 400);
        }
    });

    // Fit map to show all markers hanya saat pertama kali (atau force)
    if (forceFitBounds && active.length > 0) {
        const bounds = L.latLngBounds(active.map(d => [d.last_lat, d.last_lng]));
        mapInstance.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
    }
};

onMounted(async () => {
    await fetchTrackingDistance();
    await nextTick();

    if (document.getElementById('driverMap')) {
        // Set view default ke tengah-tengah Indonesia
        mapInstance = L.map('driverMap', { preferCanvas: true }).setView([-2.5489, 118.0149], 5);

        streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
            keepBuffer: 8,
            updateWhenZooming: false,
            updateWhenIdle: true
        });

        satelliteLayer = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                attribution: 'Tiles &copy; Esri',
                maxZoom: 19,
            }
        );

        streetLayer.addTo(mapInstance);
        renderMarkers(false);
    }

    // Subscribe ke WebSocket Echo untuk live GPS tracking
    const echo = getEcho();
    if (echo) {
        driverLocationsChannel = echo.channel('driver-locations');
        driverLocationsChannel.listen('.driver.location.updated', (event) => {
            const found = driversList.value.find(d => d.id == event.driverId);
            if (found) {
                found.last_lat = event.lat;
                found.last_lng = event.lng;
                found.last_location_update = event.timestamp;
            } else {
                driversList.value.push({
                    id: event.driverId,
                    name: event.driverName,
                    vehicle_name: event.vehicleName,
                    last_lat: event.lat,
                    last_lng: event.lng,
                    last_location_update: event.timestamp,
                });
            }
            lastRefresh.value = new Date();
            renderMarkers(false);

            if (selectedDriverId.value == event.driverId && mapInstance) {
                mapInstance.panTo([event.lat, event.lng], { animate: true, duration: 1.0 });
            }
        });
    }

    // Auto refresh tiap 30 detik sebagai cadangan fallback
    locationInterval = setInterval(refreshLocation, 30000);
});

onUnmounted(() => {
    if (locationInterval) clearInterval(locationInterval);
    if (driverLocationsChannel) {
        try {
            getEcho()?.leave('driver-locations');
        } catch (e) {}
    }
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }
});
</script>

<template>
    <AdminLayout title="Peta Lokasi Supir" :noPaddingTop="true">
        <template #header_actions>
            <div class="flex items-center gap-2">
                <!-- Dropdown Pengaturan Jarak (Otomatis Tersimpan) -->
                <div v-if="!isLoadingDistance" class="hidden md:flex items-center gap-1.5 px-2 md:px-3 py-1 rounded-lg border border-green-200 bg-green-50 shadow-sm mr-2" title="Pengaturan seberapa sering GPS supir melapor (jarak minimal)">
                    <i class="ri-radar-line text-green-600 text-sm"></i>
                    <select v-model="trackingDistance" @change="saveTrackingDistance" class="text-[10px] md:text-xs font-bold text-green-700 bg-transparent border-none focus:ring-0 py-0.5 pl-0 pr-4 cursor-pointer">
                        <option :value="10">Rapat (10m)</option>
                        <option :value="50">Normal (50m)</option>
                        <option :value="100">Hemat (100m)</option>
                        <option :value="500">Jauh (500m)</option>
                    </select>
                </div>

                <div v-if="connectionState === 'connected'" class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold mr-1" title="Real-time WebSocket Aktif">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>Live GPS</span>
                </div>

                <span class="text-[10px] md:text-xs text-gray-500 font-medium hidden sm:inline-block mr-2 border-r border-gray-200 pr-3">
                    <b class="text-gray-700">{{ activeDrivers().length }}</b> aktif · {{ lastRefresh.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
                </span>
                
                <button
                    @click="toggleSatellite"
                    class="flex items-center gap-1.5 px-2 md:px-3 py-1.5 rounded-lg text-[10px] md:text-xs font-bold transition-all border shadow-sm active:scale-95"
                    :class="isSatelliteView ? 'bg-blue-50 text-blue-600 border-blue-200' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                >
                    <i :class="isSatelliteView ? 'ri-earth-fill' : 'ri-satellite-fill'" class="text-sm"></i>
                    <span class="hidden sm:inline">{{ isSatelliteView ? 'Peta' : 'Satelit' }}</span>
                </button>

                <button
                    @click="toggleMapFullscreen"
                    class="flex items-center gap-1.5 px-2 md:px-3 py-1.5 rounded-lg text-[10px] md:text-xs font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 transition-all shadow-sm active:scale-95"
                >
                    <i :class="isMapFullscreen ? 'ri-fullscreen-exit-line' : 'ri-fullscreen-line'" class="text-sm"></i>
                    <span class="hidden sm:inline">{{ isMapFullscreen ? 'Keluar' : 'Perbesar' }}</span>
                </button>

                <button
                    @click="refreshLocation"
                    class="flex items-center gap-1.5 px-2 md:px-3 py-1.5 rounded-lg text-[10px] md:text-xs font-bold bg-primary text-white border border-primary hover:bg-primary/90 transition-all shadow-sm active:scale-95 shadow-primary/20"
                >
                    <i class="ri-refresh-line text-sm"></i>
                    <span class="hidden sm:inline">Refresh</span>
                </button>
            </div>
        </template>

        <div class="absolute inset-x-0 bottom-0 top-[48px] md:top-[64px] flex pb-16 lg:pb-0 z-10">
            <!-- Peta + Panel -->
            <div class="flex flex-1 overflow-hidden relative border-t border-gray-200">

                <!-- Peta -->
                <div
                    :class="[
                        isMapFullscreen
                            ? 'fixed inset-0 z-[9999]'
                            : 'flex-1 relative z-0'
                    ]"
                >
                    <div id="driverMap" class="w-full h-full"></div>

                    <!-- Overlay Fullscreen: panel supir horizontal -->
                    <div v-if="isMapFullscreen" class="absolute bottom-4 left-4 right-4 z-[10000] pointer-events-none">
                        <div class="bg-white/95 backdrop-blur-md rounded-2xl p-2.5 md:p-3 shadow-2xl border border-gray-100 pointer-events-auto flex items-center gap-3">
                            <div class="flex flex-col md:flex-row md:items-center gap-1 md:gap-2 pr-3 border-r border-gray-200 shrink-0">
                                <p class="text-[9px] md:text-[10px] font-black text-gray-400 uppercase tracking-widest">Supir Aktif</p>
                                <div class="bg-primary/10 text-primary px-1.5 py-0.5 rounded text-[10px] font-bold text-center w-max">{{ activeDrivers().length }}</div>
                            </div>
                            
                            <!-- Daftar Horizontal Scrollable -->
                            <div class="flex items-center gap-2 overflow-x-auto flex-1 pb-1 pt-1 scroll-smooth" style="scrollbar-width: thin;">
                                <div 
                                    v-for="d in activeDrivers()" 
                                    :key="d.id" 
                                    class="flex items-center gap-1.5 transition-colors px-3 py-1.5 rounded-xl text-xs font-bold border cursor-pointer shrink-0 active:scale-95"
                                    :class="selectedDriverId === d.id ? 'bg-blue-100 border-blue-300 text-blue-800 shadow-inner' : 'bg-gray-50 border-gray-200 hover:bg-primary/10 text-gray-700'"
                                    @click="handleDriverClick(d.id, d.last_lat, d.last_lng)"
                                >
                                    <i class="ri-map-pin-2-fill text-sm" :class="selectedDriverId === d.id ? 'text-blue-600' : 'text-primary'"></i> 
                                    <span>{{ d.name }}</span>
                                </div>
                                <div v-if="activeDrivers().length === 0" class="text-xs text-gray-400 font-medium whitespace-nowrap">
                                    Belum ada supir aktif
                                </div>
                            </div>

                            <!-- Tombol Keluar Fullscreen -->
                            <button @click="toggleMapFullscreen" class="ml-auto shrink-0 bg-gray-900 text-white shadow-lg text-[10px] md:text-xs font-bold px-3 md:px-4 py-2 md:py-2.5 rounded-xl hover:bg-gray-800 active:scale-95 transition-all flex items-center gap-1.5">
                                <i class="ri-fullscreen-exit-line text-sm md:text-base"></i> <span class="hidden md:inline">Keluar Layar Penuh</span><span class="md:hidden">Keluar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Panel Kanan: Daftar Supir -->
                <div class="w-72 shrink-0 bg-white border-l border-gray-100 overflow-y-auto hidden lg:block">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-xs font-black text-gray-500 uppercase tracking-widest">Daftar Supir ({{ props.drivers_location.length }})</h3>
                    </div>

                    <div class="p-3 space-y-2">
                        <div
                            v-for="driver in props.drivers_location"
                            :key="driver.id"
                            class="p-3 rounded-xl border transition-all cursor-pointer hover:shadow-md"
                            :class="[
                                selectedDriverId === driver.id 
                                    ? 'bg-blue-50 border-blue-400 shadow-md ring-2 ring-blue-100' 
                                    : (driver.last_lat ? 'bg-green-50/60 border-green-100' : 'bg-gray-50 border-gray-100 opacity-60')
                            ]"
                            @click="handleDriverClick(driver.id, driver.last_lat, driver.last_lng)"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 text-lg transition-colors"
                                     :class="selectedDriverId === driver.id ? 'bg-blue-600 text-white shadow-lg' : 'bg-primary/10 text-primary'">
                                    <i class="ri-steering-2-fill"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-gray-800 text-sm truncate">{{ driver.name }}</p>
                                    <p class="text-xs text-gray-500 font-medium truncate">{{ driver.vehicle_name }}</p>
                                    <div class="flex items-center gap-1 mt-1">
                                        <span class="w-1.5 h-1.5 rounded-full" :class="driver.last_lat ? 'bg-green-400' : 'bg-gray-300'"></span>
                                        <span class="text-[10px] font-bold" :class="driver.last_lat ? 'text-green-600' : 'text-gray-400'">
                                            {{ driver.last_lat ? 'GPS Aktif' : 'Offline' }}
                                        </span>
                                    </div>
                                    <p v-if="driver.last_location_update" class="text-[10px] text-gray-400 mt-0.5">
                                        {{ new Date(driver.last_location_update).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-if="props.drivers_location.length === 0" class="text-center py-10">
                            <i class="ri-map-pin-user-line text-3xl text-gray-300"></i>
                            <p class="text-xs text-gray-400 mt-2 font-medium">Belum ada supir terdaftar</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
