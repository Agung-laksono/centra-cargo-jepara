import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { Fullscreen } from '@boengli/capacitor-fullscreen';

import { App as CapacitorApp } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';

// Auto-redirect jika dibuka dari dalam aplikasi mobile native APK dan berada di root /
if (typeof window !== 'undefined' && Capacitor.isNativePlatform()) {
    if (window.location.pathname === '/' || window.location.pathname === '') {
        window.location.replace('/app');
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Mencegah context menu (long press memunculkan copy/translate) kecuali di input/textarea
window.addEventListener('contextmenu', function (e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        return;
    }
    e.preventDefault();
});

// ==========================================
// MATIKAN BROWSER AUTOCOMPLETE SECARA GLOBAL
// ==========================================
const disableAutocompleteGlobally = () => {
    if (typeof document === 'undefined') return;

    // Matikan di semua elemen form
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.setAttribute('autocomplete', 'off');
    });

    // Matikan di semua input dan textarea
    const inputs = document.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), textarea');
    inputs.forEach(el => {
        // Jangan timpa password field jika memiliki autocomplete spesifik
        if (el.type === 'password' && (el.getAttribute('autocomplete') === 'current-password' || el.getAttribute('autocomplete') === 'new-password')) {
            return;
        }
        el.setAttribute('autocomplete', 'off');
        el.setAttribute('autocorrect', 'off');
        el.setAttribute('autocapitalize', 'off');
        el.setAttribute('spellcheck', 'false');
        el.setAttribute('data-lpignore', 'true');
        el.setAttribute('data-form-type', 'other');
    });
};

if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', disableAutocompleteGlobally);

    // Tangkap saat input difokuskan
    document.addEventListener('focusin', (e) => {
        const target = e.target;
        if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')) {
            if (target.type === 'password' && (target.getAttribute('autocomplete') === 'current-password' || target.getAttribute('autocomplete') === 'new-password')) {
                return;
            }
            target.setAttribute('autocomplete', 'off');
            target.setAttribute('autocorrect', 'off');
            target.setAttribute('autocapitalize', 'off');
            target.setAttribute('spellcheck', 'false');
            target.setAttribute('data-lpignore', 'true');
            target.setAttribute('data-form-type', 'other');
        }
    }, true);

    // Observer untuk mendeteksi elemen baru yang di-render secara dinamis
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.addedNodes.length > 0) {
                disableAutocompleteGlobally();
                break;
            }
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
}

// Tampilkan loading overlay agar tidak terlihat nge-hang
const showDeepLinkLoader = () => {
    if (!document.getElementById('dl-loader')) {
        const loader = document.createElement('div');
        loader.id = 'dl-loader';
        loader.innerHTML = `
            <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:#ffffff;z-index:999999;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:sans-serif;">
                <div style="width:48px;height:48px;border:4px solid #f3f4f6;border-top-color:#22c55e;border-radius:50%;animation:dl-spin 1s linear infinite;margin-bottom:20px;"></div>
                <h3 style="color:#111827;font-size:18px;font-weight:600;margin:0 0 8px;">Membuka Surat Jalan...</h3>
                <p style="color:#6b7280;font-size:14px;margin:0;">Mohon tunggu sebentar</p>
                <style>@keyframes dl-spin{to{transform:rotate(360deg)}}</style>
            </div>
        `;
        document.body.appendChild(loader);
    }
};

const hideDeepLinkLoader = () => {
    const loader = document.getElementById('dl-loader');
    if (loader) {
        loader.remove();
    }
};

// Fungsi helper untuk handle URL
const handleDeepLink = (dataUrl) => {
    if (!dataUrl) {
        hideDeepLinkLoader();
        return;
    }
    let targetPath = '';
    if (dataUrl.startsWith('jeparacargo://')) {
        targetPath = dataUrl.replace('jeparacargo://', '/');
    } else if (dataUrl.includes('/supir/')) {
        try {
            const urlObj = new URL(dataUrl);
            targetPath = urlObj.pathname + urlObj.search;
        } catch (e) {}
    }

    if (targetPath.includes('/supir/')) {
        const currentPath = window.location.pathname + window.location.search;
        if (currentPath !== targetPath) {
            try {
                router.visit(targetPath, {
                    onFinish: () => hideDeepLinkLoader(),
                });
            } catch (e) {
                window.location.href = targetPath;
            }
        } else {
            hideDeepLinkLoader();
        }
    } else {
        hideDeepLinkLoader();
    }
};

try {
    // 1. Listen untuk event saat app sedang berjalan (warm start)
    CapacitorApp.addListener('appUrlOpen', data => {
        if (data && data.url && data.url.includes('/supir/')) {
            showDeepLinkLoader();
        }
        handleDeepLink(data.url);
    });

    // 2. Cek apakah app dibuka dari keadaan tertutup (cold start)
    CapacitorApp.getLaunchUrl().then(ret => {
        if (ret && ret.url) {
            if (ret.url.includes('/supir/')) {
                showDeepLinkLoader();
            }
            // Beri sedikit jeda agar proses inisialisasi Inertia selesai
            setTimeout(() => {
                handleDeepLink(ret.url);
            }, 500);
        }
    });

    // 3. Disable tombol back fisik/navigasi utama HP secara global
    CapacitorApp.addListener('backButton', ({ canGoBack }) => {
        // Blok tombol back utama Android agar tidak menutup/keluar dari aplikasi secara tak sengaja
        console.log('Hardware back button disabled globally');
    });
} catch (e) {
    console.log('Capacitor App plugin not running on native/supported env');
}

// 4. Mencegah tombol back browser / gesture swipe back HP pada web browser
if (typeof window !== 'undefined') {
    // Kunci riwayat history browser dengan pushState
    const preventBrowserBack = () => {
        window.history.pushState(null, '', window.location.href);
    };

    // Jalankan saat load awal
    preventBrowserBack();

    // Tangkap setiap kali user menekan tombol Back di HP / browser
    window.addEventListener('popstate', (e) => {
        // Cegah mundur, dorong state kembali ke halaman sekarang
        preventBrowserBack();
    });
}

// Sinkronisasi warna tema dinamis (CSS Variables)
const applyTheme = (hexColor) => {
    if (!hexColor || typeof hexColor !== 'string' || !hexColor.startsWith('#')) return;
    const cleanHex = hexColor.replace('#', '');
    let r, g, b;
    if (cleanHex.length === 3) {
        r = parseInt(cleanHex.substring(0, 1) + cleanHex.substring(0, 1), 16);
        g = parseInt(cleanHex.substring(1, 2) + cleanHex.substring(1, 2), 16);
        b = parseInt(cleanHex.substring(2, 3) + cleanHex.substring(2, 3), 16);
    } else {
        r = parseInt(cleanHex.substring(0, 2), 16);
        g = parseInt(cleanHex.substring(2, 4), 16);
        b = parseInt(cleanHex.substring(4, 6), 16);
    }
    if (isNaN(r) || isNaN(g) || isNaN(b)) return;

    const lr = Math.min(255, r + 60);
    const lg = Math.min(255, g + 60);
    const lb = Math.min(255, b + 60);

    const dr = Math.max(0, r - 50);
    const dg = Math.max(0, g - 50);
    const db = Math.max(0, b - 50);

    document.documentElement.style.setProperty('--color-primary', `${r} ${g} ${b}`);
    document.documentElement.style.setProperty('--color-primary-light', `${lr} ${lg} ${lb}`);
    document.documentElement.style.setProperty('--color-primary-dark', `${dr} ${dg} ${db}`);
};

const syncThemeForPage = (page) => {
    if (!page) return;
    const component = page.component || '';
    const isPublic = component === 'Welcome' || component.startsWith('Auth/');

    if (isPublic) {
        // Halaman publik (Welcome, Login) selalu manut ke aplikasi utama
        applyTheme(page.props?.app?.theme || '#8B5A2B');
    } else {
        // Halaman operasional/admin
        if (page.props?.auth?.is_super_admin) {
            // Super Admin manut ke aplikasi utama
            applyTheme(page.props?.app?.theme || '#8B5A2B');
        } else if (page.props?.company?.theme) {
            // Ekspedisi menggunakan warna tema masing-masing
            applyTheme(page.props.company.theme);
        } else {
            applyTheme(page.props?.app?.theme || '#8B5A2B');
        }
    }
};

router.on('navigate', (event) => {
    syncThemeForPage(event.detail.page);
    setTimeout(disableAutocompleteGlobally, 50);
});

router.on('finish', () => {
    setTimeout(disableAutocompleteGlobally, 50);
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Terapkan warna tema saat pertama kali dirender
        syncThemeForPage(props.initialPage);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
