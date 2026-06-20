<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-exporter font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(75, 56, 50, 0.35), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.12), transparent 60%);"></div>
            <div class="relative flex min-h-screen flex-col lg:flex-row">

                <main class="flex-1 px-4 sm:px-6 lg:px-10 py-6 lg:py-10 relative z-10 overflow-y-auto h-screen">
                    <header class="mb-10 mt-4 lg:mt-0">
                        <div class="mb-4">
                            <button @click="$router.back()" class="text-[#A89880] hover:text-[#F5EFE6] transition flex items-center gap-2 text-sm bg-[#1C1813]/50 px-3 py-1.5 rounded-full border border-[#2E241C] w-max">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                Kembali
                            </button>
                        </div>
                        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
                            <div>
                                <p class="text-[11px] text-[#A89880] uppercase tracking-wider mb-2">Kelola Batch Saya</p>
                                <div class="flex items-center gap-4">
                                    <h1 class="font-mono text-[32px] font-bold text-[#F5EFE6] leading-none">{{ batchId }}</h1>
                                    <span class="px-3 py-1 text-[11px] font-bold uppercase tracking-wider rounded-full bg-[#1A3D2E] text-[#08C246] border border-[#08C246]/50 shadow-[0_0_10px_rgba(8,194,70,0.2)]">
                                        Live di Marketplace
                                    </span>
                                </div>
                            </div>

                            <div>
                                <router-link :to="{ name: 'exporter.modify-batch', query: { id: batchId } }" id="edit-batch-btn" class="px-4 py-2 rounded-lg bg-[#E8A838] text-black font-bold hover:bg-[#d9b93a] transition">
                                    Modifikasi Batch
                                </router-link>
                            </div>
                        </div>
                    </header>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        <div class="lg:col-span-8 space-y-8">

                            <div class="mb-4">
                                <div class="bg-[#2A1F14] border border-[#4A3728] rounded-lg p-4 mb-4 flex items-start gap-3">
                                    <div class="text-[#E8A838] text-xl">⚠️</div>
                                    <div>
                                        <p class="font-sans text-[14px] font-bold text-[#F5EFE6]">Batch Terkunci — Pembelian Sedang Berlangsung</p>
                                        <p class="text-[12px] text-[#A89880]">Batch ini sedang dalam proses pembayaran oleh suatu pembeli. Modifikasi dan penawaran sementara dinonaktifkan hingga proses selesai.</p>
                                    </div>
                                </div>
                            </div>

                            <BannerPreview />

                            <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6">
                                <div class="flex justify-between items-center mb-5">
                                    <h2 class="text-[16px] font-sans flex items-center gap-2">🏢 Log Penyimpanan Gudang (IoT)</h2>
                                    <div class="flex items-center gap-3">
                                        <div>
                                            <button id="warehouse-log-full" class="ml-2 text-[12px] text-[#A89880] hover:text-[#F5EFE6] border border-[#2E241C] px-3 py-1 rounded-md bg-[#0F0D0B] transition" @click="openLogModal">Log Lengkap ↗</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-full h-[200px] border border-dashed border-[#4A3728] rounded-xl mb-6 bg-[#0F0D0B] relative">
                                    <Line :data="warehouseChartData" :options="chartOptions" ref="warehouseChartRef" class="absolute inset-0" />
                                </div>

                                <div>
                                    <p class="text-[12px] text-[#A89880] mb-2 uppercase tracking-wider">Snapshot Harian</p>
                                    <div class="overflow-x-auto rounded-lg border border-[#2E241C]">
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
                                                    <td class="px-4 py-3">{{ row.avg }}</td>
                                                    <td class="px-4 py-3">{{ row.max }}</td>
                                                    <td class="px-4 py-3">{{ row.humidity }}</td>
                                                    <td class="px-4 py-3" :class="row.statusClass">{{ row.status }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>

                            <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl overflow-hidden p-6">
                                <div class="flex justify-between items-center mb-5">
                                    <h2 class="text-[16px] font-sans flex items-center gap-2">🔒 Genesis Data Record</h2>
                                    <div class="flex items-center gap-3"></div>
                                </div>

                                <div id="tab-content-genesis" class="block">
                                    <div class="flex justify-between items-center mb-6">
                                        <p class="text-[13px] text-[#A89880]">Data profil dan histori awal dari petani yang telah dikunci di blockchain.</p>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-[12px]">
                                        <div class="space-y-3">
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">ID Batch</span>
                                                <span class="font-mono text-[#F5EFE6]">{{ batchId }}</span>
                                            </div>
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">Origin</span>
                                                <span class="font-mono text-[#F5EFE6]">{{ genesis.origin }}</span>
                                            </div>
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">Varietas & Elevasi (mdpl)</span>
                                                <span class="font-mono text-[#F5EFE6]">{{ genesis.varietas }} | {{ genesis.elevation }}</span>
                                            </div>
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">Tanggal Panen</span>
                                                <span class="font-mono text-[#F5EFE6]">{{ genesis.harvestDate }}</span>
                                            </div>
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">Ringkasan Log</span>
                                                <span class="font-mono text-[#F5EFE6]">{{ genesis.summary }}</span>
                                            </div>
                                        </div>

                                        <div class="space-y-3">
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">Hash Log Pasca-Panen</span>
                                                <span class="font-mono text-[#E8A838] truncate">{{ genesis.hashLog }}</span>
                                            </div>
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">Hash Bukti Pembayaran</span>
                                                <span class="font-mono text-[#E8A838] truncate">{{ genesis.hashPayment }}</span>
                                            </div>
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">Hash Farmer ID</span>
                                                <span class="font-mono text-[#5C4F42] truncate">{{ genesis.hashFarmer }}</span>
                                            </div>
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">Persentase Pendapatan Bersama ke Petani</span>
                                                <span class="font-mono text-[#F5EFE6]">{{ genesis.revenueShare }}</span>
                                            </div>
                                            <div class="flex flex-col border-b border-[#2E241C] pb-2">
                                                <span class="text-[#A89880] mb-1">Timestamp Genesis</span>
                                                <span class="font-mono text-[#08C246]">{{ genesis.timestamp }} ({{ genesis.timestampLabel }})</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-6 pt-4 border-t border-[#2E241C]">
                                        <div class="flex justify-between items-center mb-3">
                                            <p class="text-[12px] text-[#A89880] uppercase tracking-wider">Snapshot On-chain (30 hari terakhir)</p>
                                            <div>
                                                <button id="snapshot-log-full" class="text-[12px] text-[#A89880] hover:text-[#F5EFE6] border border-[#2E241C] px-3 py-1 rounded-md bg-[#0F0D0B] transition" @click="openSnapshotModal">Log Lengkap ↗</button>
                                            </div>
                                        </div>
                                        <div class="overflow-x-auto rounded-lg border border-[#2E241C]">
                                            <table class="w-full text-left text-[12px]">
                                                <thead class="bg-[#14110D] text-[#A89880] border-b border-[#2E241C]">
                                                    <tr>
                                                        <th class="px-4 py-3 font-medium">Tanggal</th>
                                                        <th class="px-4 py-3 font-medium">Suhu (Avg)</th>
                                                        <th class="px-4 py-3 font-medium">Suhu (Max)</th>
                                                        <th class="px-4 py-3 font-medium">Kelembapan (%)</th>
                                                        <th class="px-4 py-3 font-medium">Aktivitas Petani</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-[#2E241C] text-[#F5EFE6] font-mono">
                                                    <tr v-for="row in logFullRows" :key="row.id" :class="rowHighlightClass(row)">
                                                        <td class="px-4 py-3">{{ row.date }}</td>
                                                        <td class="px-4 py-3">{{ row.time || '-' }}</td>
                                                        <td class="px-4 py-3 font-mono">{{ row.avg }}</td>
                                                        <td class="px-4 py-3 font-mono">{{ row.max }}</td>
                                                        <td class="px-4 py-3">{{ row.note }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </section>

                        </div>

                        <div class="lg:col-span-4 space-y-6">

                            <!-- Error / Success Banner -->
                            <transition name="fade">
                                <div v-if="apiMessage" class="px-4 py-3 rounded-lg text-[13px] font-bold mb-4"
                                    :class="apiSuccess ? 'bg-[#d1fae5] text-[#065f46] border border-[#6ee7b7]' : 'bg-[#fee2e2] text-[#991b1b] border border-[#fca5a5]'">
                                    {{ apiMessage }}
                                </div>
                            </transition>

                            <div class="space-y-3 mb-6 bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6">
                                <h3 class="text-[14px] font-bold text-[#A89880] uppercase tracking-wider mb-2">Sertifikasi & Aksi</h3>
                                
                                <div v-if="batchStatus === 'draft'" class="space-y-3">
                                    <p class="text-[12px] text-[#A89880]">Sertifikat belum diterbitkan untuk batch ini.</p>
                                    <button @click="generateCertificate" :disabled="isLoading" class="w-full py-3 bg-[#2D6A4F] text-white rounded-xl text-[13px] font-bold hover:bg-[#356d58] transition flex items-center justify-center gap-2">
                                        {{ isLoading ? 'Memproses...' : 'Terbitkan Sertifikat' }}
                                    </button>
                                </div>

                                <div v-else-if="batchStatus === 'published' || batchStatus === 'terbit'" class="space-y-3">
                                    <p class="text-[12px] text-[#A89880]">Sertifikat telah diterbitkan. Silakan unggah/publikasikan ke Blockchain.</p>
                                    <button @click="publishCertificate" :disabled="isLoading" class="w-full py-3 bg-[#E8A838] text-black rounded-xl text-[13px] font-bold hover:bg-[#d9b93a] transition flex items-center justify-center gap-2">
                                        {{ isLoading ? 'Mengunggah...' : 'Publikasikan ke Blockchain' }}
                                    </button>
                                </div>

                                <div v-else-if="batchStatus === 'locked' || batchStatus === 'terkunci' || batchStatus === 'sold' || batchStatus === 'terjual'" class="space-y-3">
                                    <p class="text-[12px] text-[#A89880]">Sertifikat telah aktif di Blockchain Polygon.</p>
                                    <a :href="`/api/v1/exporter/batches/${route.query.id}/certificate/pdf`" download class="w-full py-3 bg-[#2A2118] border border-[#4A3728] text-[#F5EFE6] rounded-xl text-[13px] font-bold hover:bg-[#33271D] transition flex items-center justify-center gap-2">
                                        Unduh Sertifikat (PDF)
                                    </a>
                                    <button v-if="batchStatus !== 'sold' && batchStatus !== 'terjual'" @click="releaseBatch" :disabled="isLoading" class="w-full py-3 bg-[#D94F4F] text-white rounded-xl text-[13px] font-bold hover:bg-[#c0392b] transition flex items-center justify-center gap-2">
                                        {{ isLoading ? 'Memproses...' : 'Release Batch dari Marketplace' }}
                                    </button>
                                </div>
                            </div>

                            <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6">
                                <h2 class="text-[16px] font-sans text-[#F5EFE6] mb-4">💰 Detail Harga</h2>
                                <div class="bg-[#0F0D0B] p-4 rounded-xl border border-[#4A3728] mb-4">
                                    <p class="text-[12px] text-[#A89880] mb-1">Harga Buka Saat Ini</p>
                                    <p class="font-sans text-[24px] font-bold text-[#F5EFE6]">{{ priceText }} <span class="text-[14px] font-sans font-normal text-[#A89880]">/kg</span></p>
                                </div>
                            </section>

                            <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6">
                                <h2 class="text-[14px] font-bold text-[#F5EFE6] mb-4 flex items-center gap-2">🔗 Audit Trail Blockchain</h2>
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-[11px] text-[#A89880] uppercase tracking-wider mb-1">Contract Address</p>
                                        <a href="#" class="block bg-[#0F0D0B] p-2 rounded border border-[#2E241C] font-mono text-[11px] text-[#E8A838] hover:underline truncate">{{ genesis.contract }}</a>
                                    </div>
                                    <div class="pt-4 border-t border-[#2E241C] flex flex-col items-center">
                                        <div class="bg-white p-2 rounded-lg mb-2">
                                            <img :src="qrUrl" alt="QR Code" class="w-[100px] h-[100px]">
                                        </div>
                                        <p class="text-[10px] text-[#A89880] text-center">QR Code Kemasan<br>(Scan untuk verifikasi rantai pasok)</p>
                                    </div>
                                </div>
                            </section>

                        </div>
                    </div>

                    <!-- Log Modal -->
                    <transition name="fade">
                        <div v-show="isLogModalOpen" id="log-full-modal" class="fixed inset-0 z-50 flex items-center justify-center p-6">
                            <div id="log-full-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeLogModal"></div>
                            <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto bg-[#0F0D0B] border border-[#2E241C] rounded-2xl p-6 shadow-lg z-60">
                                <div class="flex items-start justify-between mb-4">
                                    <div>
                                        <h3 class="text-[18px] font-bold font-sans text-[#F5EFE6]">Log Penyimpanan Gudang</h3>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button id="close-log-full" aria-label="Tutup" class="px-3 py-2 rounded-md text-[12px] border border-[#2E241C] bg-[#1C1813] text-[#A89880] hover:bg-[#2E241C]" @click="closeLogModal">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </button>
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
                                        <button id="close-snapshot-log" aria-label="Tutup snapshot" class="px-3 py-2 rounded-md text-[12px] border border-[#2E241C] bg-[#1C1813] text-[#A89880] hover:bg-[#2E241C]" @click="closeSnapshotModal">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="w-full h-64 bg-[#07100a] border border-[#2E241C] rounded-lg relative overflow-hidden mb-4">
                                    <Line :data="snapshotLogChartData" :options="chartOptions" ref="snapshotLogChartRef" class="absolute inset-0" />
                                    <div id="snapshotLogChartFallback" class="absolute inset-0 flex items-center justify-center text-[#5C4F42] pointer-events-none">Chart.js placeholder</div>
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

                </main>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '../../components/exporter/Sidebar.vue';
import BannerPreview from '../../components/exporter/BannerPreview.vue';
import '../../plugins/chart-setup';
import { Line } from 'vue-chartjs';
import axios from 'axios';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'exporter.batch-saya-detail');

// Mock data converted from Blade
const batchId = ref('BJ-TRJ-24-001');
const priceText = ref('Rp 185.000');
const qrUrl = ref('https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https://biji.co/verify/BJ-TRJ-24-001');

const batchStatus = ref('draft');
const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const genesis = reactive({
    contract: '0x8B3C4...A9D0',
    origin: 'Bittuang, Tana Toraja',
    varietas: 'Arabika Lini S-795',
    elevation: '1650',
    harvestDate: '2026-05-02',
    summary: 'Avg Temp: 24C, Avg Hum: 60%',
    hashLog: 'e3b0c44298fc1c149afbf4c...',
    hashPayment: '8a2f9b1c7d3e4f5a6b7c8d9...',
    hashFarmer: 'f47ac10b58cc4372a5670e0...',
    revenueShare: '15%',
    timestamp: '1716095233',
    timestampLabel: '19 May 2026'
});

const snapshotRows = ref([
    { id: 1, date: '19 Mei 2026', avg: '22.1°C', max: '24.5°C', humidity: '60', status: 'Normal 🟢', statusClass: 'text-[#08C246] font-sans', note: 'Panen & Pengiriman ke Gudang' },
    { id: 2, date: '12 Mei 2026', avg: '21.8°C', max: '23.0°C', humidity: '58', status: 'Normal 🟢', statusClass: 'text-[#A89880]', note: 'Pengeringan Awal' },
    { id: 3, date: '05 Mei 2026', avg: '20.5°C', max: '22.0°C', humidity: '62', status: 'Normal 🟢', statusClass: 'text-[#A89880]', note: 'Pembersihan & Sortir' }
]);

const logFullRows = ref([
    { id: 1, date: '19 Mei 2026', time: '-', avg: '22.1', max: '24.5', humidity: '60', note: 'Panen & Pengiriman ke Gudang' },
    { id: 2, date: '12 Mei 2026', time: '-', avg: '21.8', max: '23.0', humidity: '58', note: 'Pengeringan Awal' },
    { id: 3, date: '05 Mei 2026', time: '-', avg: '20.5', max: '22.0', humidity: '62', note: 'Pembersihan & Sortir' }
]);

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
    console.debug('[BatchSayaDetail] openLogModal called');
    isLogModalOpen.value = true;
    await nextTick();
    try {
        if (logFullChartRef.value) {
            console.debug('[BatchSayaDetail] logFullChartRef exists, updating chart', logFullChartRef.value);
            try { logFullChartRef.value.chart?.update?.(); } catch (e) { console.warn('[BatchSayaDetail] logFull chart update failed', e); }
        } else {
            console.debug('[BatchSayaDetail] logFullChartRef not ready yet');
        }
    } catch (e) { console.error('[BatchSayaDetail] error updating logFull chart', e); }
};
const closeLogModal = () => { console.debug('[BatchSayaDetail] closeLogModal called'); safeDestroyChart(logFullChartRef); isLogModalOpen.value = false; };

const openSnapshotModal = async () => {
    console.debug('[BatchSayaDetail] openSnapshotModal called');
    isSnapshotModalOpen.value = true;
    await nextTick();
    try {
        if (snapshotLogChartRef.value) {
            console.debug('[BatchSayaDetail] snapshotLogChartRef exists, updating chart', snapshotLogChartRef.value);
            try { snapshotLogChartRef.value.chart?.update?.(); } catch (e) { console.warn('[BatchSayaDetail] snapshot chart update failed', e); }
        } else {
            console.debug('[BatchSayaDetail] snapshotLogChartRef not ready yet');
        }
    } catch (e) { console.error('[BatchSayaDetail] error updating snapshot chart', e); }
};
const closeSnapshotModal = () => { console.debug('[BatchSayaDetail] closeSnapshotModal called'); safeDestroyChart(snapshotLogChartRef); isSnapshotModalOpen.value = false; };

function rowHighlightClass(row) {
    const raw = String(row.max || row.max || '').replace(/[^0-9.]/g, '');
    const maxVal = Number(raw) || 0;
    return maxVal >= 40 ? 'bg-[#2D2210]/20 border-l-2 border-l-[#E8A838]' : '';
}

const fetchBatchDetail = async () => {
    const id = route.query.id;
    if (!id) return;
    try {
        const { data } = await axios.get(`/api/v1/exporter/batches/${id}`);
        if (data.success && data.data) {
            const b = data.data;
            batchId.value = b.batch_code || b.code || String(b.id);
            priceText.value = b.price ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(b.price) : '-';
            batchStatus.value = b.status || 'draft';

            genesis.origin = b.origin?.location || b.kebun || '-';
            genesis.varietas = b.origin?.variety || b.variety || b.varietas || '-';
            genesis.elevation = String(b.origin?.elevation_mdpl || b.elevation_mdpl || '1650');
            genesis.harvestDate = b.origin?.harvest_date || b.tanggal_panen || '-';

            genesis.contract = b.genesis_data?.contract_address || '0x8B3C4...A9D0';
            genesis.hashLog = b.genesis_data?.hash_log || 'e3b0c44298fc1c149afbf4c...';
            genesis.hashPayment = b.genesis_data?.hash_payment || '8a2f9b1c7d3e4f5a6b7c8d9...';
            genesis.hashFarmer = b.genesis_data?.hash_farmer || 'f47ac10b58cc4372a5670e0...';
            genesis.revenueShare = b.genesis_data?.revenue_share || '15%';
            genesis.timestamp = b.genesis_data?.timestamp || '1716095233';

            qrUrl.value = b.genesis_data?.qr_code_url || `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https://biji.co/verify/${batchId.value}`;

            if (b.logs && b.logs.length > 0) {
                snapshotRows.value = b.logs.map(log => ({
                    id: log.id,
                    date: log.date || new Date(log.created_at).toLocaleDateString('id-ID'),
                    avg: `${log.temperature || 0}°C`,
                    max: `${log.max_temperature || log.temperature || 0}°C`,
                    humidity: String(log.humidity || 0),
                    status: 'Normal 🟢',
                    statusClass: 'text-[#08C246] font-sans',
                    note: log.note || 'Normal'
                }));
                logFullRows.value = b.logs.map(log => ({
                    id: log.id,
                    date: log.date || new Date(log.created_at).toLocaleDateString('id-ID'),
                    time: log.time || '-',
                    avg: String(log.temperature || 0),
                    max: String(log.max_temperature || log.temperature || 0),
                    humidity: String(log.humidity || 0),
                    note: log.note || 'Normal'
                }));
            }

            // Fetch Telemetry dari Supabase
            try {
                const telemetryRes = await axios.get(`/api/v1/exporter/batches/${id}/telemetry`);
                if (telemetryRes.data?.success && telemetryRes.data.data?.logs?.length > 0) {
                    const telemetryLogs = telemetryRes.data.data.logs;
                    snapshotRows.value = telemetryLogs.map((log, idx) => ({
                        id: idx + 1,
                        date: new Date(log.created_at).toLocaleDateString('id-ID'),
                        avg: `${log.temperature || 0}°C`,
                        max: `${log.temperature || 0}°C`,
                        humidity: `${log.humidity || 0}%`,
                        status: 'Normal 🟢',
                        statusClass: 'text-[#08C246] font-sans',
                        note: 'Supabase Real-time'
                    }));
                    logFullRows.value = telemetryLogs.map((log, idx) => ({
                        id: idx + 1,
                        date: new Date(log.created_at).toLocaleDateString('id-ID'),
                        time: new Date(log.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
                        avg: String(log.temperature || 0),
                        max: String(log.temperature || 0),
                        humidity: String(log.humidity || 0),
                        note: 'Supabase Real-time'
                    }));
                }
            } catch (telemetryErr) {
                console.warn('Gagal mem-fetch data telemetri Supabase:', telemetryErr);
            }
        }
    } catch (err) {
        console.error('Failed to load batch detail:', err);
    }
};

const generateCertificate = async () => {
    isLoading.value = true;
    apiMessage.value = '';
    try {
        const response = await axios.post(`/api/v1/exporter/batches/${route.query.id}/certificate/generate`, {});
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Sertifikat berhasil diterbitkan!';
            await fetchBatchDetail();
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal menerbitkan sertifikat.';
    } finally {
        isLoading.value = false;
    }
};

const publishCertificate = async () => {
    isLoading.value = true;
    apiMessage.value = '';
    try {
        const response = await axios.post(`/api/v1/exporter/batches/${route.query.id}/certificate/publish`, {});
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Sertifikat berhasil dipublikasikan ke Blockchain Polygon!';
            await fetchBatchDetail();
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal mempublikasikan ke Blockchain.';
    } finally {
        isLoading.value = false;
    }
};

const releaseBatch = async () => {
    isLoading.value = true;
    apiMessage.value = '';
    try {
        const response = await axios.post(`/api/v1/exporter/batches/${route.query.id}/release`, {});
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Batch berhasil dirilis dari marketplace!';
            await fetchBatchDetail();
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal merilis batch.';
    } finally {
        isLoading.value = false;
    }
};

onBeforeUnmount(() => {
    safeDestroyChart(warehouseChartRef);
    safeDestroyChart(logFullChartRef);
    safeDestroyChart(snapshotLogChartRef);
});

onMounted(() => {
    fetchBatchDetail();
});
</script>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* ensure chart canvas doesn't add visible background */
.chartjs-render-monitor {
    background: transparent;
}
</style>
