<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

defineProps({ canLogin: { type: Boolean } });

const showWizard = ref(false);
const appRole = ref(null);

const driverToken = ref('');
const driverAction = ref('');

const standbyVariations = [
    { icon: 'ri-box-3-fill', color: '#d97706', text: 'Paketnya kabur! Tangkap!' }, // Kardus coklat
    { icon: 'ri-shopping-cart-fill', color: '#ef4444', text: 'Troli super terbang mode ON!' }, // Troli merah
    { icon: 'ri-ghost-smile-fill', color: '#e2e8f0', text: 'Hantu ramah numpang lewat~' }, // Hantu putih
    { icon: 'ri-cup-fill', color: '#8b5cf6', text: 'Kopi terbang penyambung nyawa!' }, // Kopi ungu
    { icon: 'ri-aliens-fill', color: '#10b981', text: 'Awas paket diculik alien!' } // Alien hijau
];
const activeVariation = ref(standbyVariations[0]);

// --- TRACKING RESI LOGIC ---
const searchResi = ref('');
const isTracking = ref(false);
const trackingResult = ref(null);
const trackingError = ref('');

const searchTracking = async () => {
    const query = searchResi.value.trim();
    if (!query) return;

    isTracking.value = true;
    trackingError.value = '';
    trackingResult.value = null;

    try {
        const res = await fetch(`/track-resi/${encodeURIComponent(query)}`);
        const data = await res.json();
        if (res.ok && data.found) {
            trackingResult.value = data.shipment;
        } else {
            trackingError.value = data.message || 'Nomor resi tidak ditemukan. Silakan cek kembali.';
        }
    } catch (err) {
        trackingError.value = 'Gagal menghubungi server. Periksa koneksi internet Anda.';
    } finally {
        isTracking.value = false;
    }
};

const closeTracking = () => {
    trackingResult.value = null;
    trackingError.value = '';
};

// --- SECRET LOGIN ACCESS (KHUSUS EKSPEDISI) ---
const showSecretLogin = ref(false);
const logoClickCount = ref(0);
let clickTimeout = null;

const onLogoClick = () => {
    logoClickCount.value++;
    if (clickTimeout) clearTimeout(clickTimeout);
    
    if (logoClickCount.value >= 4) {
        showSecretLogin.value = !showSecretLogin.value;
        localStorage.setItem('cargo_portal_unlocked', showSecretLogin.value ? 'true' : 'false');
        logoClickCount.value = 0;
    } else {
        clickTimeout = setTimeout(() => {
            logoClickCount.value = 0;
        }, 1500);
    }
};

const handleKeyDown = (e) => {
    // Tombol rahasia keyboard: Ctrl + Shift + L atau Alt + M
    if ((e.ctrlKey && e.shiftKey && (e.key === 'L' || e.key === 'l')) || (e.altKey && (e.key === 'm' || e.key === 'M'))) {
        e.preventDefault();
        showSecretLogin.value = !showSecretLogin.value;
        localStorage.setItem('cargo_portal_unlocked', showSecretLogin.value ? 'true' : 'false');
    }
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleKeyDown);

        // Ambil status dari cache / localStorage agar sekali buka tetap tersimpan
        const isUnlocked = localStorage.getItem('cargo_portal_unlocked') === 'true';
        if (isUnlocked) {
            showSecretLogin.value = true;
        }

        // Jika ada param ?portal=ekspedisi atau ?login=true di URL, buka dan simpan ke cache
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('portal') || urlParams.has('admin') || urlParams.has('login')) {
            showSecretLogin.value = true;
            localStorage.setItem('cargo_portal_unlocked', 'true');
        }

        gameHeight.value = window.innerHeight;
        gameWidth.value = window.innerWidth;
        window.addEventListener('resize', () => {
            gameHeight.value = window.innerHeight;
            gameWidth.value = window.innerWidth;
        });
    }

    // Role default customer
    appRole.value = 'customer';
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleKeyDown);
    }
});

// --- FLAPPY TRUCK GAME LOGIC ---
const gameStarted = ref(false);
const gameOver = ref(false);
const score = ref(0);
const highScore = ref(parseInt(localStorage.getItem('flappy_highscore')) || 0);

const truckY = ref(60);
const truckVelocity = ref(0);
const pipes = ref([]);

const gravity = 0.3; // Sangat melayang, jatuh perlahan
const jumpStrength = -6; // Lompatan lebih pendek & terkontrol
const pipeWidth = 60;
const gameHeight = ref(600);
const gameWidth = ref(300);
const roadHeight = 30;
const truckSize = 40;

let animationFrame;
let pipeIdCounter = 0;

const flap = () => {
    if (gameOver.value) return;
    if (!gameStarted.value) {
        gameStarted.value = true;
        loop();
    }
    truckVelocity.value = jumpStrength;
};

const startGame = () => {
    score.value = 0;
    truckY.value = 60;
    truckVelocity.value = 0;
    pipes.value = [];
    gameOver.value = false;
    gameStarted.value = false;
    if (animationFrame) cancelAnimationFrame(animationFrame);
};

const addPipe = () => {
    const minPipe = 30;
    
    // Level bertambah setiap 5 skor
    const currentLevel = Math.floor(score.value / 5);
    // Gap sangat super lebar (300px), menyempit sangat sedikit tiap level (min 200px)
    const gap = Math.max(200, 300 - (currentLevel * 10));
    
    const maxTop = gameHeight.value - roadHeight - gap - minPipe;
    const topHeight = Math.floor(Math.random() * (maxTop - minPipe + 1)) + minPipe;
    
    pipes.value.push({
        id: ++pipeIdCounter,
        x: gameWidth.value + 50,
        topHeight: topHeight,
        bottomHeight: gameHeight.value - roadHeight - topHeight - gap,
        passed: false
    });
};

const loop = () => {
    if (gameOver.value) return;

    truckVelocity.value += gravity;
    truckY.value += truckVelocity.value;

    // Hit ground or ceiling
    if (truckY.value < 0 || truckY.value + truckSize > gameHeight.value - roadHeight) {
        endGame();
    }

    // Kecepatan awal sangat lambat (2.0)
    const currentLevel = Math.floor(score.value / 5);
    const currentSpeed = 2.0 + (currentLevel * 0.2);

    pipes.value.forEach(p => {
        p.x -= currentSpeed;
        
        // collision (toleransi hitbox diperbesar menjadi 12px agar sangat sulit tertabrak di pinggiran)
        if (p.x < 40 + truckSize - 12 && p.x + pipeWidth > 40 + 12) {
            if (truckY.value < p.topHeight + 8 || truckY.value + truckSize > gameHeight.value - roadHeight - p.bottomHeight - 8) {
                endGame();
            }
        }

        // score
        if (!p.passed && p.x + pipeWidth < 40) {
            p.passed = true;
            score.value++;
            if (score.value > highScore.value) {
                highScore.value = score.value;
                localStorage.setItem('flappy_highscore', highScore.value);
            }
        }
    });

    if (pipes.value.length && pipes.value[0].x < -pipeWidth) {
        pipes.value.shift();
    }

    if (pipes.value.length === 0 || pipes.value[pipes.value.length - 1].x < 200) {
        addPipe();
    }

    if (!gameOver.value) {
        animationFrame = requestAnimationFrame(loop);
    }
};

const endGame = () => {
    gameOver.value = true;
    gameStarted.value = false;
};

onUnmounted(() => {
    if (animationFrame) cancelAnimationFrame(animationFrame);
});

</script>

<template>
    <Head :title="$page.props.company?.name || 'Aplikasi Cargo'" />

    <!-- ======== PORTAL SUPIR (MINI GAME) ======== -->
    <div v-if="appRole === 'driver'" class="driver-landing-page flappy-game-area" @click="flap" :style="{ height: gameHeight + 'px', width: gameWidth + 'px' }">
       <div class="sky-elements">
          <i class="ri-cloud-fill cloud c1"></i>
          <i class="ri-cloud-fill cloud c2"></i>
       </div>
       
       <div class="score-board">
           <span style="font-size: 20px; color: #fbbf24;">Level {{ Math.floor(score / 5) + 1 }}</span>
           <span style="font-size: 20px;">Score: {{ score }}</span>
           <span style="font-size: 20px;">Hi: {{ highScore }}</span>
       </div>
       
       <div v-if="!gameStarted && !gameOver" class="game-overlay">
           <p style="font-size: 32px; margin-bottom: 5px; animation: pulse 1s infinite">Tap to Fly!</p>
           <p style="font-size: 16px; font-weight: 400; opacity: 0.8">Mini Game Supir: {{ activeVariation.text }}</p>
       </div>
       
       <div v-if="gameOver" class="game-overlay game-over">
           <h3 style="font-size: 40px; margin-bottom: 20px;">Game Over!</h3>
           <button @click.stop="startGame" class="restart-btn" style="font-size: 20px; padding: 15px 30px;">Main Lagi</button>
       </div>

       <!-- Player -->
       <div class="flappy-truck" :style="{ top: truckY + 'px', transform: `rotate(${Math.min(Math.max(truckVelocity * 3, -25), 90)}deg)` }">
           <i :class="activeVariation.icon" :style="{ color: activeVariation.color }"></i>
       </div>

       <!-- Pipes -->
       <div v-for="pipe in pipes" :key="pipe.id">
           <div class="pipe pipe-top" :style="{ left: pipe.x + 'px', height: pipe.topHeight + 'px', width: pipeWidth + 'px' }"></div>
           <div class="pipe pipe-bottom" :style="{ left: pipe.x + 'px', height: pipe.bottomHeight + 'px', width: pipeWidth + 'px', bottom: roadHeight + 'px' }"></div>
       </div>
       
       <div class="road" :class="{ 'moving': gameStarted }" :style="{ height: roadHeight + 'px' }"></div>
    </div>

    <!-- ======== CUSTOMER VIEW (DEFAULT) ======== -->
    <div v-else class="landing-page">

        <!-- ======== NAVBAR ======== -->
        <nav class="nav">
            <div class="nav-brand" @click="onLogoClick" style="cursor: pointer;" title="Klik 4x untuk Akses Masuk">
                <div class="logo-box"><i class="ri-truck-fill"></i></div>
                <div class="brand-text-group">
                    <span class="brand-title">{{ $page.props.app?.name || 'Aplikasi Cargo' }}</span>
                    <span class="brand-subtitle">{{ $page.props.app?.tagline || 'Sistem Ekspedisi & Kargo Terpadu' }}</span>
                </div>
            </div>
            
            <!-- Tombol Masuk Rahasia (Hanya Tampil Jika Terbuka via Kunci Rahasia / Sudah Login) -->
            <div v-if="canLogin">
                <Link v-if="$page.props.auth?.user" :href="route('dashboard')" class="pill-btn">
                    <i class="ri-dashboard-fill"></i> Dashboard
                </Link>
                <Transition name="fade-slide">
                    <Link v-if="!$page.props.auth?.user && showSecretLogin" :href="route('login')" class="pill-btn secret-pill">
                        Masuk
                    </Link>
                </Transition>
            </div>
        </nav>

        <!-- ======== HERO CONTENT (FULL SCREEN) ======== -->
        <main class="hero-section">
            <div class="ambient"></div>
            
            <div class="hero-content">
                <h1 class="headline">
                    Lacak Furniture<br/>
                    <span class="accent">Anda Disini.</span>
                </h1>

                <!-- Hero Floating Tracking Card (Focused & Clean) -->
                <div class="tracking-card">
                    <form class="tracking-form-clean" @submit.prevent="searchTracking">
                        <div class="tracking-box-inner">
                            <div class="input-prefix">
                                <i class="ri-search-2-line"></i>
                            </div>
                            <input 
                                v-model="searchResi"
                                type="text" 
                                placeholder="Masukkan nomor resi pengiriman Anda..." 
                                class="clean-tracking-input" 
                                :disabled="isTracking"
                                autofocus
                            />
                            <button type="submit" class="btn-track-primary" :disabled="isTracking || !searchResi.trim()" title="Lacak Resi">
                                <i v-if="isTracking" class="ri-loader-4-line spin"></i>
                                <span v-else class="track-btn-label">Lacak Resi</span>
                                <i v-if="!isTracking" class="ri-arrow-right-line track-btn-icon"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Pesan Error / Tidak Ditemukan -->
                    <div v-if="trackingError" class="tracking-error-msg">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ trackingError }}</span>
                    </div>
                </div>
            </div>
        </main>

        <!-- ======== MODAL POPUP HASIL TRACKING ======== -->
        <div v-if="trackingResult" class="tracking-modal-overlay" @click.self="closeTracking">
            <div class="tracking-modal-card">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <span class="badge-status-pill">{{ trackingResult.current_status }}</span>
                        <h3>Resi: {{ trackingResult.tracking_number }}</h3>
                    </div>
                    <button @click="closeTracking" class="modal-close-btn">
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- Ringkasan Info -->
                    <div class="info-grid">
                        <div class="info-cell">
                            <span class="info-label">Pengirim</span>
                            <span class="info-val">{{ trackingResult.sender_name }}</span>
                            <span class="info-sub">{{ trackingResult.sender_city }}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Penerima & Tujuan</span>
                            <span class="info-val">{{ trackingResult.recipient_name }}</span>
                            <span class="info-sub">{{ trackingResult.destination }}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Jumlah Koli</span>
                            <span class="info-val">{{ trackingResult.total_koli }} Koli Fisik</span>
                            <span class="info-sub">{{ trackingResult.payment_type }}</span>
                        </div>
                    </div>

                    <!-- Timeline Pelacakan -->
                    <div class="timeline-section">
                        <h4>Riwayat Pengiriman</h4>
                        <div class="timeline-list">
                            <div v-for="(step, idx) in trackingResult.timeline" :key="idx" class="timeline-item" :class="{ 'active': idx === 0 }">
                                <div class="timeline-dot">
                                    <i v-if="idx === 0" class="ri-truck-fill"></i>
                                    <span v-else></span>
                                </div>
                                <div class="timeline-content">
                                    <div class="tl-title-row">
                                        <strong>{{ step.status }}</strong>
                                        <span class="tl-time">{{ step.time }}</span>
                                    </div>
                                    <p class="tl-loc">{{ step.location }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rincian Koli jika ada -->
                    <div v-if="trackingResult.kolis && trackingResult.kolis.length" class="koli-section">
                        <h4>Daftar Koli ({{ trackingResult.kolis.length }})</h4>
                        <div class="koli-badges">
                            <div v-for="k in trackingResult.kolis" :key="k.barcode_id" class="koli-chip">
                                <i class="ri-barcode-box-line"></i>
                                <span>{{ k.barcode_id }}</span>
                                <small v-if="k.description">({{ k.description }})</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button @click="closeTracking" class="btn-modal-close">Tutup</button>
                </div>
            </div>
        </div>

        <!-- ======== FOOTER MINIMALIS ======== -->
        <footer class="footer-compact">
            <span class="footer-credit">powered by <strong>JIHAN LAB</strong></span>
        </footer>

    </div>
</template>

<style scoped>
/* SF Pro / System font stack seperti Apple */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.landing-page {
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Helvetica Neue', sans-serif;
    -webkit-font-smoothing: antialiased;
    background: #ffffff;
    color: #1d1d1f;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    animation: welcomeFadeIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes welcomeFadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ======== NAV ======== */
.nav {
    position: sticky;
    top: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px;
    height: 60px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}
.nav-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}
.brand-text-group {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
}
.brand-title {
    font-size: 16px;
    font-weight: 700;
    color: #1d1d1f;
    letter-spacing: -0.3px;
}
.brand-subtitle {
    font-size: 11px;
    font-weight: 500;
    color: #86868b;
    letter-spacing: -0.1px;
}
.logo-box {
    width: 32px; height: 32px;
    background: rgb(var(--color-primary));
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 16px;
    box-shadow: 0 2px 8px rgb(var(--color-primary) / 0.3);
}
.pill-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px;
    background: rgb(var(--color-primary)); color: white;
    border-radius: 98px; font-size: 14px; font-weight: 500;
    text-decoration: none;
    transition: background 0.15s, transform 0.15s;
}
.pill-btn:hover { background: rgb(var(--color-primary-dark)); transform: scale(1.04); }

.secret-pill {
    background: rgb(var(--color-primary));
    box-shadow: 0 4px 14px rgb(var(--color-primary) / 0.35);
}
.secret-pill:hover {
    background: rgb(var(--color-primary-dark));
    transform: scale(1.04);
}

.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.95);
}

/* ======== HERO ======== */
/* Full-screen Hero Section */
.hero-section {
    position: relative;
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 30px 20px;
    overflow: hidden;
    width: 100%;
}
.ambient {
    position: absolute;
    top: -150px;
    left: 50%;
    transform: translateX(-50%);
    width: min(800px, 100vw);
    height: 500px;
    background: radial-gradient(ellipse, rgb(var(--color-primary) / 0.12) 0%, transparent 65%);
    pointer-events: none;
    z-index: 0;
}
.hero-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    max-width: 620px;
    margin: auto;
}

.headline {
    font-size: clamp(32px, 6vw, 58px);
    font-weight: 800;
    letter-spacing: -1.5px;
    line-height: 1.12;
    color: #1d1d1f;
    margin-bottom: 24px;
}
.accent {
    background: linear-gradient(135deg, rgb(var(--color-primary)), rgb(var(--color-primary-light)));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Tracking Card (Focal Point) */
.tracking-card {
    width: 100%;
    background: #ffffff;
    border-radius: 20px;
    padding: 8px;
    box-shadow: 0 16px 40px -8px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0, 0, 0, 0.07);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.tracking-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 48px -8px rgba(0, 0, 0, 0.12), 0 4px 10px rgba(0, 0, 0, 0.04);
}

.tracking-form-clean {
    width: 100%;
}

.tracking-box-inner {
    display: flex;
    align-items: center;
    background: #fbfbfd;
    border-radius: 14px;
    padding: 5px 5px 5px 16px;
    border: 1.5px solid rgba(0, 0, 0, 0.06);
    transition: all 0.2s ease;
}
.tracking-box-inner:focus-within {
    background: #ffffff;
    border-color: rgb(var(--color-primary));
    box-shadow: 0 0 0 4px rgb(var(--color-primary) / 0.14);
}

.input-prefix {
    color: #86868b;
    font-size: 20px;
    display: flex;
    align-items: center;
    margin-right: 10px;
}

.clean-tracking-input {
    flex: 1;
    border: none !important;
    background: transparent;
    font-size: clamp(14px, 3.5vw, 15px);
    font-weight: 500;
    color: #1d1d1f;
    outline: none !important;
    box-shadow: none !important;
    width: 100%;
    min-width: 0;
    --tw-ring-shadow: 0 0 transparent !important;
    --tw-ring-offset-shadow: 0 0 transparent !important;
}
.clean-tracking-input::placeholder {
    color: #98989d;
    font-weight: 400;
}

/* Button Lacak Resi */
.btn-track-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: linear-gradient(135deg, rgb(var(--color-primary)), rgb(var(--color-primary-dark)));
    color: white;
    border: none;
    border-radius: 12px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgb(var(--color-primary) / 0.3);
    transition: all 0.2s ease;
    white-space: nowrap;
}

/* Di Layar Sempit / Mobile (max-width: 640px): Cukup icon panah saja */
@media (max-width: 640px) {
    .btn-track-primary {
        width: 42px;
        height: 42px;
        padding: 0;
        gap: 0;
        border-radius: 12px;
        flex-shrink: 0;
    }
    .track-btn-label {
        display: none;
    }
    .track-btn-icon {
        font-size: 20px;
    }
}

.btn-track-primary:hover:not(:disabled) {
    transform: translateY(-1px) scale(1.03);
    box-shadow: 0 6px 18px rgb(var(--color-primary) / 0.45);
}
.btn-track-primary:active:not(:disabled) {
    transform: scale(0.96);
}
.btn-track-primary:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.tracking-error-msg {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px 4px;
    color: #dc2626;
    font-size: 13px;
    font-weight: 500;
    text-align: left;
}

.spin {
    animation: spin 0.8s linear infinite;
    display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Footer Compact */
.footer-compact {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px 28px;
    font-size: 13px;
    color: #86868b;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    background: #ffffff;
}
.footer-credit strong {
    color: #1d1d1f;
    font-weight: 700;
    letter-spacing: 0.3px;
}

/* Modal Popup Tracking */
.tracking-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: modalFadeIn 0.25s ease-out;
}
@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.96); }
    to { opacity: 1; transform: scale(1); }
}

.tracking-modal-card {
    background: #ffffff;
    border-radius: 24px;
    width: 100%;
    max-width: 540px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 60px rgba(0,0,0,0.2);
    overflow: hidden;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}
.modal-title-group {
    display: flex;
    align-items: center;
    gap: 12px;
}
.badge-status-pill {
    padding: 4px 10px;
    background: rgb(var(--color-primary) / 0.12);
    color: rgb(var(--color-primary-dark));
    font-size: 12px;
    font-weight: 700;
    border-radius: 999px;
}
.modal-title-group h3 {
    font-size: 16px;
    font-weight: 700;
    color: #1d1d1f;
}
.modal-close-btn {
    background: #f5f5f7;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6e6e73;
    cursor: pointer;
    font-size: 18px;
    transition: background 0.15s;
}
.modal-close-btn:hover {
    background: #e5e5ea;
    color: #1d1d1f;
}

.modal-body {
    padding: 20px 24px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    background: #fbfbfd;
    padding: 14px;
    border-radius: 16px;
    border: 1px solid rgba(0,0,0,0.05);
}
.info-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.info-label {
    font-size: 11px;
    color: #86868b;
    font-weight: 500;
}
.info-val {
    font-size: 13px;
    font-weight: 700;
    color: #1d1d1f;
}
.info-sub {
    font-size: 11px;
    color: #6e6e73;
}

.timeline-section h4,
.koli-section h4 {
    font-size: 13px;
    font-weight: 700;
    color: #1d1d1f;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.timeline-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: relative;
    padding-left: 20px;
}
.timeline-list::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 8px;
    bottom: 8px;
    width: 2px;
    background: #e5e5ea;
}

.timeline-item {
    position: relative;
    display: flex;
    flex-direction: column;
}
.timeline-dot {
    position: absolute;
    left: -20px;
    top: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #e5e5ea;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
}
.timeline-item.active .timeline-dot {
    background: rgb(var(--color-primary));
    box-shadow: 0 0 0 3px rgb(var(--color-primary) / 0.2);
}
.timeline-dot span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #86868b;
}

.timeline-content {
    padding-left: 6px;
}
.tl-title-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
}
.tl-title-row strong {
    font-size: 13px;
    color: #1d1d1f;
}
.tl-time {
    font-size: 11px;
    color: #86868b;
}
.tl-loc {
    font-size: 12px;
    color: #6e6e73;
    margin-top: 2px;
}

.koli-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.koli-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #f5f5f7;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    color: #1d1d1f;
}
.koli-chip i { color: rgb(var(--color-primary)); }
.koli-chip small { color: #86868b; }

.modal-footer {
    padding: 14px 24px;
    border-top: 1px solid rgba(0,0,0,0.06);
    display: flex;
    justify-content: flex-end;
}
.btn-modal-close {
    background: #f5f5f7;
    border: none;
    border-radius: 10px;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 600;
    color: #1d1d1f;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-modal-close:hover {
    background: #e5e5ea;
}

@media (max-width: 640px) {
    .nav { padding: 0 16px; }
    .hero-section { padding: 20px 16px; }
    .tracking-card { padding: 6px; }
    .btn-track-primary { padding: 10px 16px; font-size: 13px; }
    .footer-compact { padding: 12px 16px; flex-direction: column; gap: 8px; }
    .info-grid { grid-template-columns: 1fr; gap: 8px; }
}

/* ======== WIZARD ======== */
.wizard-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Helvetica Neue', sans-serif;
}

.wizard-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 40px;
    max-width: 460px;
    width: 100%;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    text-align: center;
    animation: slideUp 0.4s ease forwards;
}

.wizard-icon {
    width: 64px; height: 64px;
    background: #f5f5f7;
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    font-size: 32px;
    color: rgb(var(--color-primary));
    margin: 0 auto 24px;
}

.wizard-card h2 {
    font-size: 28px;
    font-weight: 700;
    color: #1d1d1f;
    margin-bottom: 12px;
    letter-spacing: -0.5px;
}

.wizard-card p {
    font-size: 15px;
    color: #6e6e73;
    margin-bottom: 32px;
    line-height: 1.5;
}

.wizard-options {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.w-btn {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    border: 1px solid rgba(0,0,0,0.08);
    background: #ffffff;
    border-radius: 16px;
    cursor: pointer;
    text-align: left;
    transition: all 0.2s;
}

.w-btn:hover {
    border-color: rgba(0,0,0,0.15);
    background: #fbfbfd;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}

.w-btn i {
    font-size: 28px;
}

.w-text strong {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #1d1d1f;
    margin-bottom: 4px;
}

.w-text span {
    font-size: 13px;
    color: #86868b;
}

/* ======== PORTAL SUPIR ======== */
.driver-landing-page {
    min-height: 100dvh;
    background: #f5f5f7;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Helvetica Neue', sans-serif;
}

.dl-container {
    background: #ffffff;
    border-radius: 28px;
    padding: 40px;
    max-width: 500px;
    width: 100%;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    text-align: center;
}

.dl-header {
    margin-bottom: 40px;
}

.dl-header .logo-box {
    margin: 0 auto 20px;
}

.dl-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #1d1d1f;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}

.dl-header p {
    font-size: 15px;
    color: #6e6e73;
}

.dl-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 30px;
}

.dl-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 20px;
    text-decoration: none;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
    text-align: left;
}

.dl-card:hover {
    border-color: rgba(0,0,0,0.15);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}

.dl-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.dl-card h3 {
    font-size: 16px;
    font-weight: 600;
    color: #1d1d1f;
    margin-bottom: 4px;
}

.dl-card p {
    font-size: 13px;
    color: #86868b;
    margin: 0;
}

.dl-arrow {
    position: absolute;
    right: 20px;
    font-size: 20px;
    color: #aeaeb2;
    transition: transform 0.2s;
}

.dl-card:hover .dl-arrow {
    transform: translateX(4px);
    color: #1d1d1f;
}

.dl-reset {
    background: none;
    border: none;
    color: #86868b;
    font-size: 14px;
    cursor: pointer;
    text-decoration: underline;
    transition: color 0.2s;
}

.dl-reset:hover {
    color: #1d1d1f;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 480px) {
    .wizard-card { padding: 30px 20px; }
}

/* ======== DRIVER THEME & ANIMATION ======== */
.driver-landing-page {
    background: #0f172a;
    color: white;
}
.dark-card {
    background: #1e293b;
    border: 1px solid #334155;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
}
.glow-text {
    color: #f8fafc;
    text-shadow: 0 0 15px rgba(255,255,255,0.2);
}

/* Flappy Game Styles */
.flappy-game-area {
   position: fixed;
   top: 0; left: 0;
   background: linear-gradient(to bottom, #0f172a, #1e293b);
   cursor: pointer;
   user-select: none;
   -webkit-tap-highlight-color: transparent;
   overflow: hidden;
}
.score-board {
   position: absolute;
   top: 30px; left: 30px; right: 30px;
   display: flex; justify-content: space-between;
   z-index: 10; color: rgba(255,255,255,0.9);
   font-weight: bold; pointer-events: none;
}
.game-overlay {
   position: absolute; inset: 0; z-index: 20;
   display: flex; flex-direction: column; align-items: center; justify-content: center;
   background: rgba(0,0,0,0.6); color: white; font-weight: bold;
}
.game-over h3 {
   color: #ef4444; text-shadow: 0 4px 6px rgba(0,0,0,0.5);
}
.restart-btn {
   background: #3b82f6; border: none; border-radius: 30px; color: white; cursor: pointer; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.3);
}
.restart-btn:active { transform: scale(0.95); }

.flappy-truck {
   position: absolute; left: 40px; width: 40px; height: 40px;
   z-index: 5; display: flex; align-items: center; justify-content: center;
   font-size: 48px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5));
}

.pipe {
   position: absolute;
   background: linear-gradient(to right, #22c55e, #16a34a);
   border: 3px solid #14532d; z-index: 4;
}
.pipe-top { top: 0; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; }
.pipe-bottom { border-top-left-radius: 8px; border-top-right-radius: 8px; }

.road {
   position: absolute; bottom: 0; left: 0; right: 0;
   background: #0f172a; z-index: 6;
}
.road.moving::after {
   content: ''; position: absolute; top: 8px; left: 0; right: -200px; height: 6px;
   background: repeating-linear-gradient(90deg, transparent, transparent 30px, #475569 30px, #475569 60px);
   animation: roadMove 0.4s infinite linear;
}

/* Standby Box & Ping */
.standby-box {
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid #475569;
    padding: 24px;
    border-radius: 20px;
    color: #f8fafc;
    position: relative;
    overflow: hidden;
}
.ping-container {
    position: relative;
    width: 64px; height: 64px;
    margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
}
.ping-ring {
    position: absolute;
    width: 100%; height: 100%;
    background: #22c55e;
    border-radius: 50%;
    animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
    opacity: 0.7;
}
.wa-icon {
    position: relative;
    font-size: 40px;
    color: #22c55e;
    z-index: 2;
    background: #1e293b;
    border-radius: 50%;
    width: 50px; height: 50px;
    display: flex; align-items: center; justify-content: center;
}

@keyframes bounceTruck {
   0% { transform: translateY(0px) rotate(0deg); }
   100% { transform: translateY(-3px) rotate(-1deg); }
}
@keyframes windMove {
   0% { transform: translateX(20px); opacity: 0; }
   50% { opacity: 1; }
   100% { transform: translateX(-40px); opacity: 0; }
}
@keyframes roadMove {
   0% { transform: translateX(0); }
   100% { transform: translateX(-30px); }
}
@keyframes cloudMove {
   0% { transform: translateX(0); opacity: 0; }
   10% { opacity: 1; }
   90% { opacity: 1; }
   100% { transform: translateX(-300px); opacity: 0; }
}
@keyframes ping {
   75%, 100% { transform: scale(1.8); opacity: 0; }
}

</style>
