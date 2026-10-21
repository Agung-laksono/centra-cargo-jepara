<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    pairingToken: { type: String, default: null },
    expedition: { type: Object, default: null },
});

const form = useForm({
    name: '',
    phone: '',
    vehicle_name: '',
    role: 'DRIVER_LINTAS',
    password: '',
    password_confirmation: '',
    pairing_token: props.pairingToken || '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('register.driver.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Daftar Supir Baru - Jepara Cargo" />

    <div class="min-h-screen bg-gradient-to-b from-gray-950 via-slate-900 to-gray-900 text-white font-sans flex flex-col justify-center items-center p-4 py-8">
        
        <!-- Ambient Glow -->
        <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[500px] h-[300px] bg-primary/20 rounded-full blur-[100px] pointer-events-none"></div>

        <div class="w-full max-w-md relative z-10 space-y-6">

            <!-- Header -->
            <div class="text-center space-y-2">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary to-primaryDark text-white mx-auto flex items-center justify-center text-2xl shadow-xl shadow-primary/25 border border-white/10">
                    <i class="ri-steering-2-line"></i>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-white">Daftar Supir Baru</h1>
                <p class="text-xs text-gray-400">Pendaftaran Mandiri Personil Armada Cargo Jepara</p>
                
                <!-- Info Kemitraan jika mendaftar dari QR Ekspedisi -->
                <div v-if="expedition" class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 text-xs font-semibold backdrop-blur-md">
                    <i class="ri-checkbox-circle-fill text-emerald-400"></i>
                    <span>Mitra Ekspedisi: <b>{{ expedition.name }}</b></span>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-gray-900/80 backdrop-blur-xl border border-white/10 rounded-3xl p-6 shadow-2xl space-y-5">
                <form @submit.prevent="submit" class="space-y-4">
                    
                    <!-- Nama Lengkap -->
                    <div class="space-y-1.5">
                        <label for="name" class="block text-xs font-bold text-gray-300 uppercase tracking-wider">Nama Lengkap Supir</label>
                        <div class="relative">
                            <i class="ri-user-line absolute left-3.5 top-3 text-gray-400 text-base"></i>
                            <input
                                id="name"
                                type="text"
                                v-model="form.name"
                                required
                                placeholder="Contoh: Bambang Sudiro"
                                class="w-full pl-10 pr-4 py-2.5 bg-black/40 border border-white/10 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition"
                            />
                        </div>
                        <InputError :message="form.errors.name" />
                    </div>

                    <!-- Nomor WhatsApp -->
                    <div class="space-y-1.5">
                        <label for="phone" class="block text-xs font-bold text-gray-300 uppercase tracking-wider">No. WhatsApp / HP</label>
                        <div class="relative">
                            <i class="ri-whatsapp-line absolute left-3.5 top-3 text-emerald-500 text-base"></i>
                            <input
                                id="phone"
                                type="tel"
                                v-model="form.phone"
                                required
                                placeholder="08xxxxxxxxxx"
                                class="w-full pl-10 pr-4 py-2.5 bg-black/40 border border-white/10 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition"
                            />
                        </div>
                        <InputError :message="form.errors.phone" />
                    </div>

                    <!-- Jenis & Plat Nomor Truk -->
                    <div class="space-y-1.5">
                        <label for="vehicle_name" class="block text-xs font-bold text-gray-300 uppercase tracking-wider">Armada & Plat Nomor</label>
                        <div class="relative">
                            <i class="ri-truck-line absolute left-3.5 top-3 text-primary text-base"></i>
                            <input
                                id="vehicle_name"
                                type="text"
                                v-model="form.vehicle_name"
                                required
                                placeholder="Contoh: K 8921 CL - Canter Box 120PS"
                                class="w-full pl-10 pr-4 py-2.5 bg-black/40 border border-white/10 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition"
                            />
                        </div>
                        <InputError :message="form.errors.vehicle_name" />
                    </div>

                    <!-- Pilihan Spesialisasi Armada -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-gray-300 uppercase tracking-wider">Tugas / Spesialisasi</label>
                        <div class="grid grid-cols-2 gap-2">
                            <!-- Lintas -->
                            <button
                                type="button"
                                @click="form.role = 'DRIVER_LINTAS'"
                                class="p-3 rounded-xl border text-left transition flex flex-col justify-between"
                                :class="form.role === 'DRIVER_LINTAS' ? 'bg-primary/20 border-primary text-white shadow-md shadow-primary/20' : 'bg-black/20 border-white/5 text-gray-400 hover:border-white/15'"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <i class="ri-route-line text-lg" :class="form.role === 'DRIVER_LINTAS' ? 'text-primary' : 'text-gray-500'"></i>
                                    <span v-if="form.role === 'DRIVER_LINTAS'" class="w-2 h-2 rounded-full bg-primary"></span>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-white">Supir Lintas</p>
                                    <p class="text-[10px] text-gray-400 leading-tight mt-0.5">Pengantaran luar kota</p>
                                </div>
                            </button>

                            <!-- Pickup -->
                            <button
                                type="button"
                                @click="form.role = 'DRIVER_PICKUP'"
                                class="p-3 rounded-xl border text-left transition flex flex-col justify-between"
                                :class="form.role === 'DRIVER_PICKUP' ? 'bg-primary/20 border-primary text-white shadow-md shadow-primary/20' : 'bg-black/20 border-white/5 text-gray-400 hover:border-white/15'"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <i class="ri-truck-fill text-lg" :class="form.role === 'DRIVER_PICKUP' ? 'text-primary' : 'text-gray-500'"></i>
                                    <span v-if="form.role === 'DRIVER_PICKUP'" class="w-2 h-2 rounded-full bg-primary"></span>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-white">Supir Pickup</p>
                                    <p class="text-[10px] text-gray-400 leading-tight mt-0.5">Jemput mebel di pengrajin</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Kata Sandi / PIN -->
                    <div class="space-y-1.5">
                        <label for="password" class="block text-xs font-bold text-gray-300 uppercase tracking-wider">Kata Sandi / PIN 6 Digit</label>
                        <div class="relative">
                            <i class="ri-lock-2-line absolute left-3.5 top-3 text-gray-400 text-base"></i>
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                placeholder="Minimal 6 karakter..."
                                class="w-full pl-10 pr-10 py-2.5 bg-black/40 border border-white/10 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition font-mono"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3.5 top-3 text-gray-400 hover:text-white"
                            >
                                <i :class="showPassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                            </button>
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Konfirmasi Sandi -->
                    <div class="space-y-1.5">
                        <label for="password_confirmation" class="block text-xs font-bold text-gray-300 uppercase tracking-wider">Ulangi Kata Sandi / PIN</label>
                        <div class="relative">
                            <i class="ri-lock-check-line absolute left-3.5 top-3 text-gray-400 text-base"></i>
                            <input
                                id="password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password_confirmation"
                                required
                                placeholder="Ketik ulang sandi..."
                                class="w-full pl-10 pr-4 py-2.5 bg-black/40 border border-white/10 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition font-mono"
                            />
                        </div>
                    </div>

                    <!-- Tombol Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full mt-2 py-3.5 px-4 rounded-xl bg-gradient-to-r from-primary to-primaryDark hover:from-primaryDark hover:to-primary text-white font-extrabold text-sm flex items-center justify-center gap-2 shadow-lg shadow-primary/30 active:scale-[0.98] transition disabled:opacity-50"
                    >
                        <i v-if="form.processing" class="ri-loader-4-line animate-spin"></i>
                        <template v-else>
                            <i class="ri-checkbox-circle-fill"></i>
                            <span>Selesaikan Pendaftaran</span>
                        </template>
                    </button>

                </form>

                <!-- Footer Tautan -->
                <div class="pt-4 border-t border-white/5 text-center">
                    <p class="text-xs text-gray-400">
                        Sudah punya akun supir? 
                        <Link :href="route('login', { mode: 'app' })" class="text-primary hover:text-primaryLight font-bold transition ml-1">
                            Masuk di sini
                        </Link>
                    </p>
                </div>
            </div>

        </div>

    </div>
</template>
