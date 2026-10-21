<script setup>
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
    users: { type: Array, default: () => [] }
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showQuickLogin = ref(false);

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
    <Head title="Masuk" />

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
                    <Link :href="route('welcome')" class="mobile-back-btn">
                        <i class="ri-arrow-left-line"></i> Beranda
                    </Link>
                    <div class="mobile-logo">
                        <div class="logo-box"><i class="ri-truck-fill"></i></div>
                        <span>{{ $page.props.app?.name || 'Aplikasi Cargo' }}</span>
                    </div>
                </div>

                <div class="form-header">
                    <h2>Selamat Datang</h2>
                    <p>Masuk untuk mengakses sistem operasional</p>
                </div>

                <div v-if="status" class="status-msg">
                    <i class="ri-check-line"></i> {{ status }}
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
                            <Link v-if="canResetPassword" :href="route('password.request')" class="forgot-link">
                                Lupa kata sandi?
                            </Link>
                        </div>
                        <div class="input-wrap">
                            <i class="ri-lock-line input-icon"></i>
                            <input
                                id="password"
                                type="password"
                                class="field-input"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                            />
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Remember -->
                    <label class="remember-row">
                        <div class="custom-check">
                            <input type="checkbox" v-model="form.remember" id="remember" />
                            <div class="checkmark"><i class="ri-check-line"></i></div>
                        </div>
                        <span>Ingat saya</span>
                    </label>

                    <!-- Submit -->
                    <button type="submit" class="btn-submit" :class="{ 'loading': form.processing }" :disabled="form.processing">
                        <span v-if="!form.processing">Masuk ke Sistem <i class="ri-arrow-right-line"></i></span>
                        <span v-else><i class="ri-loader-4-line spin"></i> Memproses...</span>
                    </button>
                </form>

                <!-- Tombol trigger quick login -->
                <div v-if="users.length > 0" class="quick-trigger-row">
                    <div class="divider-line"></div>
                    <button type="button" class="btn-quick-trigger" @click="showQuickLogin = true">
                        <i class="ri-user-line"></i>
                        Masuk cepat sebagai...
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                </div>

                <p class="back-link">
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
