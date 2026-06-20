<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-buyer font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(184, 144, 42, 0.20), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.10), transparent 60%);"></div>
            
            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-12 py-6 pt-24 lg:pt-12 lg:py-12 relative z-10 overflow-y-auto">
                <header class="flex justify-between items-start mb-10 lg:mb-16 gap-6">
                    <div class="space-y-2">
                        <p class="mb-1 text-[11px] text-[#A89880] uppercase tracking-wider">Katalog Detail</p>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                            <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6] leading-none">{{ batchName }}</h1>
                            <span class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full bg-[#1A3D2E] text-[#4CAF7D] border border-[#08C246]/30 w-max">
                                Verified On-Chain
                            </span>
                        </div>
                        <p class="mt-2 text-[#A89880] font-mono text-[14px]">{{ batchId }}</p>
                    </div>

                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="Logo"
                            class="w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mt-10 lg:-mr-10 pointer-events-none"
                        >
                    </div>
                </header>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        <div class="lg:col-span-8 space-y-8">
                            <BannerPreview />

                            <!-- IoT Log Section -->
                            <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                                <div class="flex justify-between items-center mb-6">
                                    <h2 class="text-[18px] font-serif font-bold flex items-center gap-3">
                                        <span class="text-xl">📊</span> Transparansi Log Gudang
                                    </h2>
                                     <button @click="openLogModal" class="text-[12px] text-[#A89880] hover:text-[#F5EFE6] border border-[#2E241C] px-3 py-1 rounded-md bg-[#0F0D0B] transition">Log Lengkap ↗</button>
                                </div>

                                <div class="w-full h-[240px] border border-dashed border-[#4A3728] rounded-2xl mb-8 bg-[#0F0D0B] relative">
                                    <Line :data="warehouseChartData" :options="chartOptions" ref="warehouseChartRef" class="absolute inset-0" />
                                </div>

                                <div class="overflow-x-auto rounded-lg border border-[#2E241C] mb-6">
                                    <table class="w-full text-left text-[12px]">
                                        <thead class="bg-[#14110D] text-[#A89880] border-b border-[#2E241C]">
                                            <tr>
                                                <th class="px-4 py-3 font-medium">Tanggal</th>
                                                <th class="px-4 py-3 font-medium">Suhu Rata-rata</th>
                                                <th class="px-4 py-3 font-medium">Suhu (Max)</th>
                                                <th class="px-4 py-3 font-medium">Kelembapan (%)</th>
                                                <th class="px-4 py-3 font-medium">Status Sistem</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[#2E241C] text-[#F5EFE6] font-mono">
                                            <tr v-for="row in snapshotRows" :key="row.id" :class="rowHighlightClass(row)" class="hover:bg-[#14110D]">
                                                <td class="px-4 py-3">{{ row.date }}</td>
                                                <td class="px-4 py-3">{{ row.avg }}°C</td>
                                                <td class="px-4 py-3">{{ row.max }}°C</td>
                                                <td class="px-4 py-3">{{ row.humidity }}</td>
                                                <td class="px-4 py-3" :class="row.statusClass">{{ row.status }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div v-for="stat in iotStats" :key="stat.label" class="bg-[#0F0D0B] border border-[#2E241C] p-4 rounded-xl text-center">
                                        <p class="text-[10px] text-[#5C4F42] uppercase tracking-widest mb-1">{{ stat.label }}</p>
                                        <p class="text-[16px] font-bold text-[#F5EFE6]">{{ stat.value }}</p>
                                    </div>
                                </div>
                            </section>

                            <!-- Genesis Data -->
                            <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                                <h2 class="text-[18px] font-serif font-bold mb-6 flex items-center gap-3">
                                </h2>

                                <div class="flex justify-between items-center mb-5">
                                    <h2 class="text-[18px] font-serif font-bold mb-2 flex items-center gap-3">
                                        <span class="text-xl">📜</span> Genesis Data Record
                                    </h2>
                                    <div class="flex items-center gap-3">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6 text-[14px]">
                                    <div class="space-y-4">
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">ID Batch</span>
                                            <span class="font-mono text-[#F5EFE6]">{{ batchId }}</span>
                                        </div>
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">Origin</span>
                                            <span class="font-mono text-[#F5EFE6]">{{ genesis.origin }}</span>
                                        </div>
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">Varietas & Elevasi (mdpl)</span>
                                            <span class="font-mono text-[#F5EFE6]">{{ genesis.varietas }} | {{ genesis.elevation }}</span>
                                        </div>
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">Tanggal Panen</span>
                                            <span class="font-mono text-[#F5EFE6]">{{ genesis.harvestDate }}</span>
                                        </div>
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">Ringkasan Log</span>
                                            <span class="font-mono text-[#F5EFE6]">{{ genesis.summary }}</span>
                                        </div>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">Hash Log Pasca-Panen</span>
                                            <span class="font-mono text-[#E8A838] truncate" :title="genesis.hashLog">{{ genesis.hashLog }}</span>
                                        </div>
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">Hash Bukti Pembayaran</span>
                                            <span class="font-mono text-[#E8A838] truncate" :title="genesis.hashPayment">{{ genesis.hashPayment }}</span>
                                        </div>
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">Hash Farmer ID</span>
                                            <span class="font-mono text-[#5C4F42] truncate" :title="genesis.hashFarmer">{{ genesis.hashFarmer }}</span>
                                        </div>
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">Persentase Pendapatan Bersama ke Petani</span>
                                            <span class="font-mono text-[#F5EFE6]">{{ genesis.revenueShare }}</span>
                                        </div>
                                        <div class="flex flex-col border-b border-[#2E241C] pb-3">
                                            <span class="text-[#A89880] text-[11px] uppercase tracking-widest mb-1">Timestamp Genesis</span>
                                            <span class="font-mono text-[#08C246]">{{ genesis.timestamp }} ({{ genesis.timestampLabel }})</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 pt-4 border-t border-[#2E241C]">
                                    <div class="flex justify-between items-center mb-3">
                                        <p class="text-[12px] text-[#A89880] uppercase tracking-wider">Snapshot On-chain (30 hari terakhir)</p>
                                        <div>
                                            <button class="text-[12px] text-[#A89880] hover:text-[#F5EFE6] border border-[#2E241C] px-3 py-1 rounded-md bg-[#0F0D0B] transition" @click="openSnapshotModal">Lihat Snapshot ↗</button>
                                        </div>
                                    </div>

                                    <!-- Inline snapshot summary table (same layout as exporter) -->
                                    <div class="overflow-x-auto rounded-lg border border-[#2E241C] mb-4">
                                        <table class="w-full text-left text-[12px]">
                                            <thead class="bg-[#0F0D0B] text-[#A89880]"><tr>
                                                <th class="px-4 py-3 font-medium">Tanggal</th>
                                                <th class="px-4 py-3 font-medium">Suhu (Avg)</th>
                                                <th class="px-4 py-3 font-medium">Suhu (Max)</th>
                                                <th class="px-4 py-3 font-medium">Kelembapan (%)</th>
                                                <th class="px-4 py-3 font-medium">Aktivitas</th>
                                            </tr></thead>
                                            <tbody class="divide-y divide-[#2E241C] text-[#F5EFE6] font-mono">
                                                <tr v-for="row in snapshotRows.slice(0,3)" :key="row.id" class="hover:bg-[#14110D]"><td class="px-4 py-3">{{ row.date }}</td><td class="px-4 py-3">{{ row.avg }}</td><td class="px-4 py-3">{{ row.max }}</td><td class="px-4 py-3">{{ row.humidity }}</td><td class="px-4 py-3">{{ row.note || row.status }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <p class="text-[12px] text-[#A89880]">Ringkasan snapshot on-chain (preview). Klik "Lihat Snapshot" untuk detail 30 hari terakhir dan grafik.</p>
                                </div>
                            </section>
                        </div>

                        <!-- Log Modal -->
                        <transition name="fade">
                            <div v-show="isLogModalOpen" id="log-full-modal" class="fixed inset-0 z-50 flex items-center justify-center p-6">
                                <div id="log-full-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeLogModal"></div>
                                <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto bg-[#0F0D0B] border border-[#2E241C] rounded-2xl p-6 shadow-lg z-60">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h3 class="text-[18px] font-bold font-sans text-[#F5EFE6]">Log Penyimpanan Gudang</h3>
                                            <p class="text-sm text-[#A89880]">Grafik dan tabel log penyimpanan</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button @click="closeLogModal" class="px-3 py-1 rounded border border-[#2E241C]">Tutup</button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 mb-4">
                                        <div class="col-span-1">
                                            <div class="w-full h-64 bg-[#07100a] border border-[#2E241C] rounded-lg relative overflow-hidden">
                                                <Line :data="logFullChartData" :options="chartOptions" ref="logFullChartRef" class="absolute inset-0" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto rounded-lg border border-[#2E241C]">
                                        <table class="w-full text-left text-[12px]">
                                            <thead class="bg-[#0F0D0B] text-[#A89880]"><tr><th class="px-4 py-3 font-medium">Tanggal</th><th class="px-4 py-3 font-medium">Jam</th><th class="px-4 py-3 font-medium">Suhu (Avg °C)</th><th class="px-4 py-3 font-medium">Suhu (Max °C)</th><th class="px-4 py-3 font-medium">Kelembapan (%)</th><th class="px-4 py-3 font-medium">Aktivitas Petani</th></tr></thead>
                                            <tbody id="log-full-tbody" class="divide-y divide-[#2E241C] text-[#F5EFE6]">
                                                <tr v-for="row in logFullRows" :key="row.id" :class="rowHighlightClass(row)" class="hover:bg-[#14110D]"><td class="px-4 py-3">{{ row.date }}</td><td class="px-4 py-3">{{ row.time || '-' }}</td><td class="px-4 py-3 font-mono">{{ row.avg }}</td><td class="px-4 py-3 font-mono">{{ row.max }}</td><td class="px-4 py-3">{{ row.humidity }}</td><td class="px-4 py-3">{{ row.note }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </transition>

                        <!-- Snapshot Modal -->
                        <transition name="fade">
                            <div v-show="isSnapshotModalOpen" id="snapshot-log-modal" class="fixed inset-0 z-50 flex items-center justify-center p-6">
                                <div id="snapshot-log-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeSnapshotModal"></div>
                                <div class="relative w-full max-w-3xl max-h-[85vh] overflow-y-auto bg-[#0F0D0B] border border-[#2E241C] rounded-2xl p-6 shadow-lg z-60">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h3 class="text-[18px] font-bold font-sans text-[#F5EFE6]">Snapshot On-chain — Log Lengkap</h3>
                                            <p class="text-[12px] text-[#A89880]">Tabel snapshot 30 hari terakhir dan grafik ringkas.</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button aria-label="Tutup snapshot" class="px-3 py-2 rounded-md text-[12px] border border-[#2E241C] bg-[#1C1813] text-[#A89880] hover:bg-[#2E241C]" @click="closeSnapshotModal">Tutup</button>
                                        </div>
                                    </div>

                                    <div class="w-full h-64 bg-[#07100a] border border-[#2E241C] rounded-lg relative overflow-hidden mb-4">
                                        <Line :data="snapshotLogChartData" :options="chartOptions" ref="snapshotLogChartRef" class="absolute inset-0" />
                                    </div>

                                    <div class="overflow-x-auto rounded-lg border border-[#2E241C]">
                                        <table class="w-full text-left text-[12px]">
                                            <thead class="bg-[#0F0D0B] text-[#A89880]"><tr><th class="px-4 py-3 font-medium">Tanggal</th><th class="px-4 py-3 font-medium">Suhu (Avg)</th><th class="px-4 py-3 font-medium">Suhu (Max)</th><th class="px-4 py-3 font-medium">Kelembapan (%)</th><th class="px-4 py-3 font-medium">Aktivitas Petani</th></tr></thead>
                                            <tbody id="snapshot-log-full-tbody" class="divide-y divide-[#2E241C] text-[#F5EFE6] font-mono">
                                                <tr v-for="row in snapshotRows" :key="row.id" class="hover:bg-[#14110D]"><td class="px-4 py-3">{{ row.date }}</td><td class="px-4 py-3">{{ row.avg }}</td><td class="px-4 py-3">{{ row.max }}</td><td class="px-4 py-3">{{ row.humidity }}</td><td class="px-4 py-3">{{ row.note }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </transition>

                        <div class="lg:col-span-4 space-y-6">
                            <!-- Pricing Card -->
                            <section class="bg-gradient-to-br from-[#2D2210] to-[#1C1813] border border-[#B8902A]/20 rounded-2xl p-8 shadow-2xl">
                                <h2 class="text-[14px] font-bold text-[#D4AF5A] uppercase tracking-widest mb-6">Penawaran Saat Ini</h2>
                                <div class="bg-[#0F0D0B]/60 backdrop-blur-md p-6 rounded-2xl border border-[#4A3728] mb-8">
                                    <p class="text-[11px] text-[#A89880] uppercase tracking-widest mb-2">Harga per Kilogram</p>
                                    <p class="font-serif text-[32px] font-bold text-[#F5EFE6]">{{ priceText }}</p>
                                    <p class="text-[12px] text-[#4CAF7D] mt-2 font-medium">✓ Stok Tersedia: 1,200 Kg</p>
                                </div>
                                <div class="space-y-3">
                                    <router-link :to="{ name: 'buyer.checkout', query: { batch_listing_id: route.query.id } }" class="block w-full py-4 bg-[#B8902A] text-white text-center rounded-xl font-bold hover:bg-[#D4AF5A] transition-colors">
                                        Beli Sekarang
                                    </router-link>
                                </div>
                            </section>

                            <!-- Blockchain Trail -->
                            <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                                <h2 class="text-[14px] font-bold text-[#F5EFE6] uppercase tracking-widest mb-6 flex items-center gap-3">
                                    <span class="text-lg">🔗</span> Blockchain Audit
                                </h2>
                                <div class="space-y-6">
                                    <div>
                                        <p class="text-[11px] text-[#A89880] uppercase tracking-widest mb-2">Smart Contract</p>
                                        <div class="bg-[#0F0D0B] p-3 rounded-lg border border-[#2E241C] font-mono text-[11px] text-[#D4AF5A] truncate">
                                            {{ genesis.contract }}
                                        </div>
                                    </div>
                                    <div class="pt-6 border-t border-[#2E241C] flex flex-col items-center">
                                        <div class="bg-white p-3 rounded-2xl mb-4 shadow-xl shadow-white/5">
                                            <img :src="qrUrl" alt="QR Code" class="w-[120px] h-[120px]">
                                        </div>
                                        <p class="text-[11px] text-[#A89880] text-center leading-relaxed">
                                            Pindai QR untuk memverifikasi<br>seluruh perjalanan biji kopi ini.
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </main>
            </div>
        </div>
</template>

<script setup>
import { computed, ref, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '../../components/buyer/Sidebar.vue';
import BannerPreview from '../../components/exporter/BannerPreview.vue';
import '../../plugins/chart-setup';
import { Line } from 'vue-chartjs';
import axios from 'axios';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'buyer.catalog-detail');
const logoUrl = '/assets/logo-fix.png';

const batchName = ref('');
const batchId = ref('');
const priceText = ref('');
const qrUrl = ref('');

const iotStats = ref([
    { label: 'Suhu Rata-rata', value: '-' },
    { label: 'Kelembapan', value: '-' },
    { label: 'Suhu Max', value: '-' },
    { label: 'Kualitas', value: '-' }
]);

const genesis = reactive({
    contract: '-',
    origin: '-',
    varietas: '-',
    elevation: '-',
    harvestDate: '-',
    summary: '-',
    hashLog: '-',
    hashPayment: '-',
    hashFarmer: '-',
    revenueShare: '-',
    timestamp: '-',
    timestampLabel: '-'
});

const snapshotRows = ref([]);
const logFullRows = ref([]);

const isLogModalOpen = ref(false);
const isSnapshotModalOpen = ref(false);

const warehouseChartRef = ref(null);
const logFullChartRef = ref(null);
const snapshotLogChartRef = ref(null);

const warehouseChartData = computed(() => {
    const labels = snapshotRows.value.map(r => r.date);
    const tempsAvg = snapshotRows.value.map(r => parseFloat(String(r.avg)) || null);
    const tempsMax = snapshotRows.value.map(r => parseFloat(String(r.max)) || null);
    const hums = snapshotRows.value.map(r => parseFloat(String(r.humidity)) || null);
    return {
        labels: labels.length ? labels : ['19 Mei 2026', '12 Mei 2026'],
        datasets: [
            { label: 'Suhu (Avg °C)', data: tempsAvg.length ? tempsAvg : [22.1, 21.8], borderColor: '#E8A838', backgroundColor: 'rgba(232,168,56,0.06)', tension: 0.3, yAxisID: 'y1' },
            { label: 'Suhu (Max °C)', data: tempsMax.length ? tempsMax : [24.5, 23.0], borderColor: '#FF6B6B', backgroundColor: 'rgba(255,107,107,0.06)', tension: 0.3, yAxisID: 'y1' },
            { label: 'Kelembaban (%)', data: hums.length ? hums : [60, 58], borderColor: '#08C246', backgroundColor: 'rgba(8,194,70,0.08)', tension: 0.3, yAxisID: 'y2' }
        ]
    };
});

const logFullChartData = computed(() => {
    const labels = logFullRows.value.map(r => r.date + (r.time && r.time !== '-' ? ' ' + r.time : ''));
    const tempsAvg = logFullRows.value.map(r => parseFloat(String(r.avg)) || null);
    const tempsMax = logFullRows.value.map(r => parseFloat(String(r.max)) || null);
    const hums = logFullRows.value.map(r => parseFloat(String(r.humidity)) || null);
    return {
        labels: labels.length ? labels : ['19 Mei 2026', '12 Mei 2026'],
        datasets: [
            { label: 'Suhu (Avg °C)', data: tempsAvg.length ? tempsAvg : [22.1, 21.8], borderColor: '#E8A838', backgroundColor: 'rgba(232,168,56,0.06)', tension: 0.3, yAxisID: 'y1' },
            { label: 'Suhu (Max °C)', data: tempsMax.length ? tempsMax : [24.5, 23.0], borderColor: '#FF6B6B', backgroundColor: 'rgba(255,107,107,0.06)', tension: 0.3, yAxisID: 'y1' },
            { label: 'Kelembaban (%)', data: hums.length ? hums : [60, 58], borderColor: '#08C246', backgroundColor: 'rgba(8,194,70,0.08)', tension: 0.3, yAxisID: 'y2' }
        ]
    };
});

const snapshotLogChartData = computed(() => ({
    labels: snapshotRows.value.map(r => r.date),
    datasets: [
        { label: 'Suhu (Avg °C)', data: snapshotRows.value.map(r => parseFloat(String(r.avg)) || null), borderColor: '#E8A838', backgroundColor: 'rgba(232,168,56,0.06)', tension: 0.3, yAxisID: 'y1' },
        { label: 'Suhu (Max °C)', data: snapshotRows.value.map(r => parseFloat(String(r.max)) || null), borderColor: '#FF6B6B', backgroundColor: 'rgba(255,107,107,0.06)', tension: 0.3, yAxisID: 'y1' },
        { label: 'Kelembaban (%)', data: snapshotRows.value.map(r => parseFloat(String(r.humidity)) || null), borderColor: '#08C246', backgroundColor: 'rgba(8,194,70,0.08)', tension: 0.3, yAxisID: 'y2' }
    ]
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#A89880' } } },
    scales: {
        y1: { type: 'linear', position: 'left', title: { display: true, text: 'Suhu (°C)', color: '#A89880' }, ticks: { color: '#F5EFE6' } },
        y2: { type: 'linear', position: 'right', title: { display: true, text: 'Kelembaban (%)', color: '#A89880' }, grid: { drawOnChartArea: false }, ticks: { color: '#F5EFE6' } },
        x: { ticks: { color: '#A89880' } }
    }
};

function safeDestroyChart(refComp) {
    try {
        if (!refComp || !refComp.value) return;
        const cmp = refComp.value;
        const instance = cmp.chart || cmp.chartInstance || cmp._chart || (cmp.$data && cmp.$data._chart);
        if (instance && typeof instance.destroy === 'function') instance.destroy();
    } catch (e) { /* ignore */ }
}

const openLogModal = async () => {
    isLogModalOpen.value = true;
    await nextTick();
    try { logFullChartRef.value?.chart?.update?.(); } catch (e) { /* ignore */ }
};
const closeLogModal = () => { safeDestroyChart(logFullChartRef); isLogModalOpen.value = false; };

const openSnapshotModal = async () => {
    isSnapshotModalOpen.value = true;
    await nextTick();
    try { snapshotLogChartRef.value?.chart?.update?.(); } catch (e) { /* ignore */ }
};
const closeSnapshotModal = () => { safeDestroyChart(snapshotLogChartRef); isSnapshotModalOpen.value = false; };

function rowHighlightClass(row) {
    const raw = String(row.max || '').replace(/[^0-9.]/g, '');
    const maxVal = Number(raw) || 0;
    return maxVal >= 40 ? 'bg-[#2D2210]/20 border-l-2 border-l-[#E8A838]' : '';
}

const fetchCatalogDetail = async () => {
    const id = route.query.id;
    if (!id) return;
    try {
        const { data } = await axios.get(`/api/v1/buyer/catalog/${id}`);
        if (data.success && data.data) {
            const res = data.data;
            const listing = res.listing;
            batchName.value = listing.name;
            batchId.value = listing.batch_code || String(listing.id);
            priceText.value = listing.price_per_kg_display;
            
            // IoT Stats
            const iot = res.iot_summary || {};
            iotStats.value = [
                { label: 'Suhu Rata-rata', value: `${iot.avg_temperature || 22.4}°C` },
                { label: 'Kelembapan', value: `${iot.avg_humidity || 62}%` },
                { label: 'Suhu Max', value: `${iot.max_temperature || 30}°C` },
                { label: 'Kualitas', value: listing.badge || 'Grade 1' }
            ];

            // Genesis
            const gen = res.genesis_data || {};
            genesis.contract = res.blockchain_audit?.smart_contract_address || '0x8B3C4...A9D0';
            genesis.origin = listing.origin || '-';
            genesis.varietas = listing.variety || '-';
            genesis.elevation = listing.elevation || '-';
            genesis.harvestDate = listing.harvest_date_label || '-';
            genesis.summary = gen.summary_log || 'Genesis block created';
            genesis.hashLog = gen.hash_log || 'e3b0c44298fc1c149afbf4c...';
            genesis.hashPayment = gen.hash_payment || '8a2f9b1c7d3e4f5a6b7c8d9...';
            genesis.hashFarmer = gen.hash_farmer || 'f47ac10b58cc4372a5670e0...';
            genesis.revenueShare = `${gen.revenue_share_percent || 10}%`;
            genesis.timestamp = gen.timestamp_genesis || '1716095233';
            genesis.timestampLabel = new Date(gen.timestamp_genesis).toLocaleDateString('id-ID');
            qrUrl.value = res.blockchain_audit?.qr_code_image || `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://biji.co/verify/${batchId.value}`;
        }
    } catch (err) {
        console.error('Failed to fetch catalog detail:', err);
    }

    try {
        const logsRes = await axios.get(`/api/v1/buyer/catalog/${id}/logs`);
        if (logsRes.data?.success && logsRes.data?.data) {
            const logsList = logsRes.data.data;
            logFullRows.value = logsList.map(log => ({
                id: log.id,
                date: new Date(log.timestamp).toLocaleDateString('id-ID'),
                time: new Date(log.timestamp).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
                avg: String(log.temperature),
                max: String(log.temperature_max),
                humidity: String(log.humidity),
                note: log.health_label || 'Normal'
            }));
        }
    } catch (err) {
        console.error('Failed to load logs:', err);
    }

    try {
        const snapRes = await axios.get(`/api/v1/buyer/catalog/${id}/snapshots`);
        if (snapRes.data?.success && snapRes.data?.data) {
            const snapList = snapRes.data.data;
            snapshotRows.value = snapList.map(snap => ({
                id: snap.id,
                date: snap.snapshot_date_label,
                avg: `${snap.avg_temperature}°C`,
                max: `${snap.max_temperature}°C`,
                humidity: String(snap.avg_humidity),
                status: snap.is_verified ? 'Verified 🟢' : 'Pending 🟡',
                statusClass: snap.is_verified ? 'text-[#08C246] font-sans' : 'text-[#E8A838] font-sans',
                note: `Block: ${snap.block_number}`
            }));
        }
    } catch (err) {
        console.error('Failed to load snapshots:', err);
    }
};

onBeforeUnmount(() => {
    safeDestroyChart(warehouseChartRef);
    safeDestroyChart(logFullChartRef);
    safeDestroyChart(snapshotLogChartRef);
});

onMounted(() => {
    fetchCatalogDetail();
});
</script>

<style scoped>
/* Custom scrollbar for better aesthetics */
::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-track {
    background: #0F0D0B;
}
::-webkit-scrollbar-thumb {
    background: #2E241C;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #4A3728;
}
</style>
