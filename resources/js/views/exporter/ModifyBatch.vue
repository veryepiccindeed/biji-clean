<template>
    <div class="min-h-screen lg:h-screen w-full bg-[#0F0D0B] text-[#F5EFE6] theme-exporter font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative flex flex-col lg:flex-row h-full w-full">
            <div class="absolute inset-0 pointer-events-none opacity-60" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(75, 56, 50, 0.35), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.12), transparent 60%);"></div>

            <Sidebar :current-route-name="currentRouteName" />

            <main class="relative z-10 flex-1 overflow-y-auto px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10">
                <header class="flex items-start justify-between gap-6">
                    <div>
                        <p class="mb-1 text-[11px] uppercase tracking-wider text-[#A89880]">Eksportir / Modifikasi Batch</p>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Modifikasi Batch</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">
                            Perbarui detail spesifikasi, harga, atau catatan tambahan untuk batch <span class="text-[#F5EFE6] font-mono">{{ batchId }}</span>.
                        </p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="Logo BIJI"
                            class="pointer-events-none w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mt-10 lg:-mr-10"
                        >
                    </div>
                </header>

                <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-12 items-start">
                    <div class="xl:col-span-8 space-y-6">
                        <div class="rounded-2xl border border-[#2E241C] bg-[#1C1813] p-8">
                            <div class="space-y-8">
                                <div>
                                    <h3 class="text-[18px] font-semibold text-[#F5EFE6] mb-6">Detail Informasi Batch</h3>
                                    <div class="grid gap-5 md:grid-cols-2">
                                        <div v-for="field in fields" :key="field.key" :class="field.full ? 'md:col-span-2' : ''">
                                            <label class="text-[13px] text-[#A89880]" :for="field.key">{{ field.label }}</label>
                                            <component
                                                :is="field.as"
                                                :id="field.key"
                                                v-model="form[field.key]"
                                                :type="field.type || 'text'"
                                                :rows="field.rows"
                                                :placeholder="field.placeholder"
                                                :disabled="field.key !== 'harga' && field.key !== 'catatan'"
                                                class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] transition disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
                                            </component>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Action -->
                    <div class="xl:col-span-4 xl:sticky xl:top-6">
                        <div class="rounded-2xl border border-[#2E241C] bg-[#1C1813] p-8 shadow-xl">
                            <h3 class="text-[18px] font-bold text-[#F5EFE6] mb-6">Status Perubahan</h3>

                            <div v-if="apiMessage" class="mb-4 px-4 py-3 rounded-lg text-[13px] font-bold"
                                :class="apiSuccess ? 'bg-[#d1fae5] text-[#065f46] border border-[#6ee7b7]' : 'bg-[#fee2e2] text-[#991b1b] border border-[#fca5a5]'">
                                {{ apiMessage }}
                            </div>

                            <div class="space-y-4 mb-8">
                                <div class="bg-[#0F0D0B] p-4 rounded-xl border border-[#2E241C]">
                                    <p class="text-[11px] text-[#A89880] uppercase tracking-widest mb-1">ID Batch</p>
                                    <p class="font-mono text-[14px] text-[#F5EFE6]">{{ batchId }}</p>
                                </div>
                                <div class="bg-[#2D2210] p-4 rounded-xl border border-[#B8902A]/20">
                                    <p class="text-[11px] text-[#D4AF5A] uppercase tracking-widest mb-1">Status Saat Ini</p>
                                    <p class="text-[14px] text-[#F5EFE6] font-bold uppercase tracking-widest">Live di Marketplace</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <button @click="saveBatch" :disabled="isLoading" class="w-full py-4 bg-[#8B6355] text-white rounded-xl font-bold text-[14px] hover:bg-[#a17a6e] transition shadow-lg shadow-[#8B6355]/20 disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ isLoading ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                </button>
                                <button @click="$router.back()" class="w-full py-3 bg-transparent border border-[#4A3728] text-[#A89880] rounded-xl text-[13px] font-medium hover:text-[#F5EFE6] hover:bg-[#2A2118] transition">
                                    Batalkan
                                </button>
                            </div>

                            <p class="mt-6 text-[11px] text-[#5C4F42] leading-relaxed text-center italic">
                                *Perubahan pada data inti akan dicatat sebagai entri baru dalam audit trail blockchain.
                            </p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Sidebar from '../../components/exporter/Sidebar.vue';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const currentRouteName = computed(() => route?.name ?? 'exporter.modify-batch');
const logoUrl = '/assets/logo-fix.png';
const batchId = computed(() => route.query.id || 'BJ-TRJ-24-001');

const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const form = reactive({
    varietas: '',
    origin: '',
    berat: '',
    harga: '',
    proses: '',
    grade: '',
    catatan: ''
});

const fields = [
    { key: 'varietas', label: 'Varietas Kopi', as: 'input' },
    { key: 'origin', label: 'Origin / Daerah Asal', as: 'input' },
    { key: 'berat', label: 'Total Berat (Kg)', as: 'input', type: 'number' },
    { key: 'harga', label: 'Harga per Kg (Rp)', as: 'input', type: 'number' },
    {
        key: 'proses',
        label: 'Metode Pengolahan',
        as: 'select',
        options: ['Full Wash', 'Semi Wash', 'Natural', 'Honey', 'Anaerob']
    },
    {
        key: 'grade',
        label: 'Kualitas / Grade',
        as: 'select',
        options: ['Grade 1', 'Grade 2', 'Specialty', 'Commercial']
    },
    { key: 'catatan', label: 'Catatan & Deskripsi', as: 'textarea', rows: 4, full: true }
];

const fetchBatchDetails = async () => {
    const id = route.query.id;
    if (!id) return;
    try {
        const { data } = await axios.get(`/api/v1/exporter/batches/${id}`);
        if (data.success && data.data) {
            const b = data.data;
            form.varietas = b.variety || '-';
            form.origin = b.origin?.location || b.kebun || '-';
            form.berat = String(b.volume?.total_kg || b.weight_kg || '100');
            form.harga = String(b.price || '');
            form.proses = b.proses || 'Full Wash';
            form.grade = b.grade || 'Grade 1';
            form.catatan = b.description || '';
        }
    } catch (err) {
        console.error('Failed to load batch details for edit:', err);
        apiMessage.value = 'Gagal memuat detail batch.';
        apiSuccess.value = false;
    }
};

const saveBatch = async () => {
    isLoading.value = true;
    apiMessage.value = '';
    const id = route.query.id;
    try {
        const response = await axios.patch(`/api/v1/exporter/batches/${id}`, {
            price: Number(form.harga),
            description: form.catatan
        });
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Perubahan berhasil disimpan!';
            setTimeout(() => {
                router.push({ name: 'exporter.batch-saya-detail', query: { id } });
            }, 1500);
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal menyimpan perubahan.';
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchBatchDetails();
});
</script>
