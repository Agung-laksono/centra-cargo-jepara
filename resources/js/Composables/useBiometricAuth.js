import { ref } from 'vue';
import axios from 'axios';
import localforage from 'localforage';

const BIOMETRIC_STORAGE_KEY = 'cargo_biometric_device';

// Helper to convert base64 / string to Uint8Array and back
function stringToUint8Array(str) {
    const encoder = new TextEncoder();
    return encoder.encode(str);
}

function bufferToBase64(buffer) {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return window.btoa(binary);
}

function base64ToBuffer(base64) {
    try {
        let str = base64.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4 !== 0) {
            str += '=';
        }
        const binary = window.atob(str);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    } catch (e) {
        return stringToUint8Array(base64).buffer;
    }
}

export function useBiometricAuth() {
    const isSupported = ref(false);
    const isAuthenticating = ref(false);
    const isRegistering = ref(false);
    const registeredUser = ref(null);
    const errorMessage = ref(null);

    // Periksa apakah perangkat dan browser mendukung WebAuthn / Kunci Layar
    const checkSupport = async () => {
        if (typeof window === 'undefined' || !window.PublicKeyCredential) {
            isSupported.value = false;
            return false;
        }

        try {
            const available = await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            isSupported.value = Boolean(available);
            return isSupported.value;
        } catch (e) {
            isSupported.value = false;
            return false;
        }
    };

    // Muat data biometrik yang sudah terdaftar di HP ini
    const loadRegisteredDevice = async () => {
        try {
            const data = await localforage.getItem(BIOMETRIC_STORAGE_KEY);
            if (data && data.credentialId && data.authToken) {
                registeredUser.value = data.user || null;
                return data;
            }
        } catch (e) {
            console.warn('Gagal memuat biometric local storage:', e);
        }
        registeredUser.value = null;
        return null;
    };

    // Daftarkan kunci layar perangkat saat ini untuk user yang sedang aktif
    const registerDevice = async (user) => {
        errorMessage.value = null;
        isRegistering.value = true;

        const supported = await checkSupport();
        if (!supported) {
            isRegistering.value = false;
            throw new Error('Perangkat ini belum mengaktifkan kunci layar (PIN/Pola/Sidik Jari) atau browser belum mendukung.');
        }

        try {
            const challenge = window.crypto.getRandomValues(new Uint8Array(32));
            const userIdBytes = stringToUint8Array(String(user.id || '1'));

            const publicKeyOptions = {
                challenge: challenge,
                rp: {
                    name: 'Jepara Cargo Mobile',
                    id: window.location.hostname,
                },
                user: {
                    id: userIdBytes,
                    name: user.email || 'user@jeparacargo.com',
                    displayName: user.name || 'Supir / Staf Jepara Cargo',
                },
                pubKeyCredParams: [
                    { type: 'public-key', alg: -7 },  // ES256
                    { type: 'public-key', alg: -257 }, // RS256
                ],
                authenticatorSelection: {
                    authenticatorAttachment: 'platform', // Kunci layar bawaan HP (Sidik Jari, Wajah, Pola, PIN)
                    userVerification: 'required',
                    requireResidentKey: false,
                },
                timeout: 60000,
            };

            // Munculkan dialog keamanan sistem HP Android
            const credential = await navigator.credentials.create({
                publicKey: publicKeyOptions,
            });

            if (!credential || !credential.id) {
                throw new Error('Kunci layar dibatalkan.');
            }

            // Simpan pendaftaran ke backend
            const response = await axios.post('/biometric/register', {
                credential_id: credential.id,
                device_name: navigator.userAgent.substring(0, 100),
            });

            if (response.data.status === 'success') {
                const deviceData = {
                    credentialId: credential.id,
                    authToken: response.data.auth_token,
                    user: response.data.user || user,
                    registeredAt: new Date().toISOString(),
                };

                await localforage.setItem(BIOMETRIC_STORAGE_KEY, deviceData);
                registeredUser.value = deviceData.user;
                return response.data;
            } else {
                throw new Error(response.data.message || 'Gagal mendaftarkan biometrik.');
            }
        } catch (err) {
            errorMessage.value = err.message || 'Verifikasi kunci layar gagal.';
            throw err;
        } finally {
            isRegistering.value = false;
        }
    };

    // Masuk dengan Kunci Layar / Sidik Jari
    const loginWithBiometric = async () => {
        errorMessage.value = null;
        isAuthenticating.value = true;

        const deviceData = await loadRegisteredDevice();
        if (!deviceData) {
            isAuthenticating.value = false;
            throw new Error('Belum ada akun yang dihubungkan dengan kunci layar di perangkat ini.');
        }

        try {
            const challenge = window.crypto.getRandomValues(new Uint8Array(32));
            const rawIdBuffer = base64ToBuffer(deviceData.credentialId);

            const getOptions = {
                challenge: challenge,
                allowCredentials: [
                    {
                        type: 'public-key',
                        id: rawIdBuffer,
                        transports: ['internal'],
                    },
                ],
                userVerification: 'required', // Wajibkan sidik jari/wajah/pola/PIN
                timeout: 60000,
            };

            // Munculkan dialog Android Kunci Layar
            const assertion = await navigator.credentials.get({
                publicKey: getOptions,
            });

            if (!assertion) {
                throw new Error('Verifikasi kunci layar dibatalkan.');
            }

            // Kirim token autentikasi ke backend
            const res = await axios.post('/biometric/login', {
                credential_id: deviceData.credentialId,
                auth_token: deviceData.authToken,
            });

            if (res.data.status === 'success') {
                // Arahkan ke rute tujuan
                window.location.href = res.data.redirect_url || '/dashboard';
                return res.data;
            } else {
                throw new Error(res.data.message || 'Autentikasi gagal.');
            }
        } catch (err) {
            errorMessage.value = err.message || 'Verifikasi kunci layar gagal atau dibatalkan.';
            throw err;
        } finally {
            isAuthenticating.value = false;
        }
    };

    // Hapus tautan kunci layar
    const removeBiometric = async () => {
        try {
            await axios.post('/biometric/disable');
        } catch (e) {}
        await localforage.removeItem(BIOMETRIC_STORAGE_KEY);
        registeredUser.value = null;
    };

    return {
        isSupported,
        isAuthenticating,
        isRegistering,
        registeredUser,
        errorMessage,
        checkSupport,
        loadRegisteredDevice,
        registerDevice,
        loginWithBiometric,
        removeBiometric,
    };
}
