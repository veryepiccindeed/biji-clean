<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-farmer font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex lg:flex-row flex-col">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(45, 106, 79, 0.30), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.10), transparent 60%);"></div>

            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10 relative z-10 overflow-y-auto">
                <header class="flex justify-between items-start mb-10 gap-6">
                    <div>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Selamat Datang, {{ userName }}!</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">{{ dashboardIntro }}</p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="Logo"
                            class="w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mt-10 lg:-mr-10 pointer-events-none"
                        >
                    </div>
                </header>

                <PhoneNumberWarning />

                <div v-if="!isOnline" class="mb-6 rounded-2xl border border-[#E8A838] bg-[#2A2118] px-4 py-3 text-[13px] text-[#F5EFE6]">
                    <div class="flex items-center gap-3">
                        <span class="text-[#E8A838]">☁︎</span>
                        <div>
                            <p class="font-semibold">Mode Offline — Data akan disimpan otomatis</p>
                            <p class="text-[12px] text-[#D8C8B5]">Saat koneksi kembali, sinkronisasi batch dan log berjalan otomatis.</p>
                        </div>
                    </div>
                </div>

                <div id="aksi-cepat" class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
                    <div class="lg:col-span-3 bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8 text-center w-full">
                        <h2 class="text-xl font-bold font-sans mb-3">Daftarkan Batch Baru</h2>
                        <p class="text-[13px] text-[#A89880] leading-6">Buat batch baru dari varietas, tanggal panen, lokasi, elevasi, dan kode karung otomatis.</p>
                        <router-link :to="{ name: 'farmer.new-batch' }" class="mt-6 inline-flex items-center justify-center px-6 py-3 bg-[#2D6A4F] rounded-xl text-[13px] font-semibold text-white transition hover:bg-[#356d58]">Buka Form Batch</router-link>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-10">
                    <div class="bg-[#1C1813] border border-[#2E241C] p-6 rounded-2xl">
                        <p class="text-[13px] text-[#A89880]">Sedang Diproses</p>
                        <h3 class="text-[32px] font-sans font-bold mt-2 text-[#52B788]">{{ stats.processing }}</h3>
                        <div class="mt-4 text-[11px] text-[#A89880]">Batch masih dalam tahap pengolahan</div>
                    </div>
                    <div class="bg-[#1C1813] border border-[#2E241C] p-6 rounded-2xl">
                        <p class="text-[13px] text-[#A89880]">Siap Ekspor</p>
                        <h3 class="text-[32px] font-sans font-bold mt-2 text-[#D4AF5A]">{{ stats.readyForExporter }}</h3>
                        <div class="mt-4 text-[11px] text-[#A89880]">Batch dengan log cukup untuk dibuka ke eksportir</div>
                    </div>
                    <div class="bg-[#1C1813] border border-[#2E241C] p-6 rounded-2xl">
                        <p class="text-[13px] text-[#A89880]">Log Hari Ini</p>
                        <h3 class="text-[32px] font-sans font-bold mt-2 text-[#F5EFE6]">{{ stats.todayLogs }}</h3>
                        <div class="mt-4 text-[11px] text-[#A89880]">Suhu, kelembapan, dan catatan aktivitas</div>
                    </div>
                    <div class="bg-[#1C1813] border border-[#2E241C] p-6 rounded-2xl border-l-4 border-l-[#2D6A4F]">
                        <p class="text-[13px] text-[#A89880]">Reputasi</p>
                        <h3 class="text-[32px] font-sans font-bold mt-2 text-[#52B788]">{{ stats.reputation }}</h3>
                        <div class="mt-4 text-[11px] text-[#A89880]">Konsistensi profil dan batch</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-10 gap-6 mb-10">
                    <div class="lg:col-span-6 bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                        <div class="flex justify-between items-center mb-8 gap-4">
                            <div>
                                <h2 class="text-xl font-bold font-sans">Progress Harian</h2>
                                <p class="text-[13px] text-[#A89880] mt-1">Ringkasan target operasional harian sebelum batch dipromosikan ke status Ready.</p>
                                <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-medium uppercase tracking-[0.16em]">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-[#2D6A4F] bg-[#1A3D2E] px-3 py-1 text-[#52B788]">
                                        <span class="h-2 w-2 rounded-full bg-[#52B788]"></span>
                                        Prioritas rendah
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-full border border-[#B8902A] bg-[#2D2210] px-3 py-1 text-[#D4AF5A]">
                                        <span class="h-2 w-2 rounded-full bg-[#D4AF5A]"></span>
                                        Prioritas sedang
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-full border border-[#2E241C] bg-[#14110D] px-3 py-1 text-[#A89880]">
                                        <span class="h-2 w-2 rounded-full bg-[#A89880]"></span>
                                        Draft
                                    </span>
                                </div>
                            </div>
                            <div class="shrink-0 whitespace-nowrap rounded-full border border-[#2D6A4F] bg-[#1A3D2E] px-4 py-2 text-[12px] text-[#52B788] font-semibold">
                                {{ progressLabel }}
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div v-for="item in progressCards" :key="item.title" class="flex h-full flex-col rounded-2xl border border-[#2E241C] bg-[#0F0D0B] p-4" :class="item.cardClass">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[#A89880]">{{ item.title }}</p>
                                <h3 class="mt-3 font-serif text-[20px] font-bold text-[#F5EFE6]">{{ item.value }}</h3>
                                <p class="mt-2 text-[13px] leading-6 text-[#A89880]">{{ item.description }}</p>
                                <div class="mt-auto pt-4">
                                    <div class="h-2 rounded-full bg-[#2A2118] overflow-hidden">
                                        <div class="h-full rounded-full" :class="item.barClass" :style="{ width: item.progress + '%' }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 rounded-2xl border border-[#2E241C] bg-[#14110D] p-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-sans mt-1 text-[16px] font-semibold text-[#F5EFE6]">Langkah Berikutnya</h3>
                                     <p class="text-[13px] text-[#A89880] mt-1">Aksi yang paling dekat untuk menutup progres hari ini</p>
                                </div>
                                <span class="text-[12px] text-[#A89880] font-mono">{{ nextActionLabel }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-medium uppercase tracking-[0.16em]">
                                <span class="inline-flex items-center gap-2 rounded-full border border-[#2D6A4F] bg-[#1A3D2E] px-3 py-1 text-[#52B788]">
                                    <span class="h-2 w-2 rounded-full bg-[#52B788]"></span>
                                    Prioritas
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border border-[#B8902A] bg-[#2D2210] px-3 py-1 text-[#D4AF5A]">
                                    <span class="h-2 w-2 rounded-full bg-[#D4AF5A]"></span>
                                    Hari ini
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border border-[#2E241C] bg-[#14110D] px-3 py-1 text-[#A89880]">
                                    <span class="h-2 w-2 rounded-full bg-[#A89880]"></span>
                                    Berikutnya
                                </span>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div v-for="step in nextActions" :key="step.title" class="rounded-xl border border-[#2E241C] bg-[#0F0D0B] px-4 py-3" :class="step.cardClass">
                                    <p class="text-[14px] font-semibold text-[#F5EFE6]" :class="step.titleClass">{{ step.title }}</p>
                                    <p class="mt-1 text-[12px] leading-5 text-[#A89880]">{{ step.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-4 bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                        <div class="flex items-center justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-xl font-bold font-sans">Status & Log Harian</h2>
                                <p class="text-[13px] text-[#A89880] mt-1">Status batch aktif dan catatan log terbaru dalam satu panel.</p>
                            </div>
                            <span class="text-[12px] text-[#A89880] font-mono">{{ selectedBatch.code }}</span>
                        </div>

                        <div class="rounded-2xl border border-[#2E241C] bg-[#0F0D0B] p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[#A89880]">Batch Aktif</p>
                                    <p class="mt-2 text-[15px] font-semibold text-[#F5EFE6]">{{ selectedBatch.name }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span :class="batchStatusClass(selectedBatch.status)" class="rounded-full border px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]">{{ selectedBatch.status }}</span>
                                    <router-link :to="{ name: 'farmer.batch-detail', query: { id: selectedBatch.id } }" class="rounded-full border border-[#4A3728] bg-[#2A2118] px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#F5EFE6] transition hover:bg-[#33271D]">
                                        Detail & Manajemen
                                    </router-link>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2">
                                <div class="rounded-xl border border-[#2E241C] bg-[#1C1813] px-3 py-3 text-center">
                                    <p class="text-[11px] text-[#A89880]">Health</p>
                                    <p class="mt-1 text-[13px] font-semibold text-[#4CAF7D]">{{ selectedBatch.health }}</p>
                                </div>
                                <div class="rounded-xl border border-[#2E241C] bg-[#1C1813] px-3 py-3 text-center">
                                    <p class="text-[11px] text-[#A89880]">Suhu</p>
                                    <p class="mt-1 text-[13px] font-semibold text-[#F5EFE6]">{{ selectedBatch.temperature }}°C</p>
                                </div>
                                <div class="rounded-xl border border-[#2E241C] bg-[#1C1813] px-3 py-3 text-center">
                                    <p class="text-[11px] text-[#A89880]">Kelembapan</p>
                                    <p class="mt-1 text-[13px] font-semibold text-[#F5EFE6]">{{ selectedBatch.humidity }}%</p>
                                </div>
                            </div>

                            <div class="mt-5">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[#A89880]">Tren 5 Log Terakhir</p>
                                    <span class="text-[11px] text-[#A89880]">Suhu / Kelembapan</span>
                                </div>
                                <div class="mt-10 h-28 rounded-xl border border-[#2E241C] bg-[#14110D] px-4 py-5 flex items-end gap-2">
                                    <div v-for="bar in miniChartBars" :key="bar.label" class="flex-1 flex flex-col items-center gap-2">
                                        <div class="w-full rounded-t-lg bg-[#2A2118] overflow-hidden" style="height: 88px; display: flex; align-items: end;">
                                            <div class="w-full rounded-t-lg" :class="bar.class" :style="{ height: bar.height + '%' }"></div>
                                        </div>
                                        <span class="text-[10px] text-[#A89880]">{{ bar.label }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 space-y-3">
                                <div v-for="log in dailyLogs" :key="log.id" class="rounded-xl border border-[#2E241C] bg-[#14110D] px-4 py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-[14px] font-semibold text-[#F5EFE6]">{{ log.title }}</p>
                                        <p class="text-[12px] text-[#A89880] mt-1">{{ log.subtitle }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[13px] text-[#F5EFE6] font-semibold">{{ log.value }}</p>
                                        <p :class="log.noteClass" class="text-[11px] mt-1">{{ log.note }}</p>
                                    </div>
                                </div>
                            </div>

                            <router-link :to="{ name: 'farmer.batch-detail', query: { id: selectedBatch.id } }" class="mt-5 block text-center w-full py-3 bg-[#2A2118] border border-[#4A3728] rounded-xl text-[13px] hover:bg-[#33271D] transition text-[#F5EFE6] font-bold">
                                Buka Log Harian
                            </router-link>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import PhoneNumberWarning from '../../components/farmer/PhoneNumberWarning.vue';
import Sidebar from '../../components/farmer/Sidebar.vue';
import axios from 'axios';
import { useUserProfileStore } from '../../stores/userProfile';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'farmer.dashboard');

const logoUrl = '/assets/logo-fix.png';
const dashboardIntro = 'Ringkasan operasional petani berdasarkan alur golden path: batch, log, dan kesiapan akuisisi.';
const userProfileStore = useUserProfileStore();
const userName = computed(() => userProfileStore.profile.fullName || 'User');

const stats = ref({
    processing: 0,
    readyForExporter: 0,
    todayLogs: 0,
    reputation: '0/100'
});

const latestBatches = ref([]);
const dailyLogs = ref([]);
const activeBatch = ref(null);
const nextActions = ref([]);
const progressCards = ref([]);
const miniChartBars = ref([]);

const isOnline = ref(navigator.onLine);

const updateOnlineStatus = () => {
    isOnline.value = navigator.onLine;
};

const fetchDashboardData = async () => {
    try {
        const { data } = await axios.get('/api/v1/farmer/dashboard');
        if (data.success && data.data) {
            const payload = data.data;
            
            stats.value = {
                processing: payload.stats?.processing ?? 0,
                readyForExporter: payload.stats?.ready_for_exporter ?? 0,
                todayLogs: payload.stats?.today_logs ?? 0,
                reputation: `${payload.stats?.reputation ?? 100}/${payload.stats?.reputation_max ?? 100}`
            };

            const batchesList = Array.isArray(payload.latest_batches) ? payload.latest_batches : (payload.latest_batches?.data || []);
            latestBatches.value = batchesList;
            dailyLogs.value = (payload.daily_logs ?? []).map(log => ({
                id: log.id,
                title: log.title || 'Log IoT',
                subtitle: log.created_at ? new Date(log.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '',
                value: log.value_display || `${log.temperature}°C / ${log.humidity}%`,
                note: log.note || 'Normal',
                noteClass: log.note_color === 'red' ? 'text-[#E53E3E]' : 'text-[#4CAF7D]'
            }));

            if (payload.active_batch) {
                activeBatch.value = payload.active_batch;
            }

            // Map progress
            if (payload.progress?.items) {
                progressCards.value = payload.progress.items.map(item => ({
                    title: item.label || item.key,
                    value: item.value || '100%',
                    description: item.description || '',
                    progress: item.progress_percent || 100,
                    cardClass: item.completed ? 'border-l-4 border-l-[#52B788]' : 'border-l-4 border-l-[#D4AF5A]',
                    barClass: item.completed ? 'bg-[#52B788]' : 'bg-[#D4AF5A]'
                }));
            }

            // Map next actions
            if (payload.next_actions) {
                nextActions.value = payload.next_actions.map(act => ({
                    title: act.title || 'Tugas Baru',
                    description: act.description || '',
                    cardClass: act.priority === 'high' ? 'border-l-4 border-l-[#52B788]' : 'border-l-4 border-l-[#A89880]',
                    titleClass: act.priority === 'high' ? 'text-[#52B788]' : 'text-[#A89880]'
                }));
            }

            // Map trend log to mini chart bars
            if (payload.log_trend?.data_points) {
                miniChartBars.value = payload.log_trend.data_points.map((pt, idx) => ({
                    label: pt.label || `H${idx + 1}`,
                    height: Math.min(100, Math.max(10, pt.temperature * 2)), // Scale for visualization
                    class: pt.temperature > 35 ? 'bg-[#D4AF5A]' : 'bg-[#52B788]'
                }));
            }
        }
    } catch (err) {
        console.error(err);
    }
};

const selectedBatch = computed(() => activeBatch.value || latestBatches.value[0] || {
    code: '-',
    name: 'Tidak ada batch aktif',
    status: 'none',
    health: '-',
    temperature: 0,
    humidity: 0
});

const progressLabel = computed(() => {
    const completed = progressCards.value.filter((item) => item.progress === 100).length;
    return `${completed}/${progressCards.value.length} tercapai`;
});

const nextActionLabel = computed(() => `${nextActions.value.length} aksi`);

onMounted(() => {
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    fetchDashboardData();
});

onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
});

const batchStatusClass = (status) => {
    if (status === 'ready' || status === 'Ready') {
        return 'border-[#2D6A4F] bg-[#1A3D2E] text-[#52B788]';
    }

    if (status === 'processing' || status === 'Proses') {
        return 'border-[#B8902A] bg-[#2D2210] text-[#D4AF5A]';
    }

    return 'border-[#2E241C] bg-[#14110D] text-[#A89880]';
};
</script>

<style>
canvas {
    width: 100% !important;
    height: 100% !important;
}
</style>
