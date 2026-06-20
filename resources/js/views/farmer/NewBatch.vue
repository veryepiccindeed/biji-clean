<template>
    <!-- 1. Kunci tinggi layar utama (h-screen & overflow-hidden) agar layout dashboard rigid -->
    <div class="min-h-screen lg:h-screen w-full bg-[var(--color-bg-primary)] text-[var(--color-text-primary)] theme-farmer font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative flex flex-col lg:flex-row h-full w-full">
            <div class="pointer-events-none absolute inset-0 opacity-60" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(45, 106, 79, 0.30), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.10), transparent 60%);"></div>

            <Sidebar :current-route-name="currentRouteName" />

            <!-- 2. Tag <main> ini yang sekarang memegang kendali scrollbar halaman -->
            <main class="relative z-10 flex-1 overflow-y-auto px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10">
                <header class="mb-10 flex items-start justify-between gap-6">
                    <div>
                        <p class="mb-1 text-[11px] uppercase tracking-wider text-[var(--color-text-secondary)]">Farmer Module</p>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Registrasi Batch Baru</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">
                            Ikuti alur pengajuan petani: isi data panen, unggah foto batch wajib, lengkapi lokasi dasar, lalu BIJI akan melakukan survey dan pemasangan perangkat IoT setelah pengajuan disetujui.
                        </p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="Logo"
                            class="pointer-events-none w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mt-10 lg:-mr-10"
                        >
                    </div>
                </header>

                <!-- Error / Success Banner -->
                <transition name="fade">
                    <div v-if="apiMessage" class="mt-6 px-4 py-3 rounded-lg text-[13px] font-bold"
                        :class="apiSuccess ? 'bg-[#d1fae5] text-[#065f46] border border-[#6ee7b7]' : 'bg-[#fee2e2] text-[#991b1b] border border-[#fca5a5]'">
                        {{ apiMessage }}
                    </div>
                </transition>

                <!-- 3. Tambahkan items-start di grid agar kolom kanan tidak dipaksa ikut melar setinggi form kiri -->
                <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-12 items-start">
                    <div class="xl:col-span-8 space-y-6">
                        <div class="rounded-2xl border border-[var(--color-border-light)] bg-[var(--color-bg-card)] p-6">

                            <div class="space-y-6">
                                <div>
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-[11px] uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">Langkah Pertama</p>
                                            <h3 class="mt-1 text-[18px] font-semibold text-[var(--color-text-primary)]">Data Panen</h3>
                                        </div>
                                        <span class="text-[12px] text-[var(--color-text-secondary)] font-mono">{{ batchCodePreview }}</span>
                                    </div>

                                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                                        <label v-for="field in harvestFields" :key="field.key" class="block">
                                            <span class="mb-2 block text-[12px] text-[var(--color-text-secondary)]">{{ field.label }}</span>
                                            <component
                                                :is="field.as"
                                                v-model="form[field.key]"
                                                :type="field.type || 'text'"
                                                :rows="field.rows"
                                                :placeholder="field.placeholder"
                                                class="w-full rounded-xl border border-[var(--color-border-light)] bg-[var(--color-bg-primary)] px-4 py-3 text-[14px] text-[var(--color-text-primary)] outline-none transition focus:border-[var(--color-role-farmer-light)] focus:ring-2 focus:ring-[rgba(82,183,136,0.12)]"
                                            >
                                                <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
                                            </component>
                                        </label>

                                        <div class="md:col-span-2 rounded-xl border border-dashed border-[var(--color-border-input)] bg-[var(--color-bg-primary)] p-4">
                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-[12px] font-semibold text-[var(--color-text-primary)]">Foto Batch <span class="ml-1 text-[var(--color-error)]">(WAJIB)</span></p>
                                                    <p class="mt-1 text-[12px] leading-5 text-[var(--color-text-secondary)]">Unggah minimal satu foto kondisi batch/panen sebagai syarat pengajuan. Foto ini akan dipakai BIJI untuk verifikasi awal saat survey lapangan.</p>
                                                </div>
                                            </div>
                                            <PhotoWarning class="mt-4" />
                                            <label class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-xl border border-[var(--color-border-light)] bg-[var(--color-bg-card)] px-4 py-8 text-center transition hover:bg-[var(--color-bg-hover)]">
                                                <input
                                                    ref="batchPhotoInput"
                                                    type="file"
                                                    accept="image/*"
                                                    multiple
                                                    class="hidden"
                                                    @change="handleBatchPhotoChange"
                                                >
                                                <span class="text-[13px] font-semibold text-[var(--color-text-primary)]">Klik untuk unggah foto batch</span>
                                                <span class="mt-2 text-[12px] text-[var(--color-text-secondary)]">JPEG, PNG, atau WebP</span>
                                                <span class="mt-3 text-[12px] text-[var(--color-role-farmer-light)]">{{ batchPhotoSummary }}</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-6 border-t border-[var(--color-border-light)]">
                                    <div>
                                        <p class="text-[11px] uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">Langkah Kedua</p>
                                        <h3 class="mt-1 text-[18px] font-semibold text-[var(--color-text-primary)]">Lokasi & Jejak Kebun</h3>
                                    </div>

                                    <div class="mt-4 rounded-2xl border border-[var(--color-border-input)] bg-[var(--color-bg-primary)] p-4">
                                        <p class="text-[13px] font-semibold text-[var(--color-text-primary)]">Data teknis akan diverifikasi BIJI</p>
                                        <p class="mt-2 text-[12px] leading-5 text-[var(--color-text-secondary)]">
                                            Koordinat GPS, suhu, dan data sensor/IoT tidak diisi manual oleh petani. Setelah pengajuan disetujui, tim BIJI akan melakukan survey lokasi, mengukur kondisi batch, lalu memasang perangkat IoT untuk pendampingan berikutnya.
                                        </p>
                                    </div>

                                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                                        <label v-for="field in locationFields" :key="field.key" class="block md:col-span-1" :class="field.full ? 'md:col-span-2' : ''">
                                            <span class="mb-2 block text-[12px] text-[var(--color-text-secondary)]">{{ field.label }}</span>
                                            <component
                                                :is="field.as"
                                                v-model="form[field.key]"
                                                :type="field.type || 'text'"
                                                :rows="field.rows"
                                                :placeholder="field.placeholder"
                                                class="w-full rounded-xl border border-[var(--color-border-light)] bg-[var(--color-bg-primary)] px-4 py-3 text-[14px] text-[var(--color-text-primary)] outline-none transition focus:border-[var(--color-role-farmer-light)] focus:ring-2 focus:ring-[rgba(82,183,136,0.12)]"
                                            >
                                                <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
                                            </component>
                                        </label>
                                    </div>
                                </div>

                                <div class="pt-6 border-t border-[var(--color-border-light)]">
                                    <div>
                                        <p class="text-[11px] uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">Langkah Ketiga</p>
                                        <h3 class="mt-1 text-[18px] font-semibold text-[var(--color-text-primary)]">Proses Batch</h3>
                                    </div>

                                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                                        <label v-for="field in processFields" :key="field.key" class="block" :class="field.full ? 'md:col-span-2' : ''">
                                            <span class="mb-2 block text-[12px] text-[var(--color-text-secondary)]">{{ field.label }}</span>
                                            <component
                                                :is="field.as"
                                                v-model="form[field.key]"
                                                :type="field.type || 'text'"
                                                :rows="field.rows"
                                                :placeholder="field.placeholder"
                                                class="w-full rounded-xl border border-[var(--color-border-light)] bg-[var(--color-bg-primary)] px-4 py-3 text-[14px] text-[var(--color-text-primary)] outline-none transition focus:border-[var(--color-role-farmer-light)] focus:ring-2 focus:ring-[rgba(82,183,136,0.12)]"
                                            >
                                                <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
                                            </component>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Pasang xl:sticky di level kolom ini -->
                    <div class="xl:col-span-4 xl:sticky xl:top-6 space-y-6">
                        <!-- Batas max-h dinaikkan menjadi 6rem (~96px) dari tinggi layar agar pas di resolusi lo tanpa memicu scrollbar prematur -->
                        <div class="rounded-2xl border border-[var(--color-border-light)] bg-[var(--color-bg-card)] p-6 max-h-[calc(100vh-6rem)] overflow-y-auto">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">Pratinjau Batch</p>
                                    <h3 class="mt-1 text-[18px] font-semibold text-[var(--color-text-primary)]">{{ batchCodePreview }}</h3>
                                </div>
                                <span class="rounded-full border border-[var(--color-role-farmer-primary)] bg-[var(--color-role-farmer-bg)] px-3 py-1 text-[11px] font-semibold text-[var(--color-role-farmer-light)]">Draft</span>
                            </div>

                            <div class="mt-5 space-y-3 rounded-2xl border border-[var(--color-border-light)] bg-[var(--color-bg-primary)] p-4">
                                <div class="flex items-center justify-between gap-3 text-[13px]">
                                    <span class="text-[var(--color-text-secondary)]">Varietas</span>
                                    <span class="text-[var(--color-text-primary)]">{{ form.varietas || 'Belum diisi' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-[13px]">
                                    <span class="text-[var(--color-text-secondary)]">Lokasi</span>
                                    <span class="text-[var(--color-text-primary)]">{{ form.kebun || 'Belum diisi' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-[13px]">
                                    <span class="text-[var(--color-text-secondary)]">Metode</span>
                                    <span class="text-[var(--color-text-primary)]">{{ form.prosesAwal || 'Belum diisi' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-[13px]">
                                    <span class="text-[var(--color-text-secondary)]">Target Air</span>
                                    <span class="text-[var(--color-text-primary)]">{{ form.kadarAirTarget || 'Belum diisi' }}</span>
                                </div>
                            </div>

                            <div class="mt-5">
                                <div class="flex items-center justify-between text-[12px] text-[var(--color-text-secondary)]">
                                    <span>Kelengkapan</span>
                                    <span>{{ completionLabel }}</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-[var(--color-bg-hover)]">
                                    <div class="h-full rounded-full bg-[var(--color-role-farmer-light)]" :style="{ width: `${completion}%` }"></div>
                                </div>
                            </div>

                            <div class="mt-5">
                                <div v-for="item in checklistItems" :key="item.label" class="flex items-center justify-between gap-3 rounded-xl border border-[var(--color-border-light)] bg-[var(--color-bg-primary)] px-4 py-3">
                                    <span class="text-[13px] text-[var(--color-text-secondary)]">{{ item.label }}</span>
                                    <span class="text-[12px] font-semibold" :class="item.done ? 'text-[var(--color-success)]' : 'text-[var(--color-warning)]'">{{ item.done ? 'Siap' : 'Belum' }}</span>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-3">
                                <button
                                    type="button"
                                    @click="submitBatch"
                                    :disabled="isLoading"
                                    class="rounded-xl border border-[var(--color-border-input)] bg-[var(--color-bg-hover)] px-4 py-3 text-[13px] font-semibold text-[var(--color-text-primary)] transition hover:bg-[#33271D] flex items-center justify-center gap-2"
                                >
                                    <svg v-if="isLoading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                    </svg>
                                    {{ isLoading ? 'Menyimpan...' : 'Simpan Draft' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Sidebar from '../../components/farmer/Sidebar.vue';
import PhotoWarning from '../../components/farmer/PhotoWarning.vue';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const currentRouteName = computed(() => route?.name ?? 'farmer.new-batch');
const logoUrl = '/assets/logo-fix.png';

const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);
const batchPhotos = ref([]);

const form = reactive({
    varietas: '',
    tanggalPanen: '',
    metodePanen: 'Petik merah',
    jumlahKarung: '',
    beratBasah: '',
    kebun: '',
    desa: '',
    kecamatan: '',
    prosesAwal: 'Penjemuran',
    kadarAirTarget: '12%',
    statusJemur: 'Sedang berjalan',
    catatan: '',
    batchPhotoNames: []
});

const batchPhotoInput = ref(null);

const harvestFields = [
    { key: 'varietas', label: 'Varietas Kopi', placeholder: 'Contoh: Arabika Toraja', as: 'input' },
    { key: 'tanggalPanen', label: 'Tanggal Panen', placeholder: '', as: 'input', type: 'date' },
    {
        key: 'metodePanen',
        label: 'Metode Panen',
        as: 'select',
        options: ['Petik merah', 'Petik campur', 'Selektif']
    },
    { key: 'jumlahKarung', label: 'Jumlah Karung', placeholder: 'Misal: 18', as: 'input', type: 'number' },
    { key: 'beratBasah', label: 'Berat Basah (kg)', placeholder: 'Misal: 560', as: 'input', type: 'number' }
];

const locationFields = [
    { key: 'kebun', label: 'Nama Kebun / Blok', placeholder: 'Contoh: Kebun Hulu 01', as: 'input' },
    { key: 'desa', label: 'Desa / Kelurahan', placeholder: 'Contoh: Buntu Batu', as: 'input' },
    { key: 'kecamatan', label: 'Kecamatan', placeholder: 'Contoh: Baraka', as: 'input' }
];

const processFields = [
    {
        key: 'prosesAwal',
        label: 'Proses Awal',
        as: 'select',
        options: ['Penjemuran', 'Fermentasi', 'Honey', 'Natural']
    },
    {
        key: 'kadarAirTarget',
        label: 'Target Kadar Air',
        as: 'select',
        options: ['11%', '12%', '13%']
    },
    {
        key: 'statusJemur',
        label: 'Status Jemur',
        as: 'select',
        options: ['Sedang berjalan', 'Menunggu cuaca', 'Selesai']
    },
    { key: 'catatan', label: 'Catatan Batch', placeholder: 'Tambahkan catatan singkat tentang kondisi panen, cuaca, atau penanganan awal.', as: 'textarea', rows: 4, full: true }
];

const batchCodePreview = computed(() => {
    const varietyCode = (form.varietas || 'FARM').replace(/[^A-Za-z0-9]/g, '').slice(0, 4).toUpperCase();
    const dateCode = form.tanggalPanen ? form.tanggalPanen.replace(/-/g, '').slice(2) : '2605';

    return `BJI-${varietyCode || 'FARM'}-${dateCode}`;
});

const checklistItems = computed(() => [
    { label: 'Data panen diisi', done: Boolean(form.varietas && form.tanggalPanen && form.jumlahKarung) },
    { label: 'Lokasi lengkap', done: Boolean(form.kebun && form.desa && form.kecamatan) },
    { label: 'Proses batch ditentukan', done: Boolean(form.prosesAwal && form.kadarAirTarget) },
    { label: 'Foto batch terunggah', done: form.batchPhotoNames.length >= 3 }
]);

const completion = computed(() => {
    const fields = [form.varietas, form.tanggalPanen, form.jumlahKarung, form.beratBasah, form.kebun, form.desa, form.kecamatan, form.catatan, form.batchPhotoNames.length >= 3];
    const filled = fields.filter(Boolean).length;

    return Math.round((filled / fields.length) * 100);
});

const completionLabel = computed(() => `${completion.value}% draft terisi`);

const batchPhotoSummary = computed(() => {
    if (form.batchPhotoNames.length === 0) {
        return 'Belum ada file dipilih';
    }

    if (form.batchPhotoNames.length === 1) {
        return '1 foto dipilih';
    }

    return `${form.batchPhotoNames.length} foto dipilih`;
});

const handleBatchPhotoChange = (event) => {
    const files = Array.from(event.target.files || []);
    batchPhotos.value = files;
    form.batchPhotoNames = files.map((file) => file.name);
};

const submitBatch = async () => {
    if (isLoading.value) return;
    isLoading.value = true;
    apiMessage.value = '';

    try {
        const response = await axios.post('/api/v1/farmer/batches', {
            varietas: form.varietas,
            tanggal_panen: form.tanggalPanen,
            metode_panen: form.metodePanen,
            jumlah_karung: form.jumlahKarung,
            berat_basah: form.beratBasah,
            kebun: form.kebun,
            desa: form.desa,
            kecamatan: form.kecamatan,
            proses_awal: form.prosesAwal,
            kadar_air_target: form.kadarAirTarget,
            status_jemur: form.statusJemur === 'Sedang berjalan' ? 'Sedang berjalan' : (form.statusJemur === 'Selesai' ? 'Selesai' : 'Belum mulai'),
            catatan: form.catatan
        });

        if (response.data?.success && response.data?.data?.batch) {
            const batchId = response.data.data.batch.id;

            // Upload photos if any
            if (batchPhotos.value.length > 0) {
                const formData = new FormData();
                batchPhotos.value.forEach((file) => {
                    formData.append('photos[]', file);
                });
                batchPhotos.value.forEach((_, idx) => {
                    formData.append(`notes[${idx}]`, '');
                });

                await axios.post(`/api/v1/farmer/batches/${batchId}/photos`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
            }

            apiSuccess.value = true;
            apiMessage.value = 'Batch berhasil disimpan! Mengalihkan...';
            setTimeout(() => {
                router.push({ name: 'farmer.dashboard' });
            }, 800);
        }
    } catch (err) {
        apiSuccess.value = false;
        const res = err?.response?.data;
        apiMessage.value = res?.message ?? 'Terjadi kesalahan saat menyimpan batch.';
    } finally {
        isLoading.value = false;
    }
};
</script>
