<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-exporter font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(75, 56, 50, 0.35), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.12), transparent 60%);"></div>

            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10 relative z-10 overflow-y-auto">
                <header class="flex justify-between items-start mb-10 gap-6">
                    <div>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Selamat Datang!</h1>
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

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <div class="bg-[#1C1813] border border-[#2E241C] p-6 rounded-2xl">
                        <p class="text-[13px] text-[#A89880]">Total Batch Diakuisisi</p>
                        <h3 class="text-[32px] font-sans font-bold mt-2 text-[#F5EFE6]">{{ stats.totalBatchDiakuisisi }}</h3>
                        <div class="mt-4 text-[11px] text-[#A89880]">{{ stats.totalBatchCaption }}</div>
                    </div>
                    <div class="bg-[#1C1813] border border-[#2E241C] p-6 rounded-2xl">
                        <p class="text-[13px] text-[#A89880]">Sertifikat Terbit</p>
                        <h3 class="text-[32px] font-sans font-bold mt-2 text-[#4CAF7D]">{{ stats.sertifikatTerbit }}</h3>
                        <div class="mt-4 flex items-center gap-1 text-[11px] text-[#4CAF7D]">
                            <span>{{ stats.sertifikatGrowth }}</span>
                            <span class="text-[#A89880]">bulan ini</span>
                        </div>
                    </div>
                    <div class="bg-[#1C1813] border border-[#2E241C] p-6 rounded-2xl relative overflow-hidden">
                        <p class="text-[13px] text-[#A89880]">Perlu Tindakan</p>
                        <h3 class="text-[32px] font-sans font-bold mt-2 text-[#D94F4F]">{{ stats.perluTindakan }}</h3>
                        <div class="mt-4 text-[11px] text-[#D94F4F] font-bold">{{ stats.perluTindakanDetail }}</div>
                    </div>
                    <div class="bg-[#1C1813] border border-[#2E241C] p-6 rounded-2xl border-l-4 border-l-[#E8A838]">
                        <p class="text-[13px] text-[#A89880]">Batch Siap Diakuisisi</p>
                        <h3 class="text-[32px] font-sans font-bold mt-2 text-[#E8A838]">{{ stats.batchSiap }}</h3>
                        <div class="mt-4 text-[11px] text-[#A89880]">{{ stats.batchSiapCaption }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-10 gap-6 mb-10">
                    <div class="lg:col-span-6 bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                        <div class="flex justify-between items-center mb-8">
                            <h2 class="text-xl font-bold font-sans">Aktivitas Blockchain</h2>
                            <div class="relative">
                                <select v-model="selectedRange" class="bg-[#0F0D0B] border border-[#4A3728] text-[12px] px-3 py-2 pr-9 rounded-md appearance-none">
                                    <option v-for="range in rangeOptions" :key="range">{{ range }}</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[#A89880]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="h-[240px] flex items-center justify-center border border-dashed border-[#4A3728] rounded-xl text-[#5C4F42]">
                            Belum ada data 
                        </div>
                    </div>

                    <div class="lg:col-span-4 bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                        <h2 class="text-xl font-bold font-sans mb-6">Status Jaringan</h2>

                        <div class="space-y-6">
                            <div class="bg-[#0F0D0B] border border-[#2E241C] rounded-xl px-5 py-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 rounded-full bg-[#08C246] shadow-[0_0_8px_rgba(8,194,70,0.4)]"></div>
                                    <span class="text-[14px]">{{ networkStatus.name }}</span>
                                </div>
                                <span class="text-[12px] text-[#A89880] font-mono">Ping: {{ networkStatus.ping }}</span>
                            </div>

                            <div>
                                <p class="text-[11px] text-[#A89880] uppercase tracking-wider mb-3">Log Kegagalan Terakhir</p>
                                <div class="space-y-3">
                                    <div
                                        v-for="log in failureLogs"
                                        :key="log.id"
                                        class="flex items-center justify-between text-[13px] py-2 border-b border-[#2E241C]"
                                    >
                                        <span class="text-[#F5EFE6]">{{ log.label }}</span>
                                        <button class="text-[#E8A838] hover:underline">Retry</button>
                                    </div>
                                </div>
                            </div>

                            <button class="w-full py-3 bg-[#2A2118] border border-[#4A3728] rounded-xl text-[13px] hover:bg-[#33271D] transition">
                                Lihat Semua Log Blockchain
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold font-sans">Batch Petani Terbaru</h2>
                            <a href="#" class="text-[13px] text-[#E8A838]">Lihat Semua</a>
                        </div>
                        <div class="space-y-4">
                            <div
                                v-for="batch in latestBatches"
                                :key="batch.id"
                                class="group flex items-center justify-between p-4 rounded-xl border border-transparent hover:border-[#4A3728] hover:bg-[#14110D] transition"
                            >
                                <div class="flex items-center gap-4">
                                    <div :class="batch.iconClass" class="w-10 h-10 rounded-full flex items-center justify-center text-lg mt-1">{{ batch.icon }}</div>
                                    <div>
                                        <p class="text-[15px] font-bold">{{ batch.title }}</p>
                                        <p class="text-[12px] text-[#A89880]">{{ batch.subtitle }}</p>
                                    </div>
                                </div>
                                <button :class="batch.actionClass" class="px-4 py-2 text-[12px] rounded-lg border">{{ batch.actionLabel }}</button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-xl font-bold font-sans">Pesanan</h2>
                                <a class="text-m text-[#4CAF7D] font-bold">Total Pendapatan: {{ revenueTotal }}</a>
                            </div>
                            <a href="#" class="text-[13px] text-[#E8A838]">Kelola Order</a>
                        </div>
                        <div class="space-y-4">
                            <div
                                v-for="order in orders"
                                :key="order.id"
                                class="flex items-center justify-between p-4 rounded-xl bg-[#0F0D0B] border border-[#2E241C]"
                            >
                                <div>
                                    <p class="text-[14px]">{{ order.title }}</p>
                                    <p class="text-[12px] text-[#A89880]">{{ order.subtitle }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span :class="orderStatusClass(order.status)" class="text-[11px] px-2 py-1 rounded">{{ order.status }}</span>
                                    <button v-if="order.showAction" class="w-8 h-8 rounded-lg bg-[#2A2118] flex items-center justify-center border border-[#4A3728]">✓</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import PhoneNumberWarning from '../../components/farmer/PhoneNumberWarning.vue';
import Sidebar from '../../components/exporter/Sidebar.vue';
import axios from 'axios';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'exporter.dashboard');

const logoUrl = '/assets/logo-fix.png';
const dashboardIntro = 'Berikut adalah ringkasan operasional dan rantai pasok Anda hari ini.';

const stats = ref({
    totalBatchDiakuisisi: 0,
    totalBatchCaption: 'Total batch yang telah diakuisisi',
    sertifikatTerbit: 0,
    sertifikatGrowth: '↑ 0%',
    perluTindakan: 0,
    perluTindakanDetail: 'Batch butuh verifikasi akhir',
    batchSiap: 0,
    batchSiapCaption: 'Batch tersedia di pasar'
});

const rangeOptions = ['3 Bulan Terakhir', '6 Bulan Terakhir'];
const selectedRange = ref(rangeOptions[0]);

const networkStatus = ref({
    name: 'Polygon Amoy',
    ping: '0ms'
});

const failureLogs = ref([]);
const latestBatches = ref([]);
const revenueTotal = ref('Rp 0');
const orders = ref([]);

const fetchDashboardData = async () => {
    try {
        const { data } = await axios.get('/api/v1/exporter/dashboard');
        if (data.success && data.data) {
            const payload = data.data;

            stats.value = {
                totalBatchDiakuisisi: payload.stats?.total_batches_acquired ?? 0,
                totalBatchCaption: payload.stats?.total_batches_caption ?? 'Total batch yang telah diakuisisi',
                sertifikatTerbit: payload.stats?.certificates_issued ?? 0,
                sertifikatGrowth: payload.stats?.certificates_growth_percent ? `↑ ${payload.stats.certificates_growth_percent}%` : '↑ 0%',
                perluTindakan: payload.stats?.pending_actions_count ?? 0,
                perluTindakanDetail: payload.stats?.pending_actions_detail ?? 'Batch butuh verifikasi akhir',
                batchSiap: payload.stats?.batches_ready_for_acquisition ?? 0,
                batchSiapCaption: payload.stats?.batches_ready_caption ?? 'Batch tersedia di pasar'
            };

            revenueTotal.value = payload.stats?.revenue_total 
                ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(payload.stats.revenue_total) 
                : 'Rp 0';

            networkStatus.value = {
                name: payload.network_status?.name ?? 'Polygon Amoy',
                ping: `${payload.network_status?.ping_ms ?? 0}ms`
            };

            failureLogs.value = (payload.blockchain_failure_logs ?? []).map(log => ({
                id: log.id,
                label: `Tx #${(log.log_id || '').substring(0, 8)}... (${log.error_type || 'Error'})`,
                log_id: log.log_id
            }));

            const batchesList = Array.isArray(payload.latest_batches) ? payload.latest_batches : (payload.latest_batches?.data || []);
            latestBatches.value = batchesList.map(b => ({
                id: b.id,
                icon: b.status === 'ready' ? '🟢' : '🟡',
                iconClass: b.status === 'ready' ? 'bg-[#1A3D2E]' : 'bg-[#2D2210]',
                title: b.name || `Batch ${b.code}`,
                subtitle: `Petani: ${b.farmer?.name || 'Petani'} · ${b.elevation_mdpl || 0} mdpl`,
                actionLabel: b.status === 'ready' ? 'Siap' : 'Proses',
                actionClass: b.status === 'ready' ? 'bg-[#1A3D2E] text-[#52B788] border-[#2D6A4F]' : 'border-[#4A3728] text-[#F5EFE6]'
            }));

            const ordersList = Array.isArray(payload.recent_orders) ? payload.recent_orders : (payload.recent_orders?.data || []);
            orders.value = ordersList.map(o => ({
                id: o.id,
                title: `Order #${o.order_number || o.id}`,
                subtitle: `Buyer: ${o.buyer?.name || 'Pembeli'}`,
                status: o.status === 'paid' ? 'Lunas' : (o.status === 'pending' ? 'Pending' : o.status),
                showAction: o.status === 'pending_confirmation'
            }));
        }
    } catch (err) {
        console.error('Failed to load exporter dashboard:', err);
    }
};

const retryTransaction = async (logId) => {
    try {
        const response = await axios.post(`/api/v1/exporter/blockchain-logs/${logId}/retry`);
        if (response.data?.success) {
            fetchDashboardData();
        }
    } catch (err) {
        console.error('Failed to retry transaction:', err);
    }
};

const confirmOrder = async (orderId) => {
    try {
        const response = await axios.post(`/api/v1/exporter/orders/${orderId}/confirm`);
        if (response.data?.success) {
            fetchDashboardData();
        }
    } catch (err) {
        console.error('Failed to confirm order:', err);
    }
};

const orderStatusClass = (status) => {
    if (status === 'Pending' || status === 'pending') {
        return 'text-[#E8A838] bg-[#2D2210]';
    }
    if (status === 'Lunas' || status === 'paid' || status === 'success') {
        return 'text-[#4CAF7D] bg-[#1A3D2E]';
    }
    return 'text-[#A89880] bg-[#2D2210]';
};

onMounted(() => {
    fetchDashboardData();
});

</script>

<style>
canvas {
    width: 100% !important;
    height: 100% !important;
}
</style>
