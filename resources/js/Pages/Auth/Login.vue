<script setup>
import InputError from '@/Components/InputError.vue';
import { ref, computed, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Capacitor } from '@capacitor/core';
import { useBiometricAuth } from '@/Composables/useBiometricAuth';

const props = defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
    mode: { type: String },
    users: { type: Array, default: () => [] }
});

const isMobileApp = computed(() => {
    return props.mode === 'app' || (typeof window !== 'undefined' && Capacitor.isNativePlatform());
});

const form = useForm({
    email: '',
    password: '',
    remember: isMobileApp.value,
});

const showPassword = ref(false);
const showQuickLogin = ref(false);

const {
    isSupported,
    isAuthenticating,
    registeredUser,
    errorMessage: biometricError,
    checkSupport,
    loadRegisteredDevice,
    loginWithBiometric,
    removeBiometric,
} = useBiometricAuth();

onMounted(async () => {
    await checkSupport();
    await loadRegisteredDevice();
});

const handleBiometricLogin = async () => {
    try {
        await loginWithBiometric();
    } catch (e) {
        console.warn('Biometric login error:', e);
    }
};

const handleRemoveBiometric = async () => {
    if (confirm('Lepas kunci layar/sidik jari akun ini dari perangkat? Anda tetap bisa masuk menggunakan kata sandi.')) {
        await removeBiometric();
    }
};

const formatRole = (role) => {
    if (role === 'DRIVER_PICKUP') return 'Supir Penjemputan (Lokal)';
    if (role === 'DRIVER_LINTAS') return 'Supir Pengantaran (Lintas Kota)';
    if (role === 'GUDANG') return 'Petugas Gudang / Checker';
    if (role === 'ADMIN') return 'Admin Ekspedisi';
    if (role === 'SUPERADMIN') return 'Super Admin';
    return role || 'Personil Lapangan';
};

const isDriver = (role) => {
    return role === 'DRIVER_PICKUP' || role === 'DRIVER_LINTAS';
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const quickLogin = (email) => {
    showQuickLogin.value = false;
    form.email = email;
    form.password = 'password';
    submit();
};
</script>

<template>
    <Head :title="isMobileApp ? 'Jepara Cargo Mobile' : 'Masuk'" />

    <div class="login-screen">

        <!-- Left: Branding Panel (Desktop only) -->
        <div class="brand-panel">
            <Link :href="route('welcome')" class="brand-back-btn">
                <i class="ri-arrow-left-line"></i> Kembali ke Beranda
            </Link>
            <div class="brand-ambient"></div>
            <div class="brand-content">
                <div class="brand-logo">
                    <i class="ri-truck-fill"></i>
                </div>
                <h1 style="font-size:38px; line-height:1.2;">{{ $page.props.app?.name || 'Aplikasi Cargo' }}</h1>
                <p>{{ $page.props.app?.tagline || 'Sistem Ekspedisi & Kargo Terpadu' }}</p>

                <div class="brand-features">
                    <div class="brand-feat">
                        <div class="brand-feat-icon"><i class="ri-file-list-3-line"></i></div>
                        <span>Manajemen Resi</span>
                    </div>
                    <div class="brand-feat">
                        <div class="brand-feat-icon"><i class="ri-barcode-box-line"></i></div>
                        <span>Scan & Lacak Koli</span>
                    </div>
                    <div class="brand-feat">
                        <div class="brand-feat-icon"><i class="ri-steering-2-line"></i></div>
                        <span>Armada Lintas Kota</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Login Form -->
        <div class="form-panel">
            <div class="form-container">

                <!-- Mobile Top Back Bar & Logo -->
                <div class="mobile-top-bar">
                    <Link v-if="!isMobileApp" :href="route('welcome')" class="mobile-back-btn">
                        <i class="ri-arrow-left-line"></i> Beranda
                    </Link>
                    <div v-else class="mobile-app-tag">
                        <i class="ri-steering-2-line"></i> Mode Supir & Gudang
                    </div>
                    <div class="mobile-logo">
                        <div class="logo-box"><i class="ri-truck-fill"></i></div>
                        <span>{{ $page.props.app?.name || 'Aplikasi Cargo' }}</span>
                    </div>
                </div>

                <div class="form-header">
                    <h2>{{ isMobileApp ? 'Jepara Cargo Mobile' : 'Selamat Datang' }}</h2>
                    <p>{{ isMobileApp ? 'Portal Masuk Supir & Petugas Gudang' : 'Masuk untuk mengakses sistem operasional' }}</p>
                </div>

                <div v-if="status" class="status-msg">
                    <i class="ri-check-line"></i> {{ status }}
                </div>

                <!-- KARTU MASUK CEPAT KUNCI LAYAR / SIDIK JARI -->
                <div v-if="registeredUser" class="biometric-card">
                    <div class="biometric-avatar" :class="{ 'biometric-avatar-driver': isDriver(registeredUser.role) }">
                        <i v-if="isDriver(registeredUser.role)" class="ri-steering-2-line"></i>
                        <i v-else class="ri-fingerprint-line"></i>
                    </div>
                    <div class="biometric-info">
                        <span class="biometric-badge">
                            <i class="ri-shield-check-fill"></i>
                            {{ isDriver(registeredUser.role) ? 'Kunci Layar Supir Aktif' : 'Kunci Layar HP Aktif' }}
                        </span>
                        <h4 class="biometric-user-name">{{ registeredUser.name }}</h4>
                        <p class="biometric-user-role">
                            <span class="font-semibold">{{ formatRole(registeredUser.role) }}</span>
                            <span class="opacity-60"> • {{ registeredUser.email }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn-biometric-unlock"
                        :disabled="isAuthenticating"
                        @click="handleBiometricLogin"
                        :title="isDriver(registeredUser.role) ? 'Buka Tugas Supir' : 'Buka Sistem'"
                    >
                        <i v-if="isAuthenticating" class="ri-loader-4-line spin"></i>
                        <template v-else>
                            <i class="ri-lock-unlock-line"></i>
                            <span>{{ isDriver(registeredUser.role) ? 'Buka Tugas' : 'Buka' }}</span>
                        </template>
                    </button>
                    <button
                        type="button"
                        class="btn-biometric-dismiss"
                        @click="handleRemoveBiometric"
                        title="Lepas Kunci Layar di HP Ini"
                    >
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <div v-if="biometricError" class="biometric-error-alert">
                    <i class="ri-error-warning-fill"></i>
                    <span>{{ biometricError }}</span>
                </div>

                <div v-if="registeredUser" class="biometric-divider">
                    <div class="divider-line"></div>
                    <span>atau gunakan kata sandi</span>
                    <div class="divider-line"></div>
                </div>

                <form @submit.prevent="submit" class="login-form">
                    <!-- Email -->
                    <div class="field-group">
                        <label for="email" class="field-label">Email</label>
                        <div class="input-wrap">
                            <i class="ri-mail-line input-icon"></i>
                            <input
                                id="email"
                                type="email"
                                class="field-input"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="nama@example.com"
                            />
                        </div>
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div class="field-group">
                        <div class="label-row">
                            <label for="password" class="field-label">Kata Sandi</label>
                            <Link v-if="canResetPassword && !isMobileApp" :href="route('password.request')" class="forgot-link">
                                Lupa kata sandi?
                            </Link>
                        </div>
                        <div class="input-wrap">
                            <i class="ri-lock-line input-icon"></i>
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                class="field-input"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                            />
                            <button
                                type="button"
                                class="btn-toggle-eye"
                                @click="showPassword = !showPassword"
                                tabindex="-1"
                                :title="showPassword ? 'Sembunyikan sandi' : 'Lihat sandi'"
                            >
                                <i :class="showPassword ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                            </button>
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Remember -->
                    <label class="remember-row">
                        <div class="custom-check">
                            <input type="checkbox" v-model="form.remember" id="remember" />
                            <div class="checkmark"><i class="ri-check-line"></i></div>
                        </div>
                        <span>Ingat saya di perangkat ini</span>
                    </label>

                    <!-- Submit -->
                    <button type="submit" class="btn-submit" :class="{ 'loading': form.processing }" :disabled="form.processing">
                        <span v-if="!form.processing">Masuk ke Sistem <i class="ri-arrow-right-line"></i></span>
                        <span v-else><i class="ri-loader-4-line spin"></i> Memproses...</span>
                    </button>
                </form>

                <!-- Tombol Daftar Mandiri Supir Baru -->
                <div class="driver-register-card">
                    <div class="driver-register-info">
                        <span class="driver-register-title">Supir Armada Baru?</span>
                        <span class="driver-register-desc">Daftar mandiri & aktifkan kunci layar HP</span>
                    </div>
                    <Link :href="route('register.driver')" class="btn-driver-register">
                        <i class="ri-steering-2-line"></i> Daftar Supir
                    </Link>
                </div>

                <!-- Tombol trigger quick login -->
                <div v-if="users.length > 0" class="quick-trigger-row">
                    <div class="divider-line"></div>
                    <button type="button" class="btn-quick-trigger" @click="showQuickLogin = true">
                        <i class="ri-user-line"></i>
                        Masuk cepat sebagai...
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                </div>

                <p v-if="!isMobileApp" class="back-link">
                    <Link :href="route('welcome')" class="back-link-a">
                        <i class="ri-arrow-left-line"></i> Kembali ke Beranda
                    </Link>
                </p>

            </div>
        </div>

        <!-- ======== QUICK LOGIN BOTTOM SHEET ======== -->
        <Transition name="sheet">
            <div v-if="showQuickLogin && users.length > 0" class="sheet-overlay" @click.self="showQuickLogin = false">
                <div class="bottom-sheet">
                    <!-- Handle bar -->
                    <div class="sheet-handle"></div>
                    <div class="sheet-header">
                        <h3>Masuk Cepat Sebagai</h3>
                        <button class="sheet-close" @click="showQuickLogin = false">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div class="sheet-list">
                        <button
                            v-for="user in users"
                            :key="user.email"
                            type="button"
                            @click="quickLogin(user.email)"
                            class="sheet-user-btn"
                        >
                            <div class="sheet-avatar" :class="{ 'avatar-super': !user.expedition_id }">
                                <i v-if="!user.expedition_id" class="ri-shield-star-fill text-lg"></i>
                                <span v-else>{{ user.name.substring(0,2).toUpperCase() }}</span>
                            </div>
                            <div class="sheet-user-info">
                                <div class="sheet-user-title-row">
                                    <span class="sheet-user-name">{{ user.name }}</span>
                                    <span v-if="!user.expedition_id" class="badge-super">Super Admin</span>
                                    <span v-else-if="user.expedition" class="badge-tenant">{{ user.expedition.name }}</span>
                                </div>
                                <span class="sheet-user-email">{{ user.email }}</span>
                            </div>
                            <i class="ri-arrow-right-s-line sheet-arrow"></i>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

    </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.login-screen {
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Helvetica Neue', sans-serif;
    -webkit-font-smoothing: antialiased;
    min-height: 100dvh;
    display: flex;
    animation: loginFadeIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes loginFadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ======== LEFT PANEL ======== */
.brand-panel {
    display: none;
    position: relative;
    overflow: hidden;
    background: linear-gradient(160deg, rgb(var(--color-primary-dark)) 0%, rgb(var(--color-primary)) 100%);
    flex: 1;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
}
@media (min-width: 768px) {
    .brand-panel { display: flex; }
}

.brand-back-btn {
    position: absolute;
    top: 24px;
    left: 28px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 999px;
    color: #ffffff;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
    z-index: 10;
}
.brand-back-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateX(-3px);
}

.brand-ambient {
    position: absolute;
    bottom: -100px;
    right: -100px;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.08), transparent 70%);
    pointer-events: none;
}
.brand-content { position: relative; z-index: 1; color: white; }

.brand-logo {
    width: 64px;
    height: 64px;
    background: rgba(255,255,255,0.15);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: white;
    margin-bottom: 24px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}
.brand-content h1 {
    font-size: 52px;
    font-weight: 800;
    letter-spacing: -2.5px;
    line-height: 1.0;
    margin-bottom: 16px;
    color: white;
}
.brand-content p {
    font-size: 16px;
    color: rgba(255,255,255,0.65);
    line-height: 1.6;
    margin-bottom: 48px;
}

.brand-features { display: flex; flex-direction: column; gap: 16px; }
.brand-feat {
    display: flex;
    align-items: center;
    gap: 14px;
    color: rgba(255,255,255,0.85);
    font-size: 15px;
    font-weight: 500;
}
.brand-feat-icon {
    width: 36px; height: 36px;
    background: rgba(255,255,255,0.12);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px;
    color: white;
    flex-shrink: 0;
}

/* ======== RIGHT PANEL ======== */
.form-panel {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 24px;
    background: #ffffff;
    overflow-y: auto;
}
.form-container {
    width: 100%;
    max-width: 400px;
}

/* Mobile top bar */
.mobile-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}
@media (min-width: 768px) {
    .mobile-top-bar { display: none; }
}

.mobile-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #f5f5f7;
    border-radius: 999px;
    color: #48484a;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.15s, transform 0.15s;
}
.mobile-back-btn:hover {
    background: #e5e5ea;
    transform: translateX(-2px);
}

.mobile-app-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: rgba(var(--color-primary), 0.08);
    border: 1px solid rgba(var(--color-primary), 0.2);
    border-radius: 999px;
    color: rgb(var(--color-primary));
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.2px;
}

.btn-toggle-eye {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #8e8e93;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    transition: color 0.15s;
}
.btn-toggle-eye:hover {
    color: #1d1d1f;
}

/* Mobile logo */
.mobile-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 700;
    color: #1d1d1f;
}
.logo-box {
    width: 30px; height: 30px;
    background: rgb(var(--color-primary));
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 15px;
    box-shadow: 0 2px 8px rgb(var(--color-primary) / 0.35);
}

/* Form Header */
.form-header { margin-bottom: 20px; }
.form-header h2 {
    font-size: 26px;
    font-weight: 700;
    letter-spacing: -0.8px;
    color: #1d1d1f;
    margin-bottom: 4px;
}
.form-header p { font-size: 14px; color: #86868b; }

/* Status */
.status-msg {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    color: #15803d;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
}

/* Biometric Quick Login Card */
.biometric-card {
    display: flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, rgba(var(--color-primary), 0.08) 0%, rgba(var(--color-primary), 0.02) 100%);
    border: 1.5px solid rgba(var(--color-primary), 0.25);
    border-radius: 16px;
    padding: 12px 14px;
    margin-bottom: 16px;
    position: relative;
    box-shadow: 0 4px 14px rgba(var(--color-primary), 0.06);
    transition: all 0.2s ease;
}
.biometric-card:hover {
    border-color: rgba(var(--color-primary), 0.45);
    box-shadow: 0 6px 18px rgba(var(--color-primary), 0.1);
}
.biometric-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgb(var(--color-primary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 10px rgba(var(--color-primary), 0.35);
    flex-shrink: 0;
}
.biometric-avatar-driver {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: rgb(var(--color-primary));
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.35);
}
.biometric-info {
    flex: 1;
    min-width: 0;
}
.biometric-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    font-weight: 700;
    color: rgb(var(--color-primary));
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 2px;
}
.biometric-user-name {
    font-size: 14px;
    font-weight: 700;
    color: #1d1d1f;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.biometric-user-role {
    font-size: 11px;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
}
.btn-biometric-unlock {
    padding: 8px 14px;
    background: rgb(var(--color-primary));
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    box-shadow: 0 3px 8px rgba(var(--color-primary), 0.3);
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.btn-biometric-unlock:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 12px rgba(var(--color-primary), 0.4);
}
.btn-biometric-unlock:active {
    transform: scale(0.96);
}
.btn-biometric-dismiss {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(0,0,0,0.06);
    border: none;
    color: #9ca3af;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    position: absolute;
    top: 6px;
    right: 6px;
    transition: background 0.15s, color 0.15s;
}
.btn-biometric-dismiss:hover {
    background: #fee2e2;
    color: #ef4444;
}
.biometric-error-alert {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 10px;
    color: #b91c1c;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 14px;
}
.biometric-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.biometric-divider .divider-line {
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}
.biometric-divider span {
    font-size: 11px;
    color: #9ca3af;
    font-weight: 500;
}

/* Driver Register Card */
.driver-register-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: #f8fafc;
    border: 1.5px dashed #cbd5e1;
    border-radius: 14px;
    padding: 10px 14px;
    margin-top: 16px;
    transition: all 0.2s ease;
}
.driver-register-card:hover {
    border-color: rgb(var(--color-primary));
    background: rgba(var(--color-primary), 0.03);
}
.driver-register-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.driver-register-title {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
}
.driver-register-desc {
    font-size: 11px;
    color: #64748b;
}
.btn-driver-register {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #1e293b;
    color: white;
    font-size: 12px;
    font-weight: 700;
    padding: 8px 12px;
    border-radius: 10px;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.15s ease;
}
.btn-driver-register:hover {
    background: black;
    transform: translateY(-1px);
}

/* Form fields */
.login-form { display: flex; flex-direction: column; gap: 14px; }
.field-group { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 13px; font-weight: 600; color: #1d1d1f; }
.label-row { display: flex; justify-content: space-between; align-items: center; }
.forgot-link { font-size: 12px; color: rgb(var(--color-primary)); text-decoration: none; font-weight: 500; }
.forgot-link:hover { text-decoration: underline; }

.input-wrap {
    position: relative;
    display: flex; align-items: center;
}
.input-icon {
    position: absolute;
    left: 12px;
    color: #aeaeb2;
    font-size: 16px;
    pointer-events: none;
}
.field-input {
    width: 100%;
    padding: 11px 14px 11px 38px;
    background: #f5f5f7;
    border: 1.5px solid transparent;
    border-radius: 11px;
    font-size: 15px;
    color: #1d1d1f;
    outline: none;
    transition: border-color 0.2s, background 0.2s;
    font-family: inherit;
}
.field-input:focus {
    background: #fff;
    border-color: rgb(var(--color-primary));
    box-shadow: 0 0 0 3px rgb(var(--color-primary) / 0.12);
}
.field-input::placeholder { color: #c7c7cc; }

/* Remember */
.remember-row {
    display: flex; align-items: center; gap: 10px;
    cursor: pointer; user-select: none;
}
.custom-check { position: relative; }
.custom-check input { position: absolute; opacity: 0; width: 0; height: 0; }
.checkmark {
    width: 20px; height: 20px;
    border: 1.5px solid #c7c7cc;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
    color: transparent;
    background: #f5f5f7;
    transition: all 0.15s;
}
.custom-check input:checked + .checkmark {
    background: rgb(var(--color-primary));
    border-color: rgb(var(--color-primary));
    color: white;
}
.remember-row span { font-size: 14px; color: #6e6e73; }

/* Submit */
.btn-submit {
    width: 100%;
    padding: 13px;
    background: rgb(var(--color-primary));
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background 0.15s, transform 0.15s, box-shadow 0.15s;
    box-shadow: 0 4px 16px rgb(var(--color-primary) / 0.35);
    letter-spacing: -0.2px;
    font-family: inherit;
}
.btn-submit:hover:not(:disabled) {
    background: rgb(var(--color-primary-dark));
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgb(var(--color-primary) / 0.45);
}
.btn-submit:disabled { opacity: 0.65; cursor: not-allowed; }
.btn-submit.loading { background: rgb(var(--color-primary-dark)); }

.spin { animation: spin 0.8s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Quick login trigger */
.quick-trigger-row {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}
.divider-line {
    width: 100%;
    height: 1px;
    background: rgba(0,0,0,0.07);
}
.btn-quick-trigger {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #f5f5f7;
    border: none;
    border-radius: 980px;
    font-size: 13px;
    font-weight: 500;
    color: #3a3a3c;
    cursor: pointer;
    transition: background 0.15s, transform 0.15s;
    font-family: inherit;
}
.btn-quick-trigger:hover { background: #e8e8ed; transform: scale(1.02); }

/* Back link */
.back-link { margin-top: 14px; text-align: center; }
.back-link-a {
    font-size: 13px; color: #6e6e73;
    text-decoration: none;
    display: inline-flex; align-items: center; gap: 4px;
    transition: color 0.15s;
}
.back-link-a:hover { color: rgb(var(--color-primary)); }

/* ======== BOTTOM SHEET ======== */
.sheet-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 200;
    display: flex;
    align-items: flex-end;
    backdrop-filter: blur(4px);
}
.bottom-sheet {
    width: 100%;
    background: white;
    border-radius: 20px 20px 0 0;
    padding: 12px 20px 32px;
    max-height: 70dvh;
    overflow-y: auto;
}
.sheet-handle {
    width: 36px;
    height: 4px;
    background: #c7c7cc;
    border-radius: 2px;
    margin: 0 auto 16px;
}
.sheet-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.sheet-header h3 {
    font-size: 17px;
    font-weight: 700;
    color: #1d1d1f;
    letter-spacing: -0.4px;
}
.sheet-close {
    width: 28px; height: 28px;
    background: #f5f5f7;
    border: none;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
    color: #6e6e73;
    cursor: pointer;
    transition: background 0.15s;
}
.sheet-close:hover { background: #e8e8ed; }

.sheet-list { display: flex; flex-direction: column; gap: 6px; }
.sheet-user-btn {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px;
    background: #f5f5f7;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    text-align: left;
    width: 100%;
    transition: background 0.15s;
    font-family: inherit;
}
.sheet-user-btn:hover { background: #ebebed; }
.sheet-avatar {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, rgb(var(--color-primary)), rgb(var(--color-primary-light)));
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: white;
    flex-shrink: 0;
}
.sheet-avatar.avatar-super {
    background: linear-gradient(135deg, #1e293b, #475569);
    box-shadow: 0 2px 6px rgba(30, 41, 59, 0.35);
}
.sheet-user-info { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.sheet-user-title-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.sheet-user-name { font-size: 14px; font-weight: 600; color: #1d1d1f; }
.sheet-user-email { font-size: 12px; color: #8e8e93; }
.badge-super {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 2px 6px;
    background: #1e293b;
    color: #f8fafc;
    border-radius: 6px;
}
.badge-tenant {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    background: rgba(var(--color-primary), 0.12);
    color: rgb(var(--color-primary));
    border-radius: 6px;
}
.sheet-arrow { font-size: 18px; color: #c7c7cc; }

/* Sheet Transition */
.sheet-enter-active, .sheet-leave-active { transition: opacity 0.25s ease; }
.sheet-enter-active .bottom-sheet, .sheet-leave-active .bottom-sheet { transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1); }
.sheet-enter-from, .sheet-leave-to { opacity: 0; }
.sheet-enter-from .bottom-sheet, .sheet-leave-to .bottom-sheet { transform: translateY(100%); }

/* ======== MOBILE LOCK (No scroll) ======== */
@media (max-width: 767px) {
    .login-screen {
        height: 100dvh;
        overflow: hidden;
    }
    .form-panel {
        height: 100dvh;
        overflow: hidden;
        padding: 20px 20px;
        align-items: center;
    }
}
</style>
