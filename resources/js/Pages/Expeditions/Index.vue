<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExpeditionHandshakeModal from '@/Components/ExpeditionHandshakeModal.vue';

const props = defineProps({
    expeditions: Array,
});

const isModalOpen = ref(false);
const editingExpedition = ref(null);
const isQrModalOpen = ref(false);
const selectedQrExpeditionId = ref(null);

const openQrModal = (exp) => {
    selectedQrExpeditionId.value = exp.id;
    isQrModalOpen.value = true;
};

const form = useForm({
    name: '',
    domain_slug: '',
    address: '',
    phone: '',
    is_active: true,
});

const openCreateModal = () => {
    editingExpedition.value = null;
    form.reset();
    isModalOpen.value = true;
};

const openEditModal = (expedition) => {
    editingExpedition.value = expedition;
    form.name = expedition.name;
    form.domain_slug = expedition.domain_slug || '';
    form.address = expedition.address || '';
    form.phone = expedition.phone || '';
    form.is_active = Boolean(expedition.is_active);
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    editingExpedition.value = null;
};

const submitForm = () => {
    if (editingExpedition.value) {
        form.put(route('expeditions.update', editingExpedition.value.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('expeditions.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteExpedition = (expedition) => {
    if (confirm(`Apakah Anda yakin ingin menghapus ekspedisi "${expedition.name}"? Semua data terkait akan ikut terhapus!`)) {
        router.delete(route('expeditions.destroy', expedition.id));
    }
};
</script>

<template>
    <Head title="Manajemen Ekspedisi (Agregator)" />

    <AdminLayout title="Manajemen Ekspedisi">
        <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="ri-community-line text-primary"></i>
                        Daftar Ekspedisi Mitra
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Kelola seluruh ekspedisi yang terdaftar dalam platform Agregator Anda.
                    </p>
                </div>
                <button
                    @click="openCreateModal"
                    class="flex items-center gap-2 bg-primary text-white px-4 py-2.5 rounded-xl font-medium shadow-md shadow-primary/20 hover:bg-primary/90 transition"
                >
                    <i class="ri-add-line text-lg"></i>
                    Tambah Ekspedisi
                </button>
            </div>

            <!-- Flash Message Banner -->
            <div v-if="$page.props.flash && $page.props.flash.success" class="bg-emerald-50 text-emerald-800 p-4 rounded-2xl border border-emerald-200 flex items-start gap-3 shadow-sm">
                <i class="ri-checkbox-circle-fill text-xl text-emerald-600 shrink-0 mt-0.5"></i>
                <div>
                    <h4 class="font-bold text-sm">Berhasil</h4>
                    <p class="text-xs mt-0.5 leading-relaxed text-emerald-700">{{ $page.props.flash.success }}</p>
                </div>
            </div>

            <!-- List Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="exp in expeditions"
                    :key="exp.id"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition"
                >
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xl">
                                    {{ exp.name.charAt(0) }}
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800 text-lg leading-tight">{{ exp.name }}</h3>
                                    <span 
                                        :class="exp.is_active ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200'"
                                        class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full border mt-1"
                                    >
                                        {{ exp.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 space-y-2 text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <i class="ri-phone-line text-gray-400"></i>
                                <span>{{ exp.phone || 'Tidak ada nomor telp' }}</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="ri-map-pin-line text-gray-400 mt-0.5"></i>
                                <span class="line-clamp-2">{{ exp.address || 'Tidak ada alamat' }}</span>
                            </div>
                            <div v-if="exp.domain_slug" class="flex items-center gap-2">
                                <i class="ri-link text-gray-400"></i>
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ exp.domain_slug }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
                        <button
                            @click="openQrModal(exp)"
                            class="px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition flex items-center gap-1"
                            title="Tampilkan QR Kemitraan Armada Supir"
                        >
                            <i class="ri-qr-code-line"></i> QR Kemitraan
                        </button>
                        <button
                            @click="openEditModal(exp)"
                            class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition flex items-center gap-1"
                        >
                            <i class="ri-edit-line"></i> Edit
                        </button>
                        <button
                            @click="deleteExpedition(exp)"
                            class="px-3 py-1.5 text-xs font-semibold text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition flex items-center gap-1"
                        >
                            <i class="ri-delete-bin-line"></i> Hapus
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="expeditions.length === 0" class="text-center py-12 bg-white rounded-2xl border border-gray-100">
                <i class="ri-community-line text-5xl text-gray-300"></i>
                <h3 class="mt-2 text-base font-semibold text-gray-800">Belum Ada Ekspedisi</h3>
                <p class="text-sm text-gray-500">Mulai tambahkan ekspedisi pertama Anda ke dalam sistem.</p>
            </div>
        </div>

        <!-- Modal Create/Edit -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-xl border border-gray-100">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800">
                        {{ editingExpedition ? 'Edit Ekspedisi' : 'Tambah Ekspedisi Baru' }}
                    </h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Ekspedisi *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Contoh: Jepara Express"
                            class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Slug Domain (Opsional)</label>
                        <input
                            v-model="form.domain_slug"
                            type="text"
                            placeholder="jepara-express"
                            class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        />
                        <p class="text-xs text-gray-400 mt-1">Akan digunakan jika fitur subdomain diaktifkan nanti.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            placeholder="08123456789"
                            class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Kantor</label>
                        <textarea
                            v-model="form.address"
                            rows="3"
                            placeholder="Alamat lengkap ekspedisi..."
                            class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            id="is_active"
                            class="rounded border-gray-300 text-primary focus:ring-primary"
                        />
                        <label for="is_active" class="text-sm font-medium text-gray-700">Ekspedisi Aktif</label>
                    </div>

                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-xl transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-xl shadow-md shadow-primary/20 transition disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal QR Kemitraan Ekspedisi -->
        <ExpeditionHandshakeModal
            :show="isQrModalOpen"
            :expedition-id="selectedQrExpeditionId"
            @close="isQrModalOpen = false"
        />
    </AdminLayout>
</template>
