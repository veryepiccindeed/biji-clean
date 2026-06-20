<template>
    <div class="min-h-screen bg-[var(--color-bg-primary)] text-[var(--color-text-primary)] theme-farmer font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative flex flex-col lg:flex-row min-h-screen">
            <div class="pointer-events-none absolute inset-0 opacity-60" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(45, 106, 79, 0.30), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.10), transparent 60%);"></div>

            <Sidebar :current-route-name="currentRouteName" />

            <main class="relative z-10 flex-1 overflow-y-auto px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10">
                <header class="mb-10 flex items-start justify-between gap-6">
                    <div>
                        <p class="mb-1 text-[11px] uppercase tracking-wider text-[var(--color-text-secondary)]">Kelola Batch</p>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Rincian Batch</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">Rincian batch kopi anda sebelum memulai alur pengajuan, survey BIJI, dan pemasangan IoT.</p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img :src="logoUrl" alt="Logo BIJI" class="pointer-events-none w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mr-10 lg:-mt-10">
                    </div>
                </header>

                <!-- Error / Success Banner -->
                <transition name="fade">
                    <div v-if="apiMessage" class="mt-6 px-4 py-3 rounded-lg text-[13px] font-bold"
                        :class="apiSuccess ? 'bg-[#d1fae5] text-[#065f46] border border-[#6ee7b7]' : 'bg-[#fee2e2] text-[#991b1b] border border-[#fca5a5]'">
                        {{ apiMessage }}
                    </div>
                </transition>

                <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <div class="xl:col-span-8 space-y-6">
                        <div class="rounded-2xl border border-[var(--color-border-light)] bg-[var(--color-bg-card)] p-6">
                            <div class="flex items-start justify-between gap-4">
                              
                            </div>

                            <div class="space-y-6">

                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-[var(--color-role-farmer-primary)] bg-[var(--color-role-farmer-bg)] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--color-role-farmer-light)]">{{ batch.stage }}</span>
                                        <span class="rounded-full border border-[var(--color-warning)] bg-[var(--color-bg-hover)] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--color-warning)]">{{ batch.surveyStatus }}</span>
                                    </div>
                                    <router-link :to="{ name: 'landing' }" class="inline-flex items-center gap-2 rounded-full border border-[var(--color-border-input)] bg-[var(--color-bg-primary)] px-3 py-2 text-[12px] font-semibold text-[var(--color-text-primary)] transition hover:bg-[var(--color-bg-hover)]">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-[var(--color-text-primary)]">
                                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M14.06 5.94l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        Ubah
                                    </router-link>
                                </div>
                                 

                                <div class="rounded-2xl border border-[var(--color-border-light)] bg-[var(--color-bg-primary)] p-6">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-[11px] uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">Identitas Batch</p>
                                            <h3 class="mt-1 text-[18px] font-semibold text-[var(--color-text-primary)]">Data dasar yang dipakai untuk verifikasi dan manajemen</h3>
                                        </div>
                                        <span class="text-[12px] text-[var(--color-text-secondary)] font-mono">{{ batch.code }}</span>
                                    </div>

                                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                                        <div v-for="item in identityItems" :key="item.label" class="rounded-xl border border-[var(--color-border-light)] bg-[var(--color-bg-card)] p-4">
                                            <p class="text-[11px] uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">{{ item.label }}</p>
                                            <p class="mt-2 text-[14px] font-semibold text-[var(--color-text-primary)]">{{ item.value }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-[var(--color-border-light)] bg-[var(--color-bg-primary)] p-6">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-[11px] uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">Foto Batch</p>
                                            <h3 class="mt-1 text-[18px] font-semibold text-[var(--color-text-primary)]">Dokumentasi wajib dan angle verifikasi</h3>
                                        </div>
                                        <span class="text-[12px] text-[var(--color-text-secondary)]">{{ batch.photoCount }} foto</span>
                                    </div>

                                    <div class="mt-5 grid gap-3 md:grid-cols-3">
                                        <div v-for="photo in batch.photos" :key="photo.id" class="rounded-2xl border border-[var(--color-border-light)] bg-[var(--color-bg-primary)] p-3">
                                            <div class="relative h-36 overflow-hidden rounded-xl border border-[var(--color-border-input)] bg-cover bg-center" :style="{ backgroundImage: `url(${photo.url})` }">
                                                <div class="absolute inset-x-0 bottom-3 flex justify-center">
                                                    <span class="rounded-full bg-black/50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--color-warning)]">{{ photo.note || 'Foto Batch' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <PhotoWarning class="mt-5" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="xl:col-span-4 space-y-6">
                        <div class="rounded-2xl border border-[var(--color-border-light)] bg-[var(--color-bg-card)] p-6 xl:sticky xl:top-6">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[var(--color-text-secondary)]">Manajemen Status</p>
                                    <h3 class="mt-1 text-[18px] font-semibold text-[var(--color-text-primary)]">Aksi cepat untuk batch ini</h3>
                                </div>
                            </div>

                            <div class="mt-5 space-y-3 rounded-2xl border border-[var(--color-border-light)] bg-[var(--color-bg-primary)] p-4">
                                <div class="flex items-center justify-between gap-3 text-[13px]">
                                    <span class="text-[var(--color-text-secondary)]">Survey BIJI</span>
                                    <span class="text-[var(--color-warning)] font-semibold">{{ batch.surveyStatus }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-[13px]">
                                    <span class="text-[var(--color-text-secondary)]">Foto wajib</span>
                                    <span class="text-[var(--color-success)] font-semibold">{{ batch.photoCount >= 3 ? 'Lengkap' : 'Kurang' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-[13px]">
                                    <span class="text-[var(--color-text-secondary)]">IoT</span>
                                    <span class="text-[var(--color-text-primary)] font-semibold">{{ batch.iotStatus }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-[13px]">
                                    <span class="text-[var(--color-text-secondary)]">Log terakhir</span>
                                    <span class="text-[var(--color-text-primary)] font-semibold">{{ batch.lastLog }}</span>
                                </div>
                            </div>

                            <div class="mt-5 space-y-3">
                                <button
                                    @click="submitSurvey"
                                    :disabled="isLoading || batch.status !== 'draft'"
                                    :class="batch.status === 'draft' && !isLoading ? 'bg-[var(--color-role-farmer-primary)] hover:bg-[#356d58]' : 'bg-[var(--color-border-input)] cursor-not-allowed'"
                                    class="w-full rounded-xl px-4 py-3 text-[13px] font-semibold text-white transition font-bold"
                                >
                                    {{ isLoading ? 'Memproses...' : (batch.status === 'draft' ? 'Ajukan Survey BIJI' : 'Sudah Diajukan') }}
                                </button>
                            </div>

                            <div class="mt-5 rounded-2xl border border-[var(--color-warning)] bg-[var(--color-bg-hover)] p-4">
                                <p class="text-[13px] font-semibold text-[var(--color-warning)]">Sensor dan lokasi bukan input manual</p>
                                <p class="mt-2 text-[12px] leading-5 text-[var(--color-text-secondary)]">
                                    Koordinat GPS, suhu, kelembapan, dan data IoT akan diisi oleh tim BIJI setelah pengajuan disetujui dan survey lapangan dilakukan.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '../../components/farmer/Sidebar.vue';
import PhotoWarning from '../../components/farmer/PhotoWarning.vue';
import axios from 'axios';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'farmer.batch-detail');
const logoUrl = '/assets/logo-fix.png';

const batchId = route.query.id;
const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const batch = reactive({
    id: '',
    code: '-',
    name: 'Memuat...',
    stage: 'Draft Survey',
    surveyStatus: 'Belum Diajukan',
    iotStatus: 'Belum Terpasang',
    photoCount: 0,
    lastLog: '-',
    origin: '-',
    varietas: '-',
    tanggalPanen: '-',
    jumlahKarung: '-',
    beratBasah: '-',
    prosesAwal: '-',
    targetKadarAir: '-',
    surveyDate: '-',
    status: 'draft',
    photos: []
});

const identityItems = computed(() => [
    { label: 'Origin / Lokasi', value: batch.origin },
    { label: 'Varietas', value: batch.varietas },
    { label: 'Tanggal Panen', value: batch.tanggalPanen },
    { label: 'Jumlah Karung', value: batch.jumlahKarung },
    { label: 'Berat Basah', value: batch.beratBasah },
    { label: 'Proses Awal', value: batch.prosesAwal },
    { label: 'Target Kadar Air', value: batch.targetKadarAir },
    { label: 'Jadwal Survey', value: batch.surveyDate }
]);

const fetchBatchDetail = async () => {
    if (!batchId) return;
    try {
        const { data } = await axios.get(`/api/v1/farmer/batches/${batchId}`);
        if (data.success && data.data?.batch) {
            const b = data.data.batch;
            batch.id = b.id;
            batch.code = b.code;
            batch.name = b.name || `Batch ${b.code}`;
            batch.stage = b.stage || 'Draft Survey';
            batch.surveyStatus = b.survey_status || 'Belum Diajukan';
            batch.iotStatus = b.iot_status || 'Belum Terpasang';
            batch.photoCount = b.photo_count || 0;
            batch.lastLog = b.last_log_at ? new Date(b.last_log_at).toLocaleDateString('id-ID') : '-';
            batch.origin = b.kebun ? `${b.kebun}, ${b.desa}, ${b.kecamatan}` : '-';
            batch.varietas = b.varietas || b.variety || '-';
            batch.tanggalPanen = b.tanggal_panen || '-';
            batch.jumlahKarung = b.jumlah_karung ? `${b.jumlah_karung} karung` : '-';
            batch.beratBasah = b.berat_basah ? `${b.berat_basah} kg` : '-';
            batch.prosesAwal = b.proses_awal || '-';
            batch.targetKadarAir = b.kadar_air_target || '-';
            batch.status = b.status || 'draft';
            batch.photos = b.photos?.items ?? [];
        }
    } catch (err) {
        console.error(err);
    }
};

const submitSurvey = async () => {
    if (isLoading.value || batch.status !== 'draft') return;
    isLoading.value = true;
    apiMessage.value = '';

    try {
        const { data } = await axios.post(`/api/v1/farmer/batches/${batchId}/submit-survey`);
        if (data.success) {
            apiSuccess.value = true;
            apiMessage.value = data.message ?? 'Pengajuan survey berhasil dikirim!';
            batch.status = 'survey_pending';
            batch.surveyStatus = 'Menunggu Jadwal Survey';
            await fetchBatchDetail();
        }
    } catch (err) {
        apiSuccess.value = false;
        const res = err?.response?.data;
        apiMessage.value = res?.message ?? 'Terjadi kesalahan saat mengajukan survey.';
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchBatchDetail();
});
</script>

<style>
canvas {
    width: 100% !important;
    height: 100% !important;
}
</style>
