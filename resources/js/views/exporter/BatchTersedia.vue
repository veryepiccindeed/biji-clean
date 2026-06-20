<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-exporter font-sans">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 500px at 20% 10%, rgba(75, 56, 50, 0.35), transparent 60%), radial-gradient(900px 520px at 80% 30%, rgba(8, 194, 70, 0.15), transparent 60%);"></div>
            
            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10">
                <header class="flex items-start justify-between gap-6">
                    <div>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Batch Tersedia</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">{{ pageSubtitle }}</p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="BIJI Logo"
                            class="w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mt-10 lg:-mr-10 pointer-events-none"
                        >
                    </div>
                </header>

                    <section class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-lg p-6 max-w-6xl mx-auto">
                            <div class="flex flex-wrap items-center justify-between gap-6 pb-4 mb-2">
                                <div class="flex flex-wrap items-center gap-6 text-[12px] text-[#A89880]">
                                    <button @click="setHealthFilter(null)" :class="{'text-[#F5EFE6] font-bold': !selectedHealth}" class="hover:text-[#F5EFE6]">Semua</button>
                                    <button @click="setHealthFilter('normal')" :class="{'text-[#4CAF7D] font-bold': selectedHealth === 'normal'}" class="flex items-center gap-2 hover:text-[#4CAF7D]">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#4CAF7D]"></span>
                                        <span>Normal</span>
                                    </button>
                                    <button @click="setHealthFilter('warning')" :class="{'text-[#E8A838] font-bold': selectedHealth === 'warning'}" class="flex items-center gap-2 hover:text-[#E8A838]">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#E8A838]"></span>
                                        <span>Peringatan</span>
                                    </button>
                                    <button @click="setHealthFilter('critical')" :class="{'text-[#D94F4F] font-bold': selectedHealth === 'critical'}" class="flex items-center gap-2 hover:text-[#D94F4F]">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#D94F4F]"></span>
                                        <span>Kritis</span>
                                    </button>
                                </div>
                                <div class="flex items-center gap-4 text-[12px]">
                                    <label class="text-[#A89880]">Urutkan:</label>
                                    <select v-model="selectedSort" @change="fetchAvailableBatches" class="bg-[#0F0D0B] border border-[#4A3728] text-[#F5EFE6] px-3 py-1.5 rounded-md focus:outline-none">
                                        <option value="">Terbaru</option>
                                        <option value="elevation">Elevasi tertinggi</option>
                                        <option value="name">Nama (A-Z)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="hidden lg:grid grid-cols-[0.9fr_1.2fr_1.2fr_1fr_1fr_0.7fr_1.3fr] gap-4 text-[13px] text-[#A89880] border-b border-[#2E241C] pb-4">
                                <div class="pl-2">Kesehatan</div>
                                <div class="pl-4">Varietas &amp; Petani</div>
                                <div class="pl-4">Harga (Rp)</div>
                                <div class="pl-2">Tanggal Panen</div>
                                <div class="pl-2">Elevasi (mdpl)</div>
                                <div class="pl-6">JML Log</div>
                                <div class="text-center">Aksi</div>
                            </div>

                            <div
                                v-for="batch in availableBatches"
                                :key="batch.id"
                                :class="[batch.rowClass, 'flex flex-col lg:grid lg:grid-cols-[0.9fr_1.2fr_1.2fr_1fr_1fr_0.7fr_1.3fr] lg:items-center gap-4 py-5 text-[15px] border-b border-[#2E241C] lg:border-transparent']"
                            >
                                <div class="flex items-center lg:pl-8 justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">Kesehatan</span>
                                    <span :class="healthClass(batch.health)" class="w-2.5 h-2.5 rounded-full block"></span>
                                </div>
                                <div class="lg:pl-4 flex flex-row lg:flex-col justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">Varietas</span>
                                    <div class="text-right lg:text-left">
                                        <div class="text-[#F5EFE6]">{{ batch.varietas }}</div>
                                        <div class="text-[12px] text-[#A89880]">{{ batch.petani }}</div>
                                    </div>
                                </div>
                                <div class="lg:pl-12 flex justify-between lg:justify-start w-full lg:w-auto text-[#A89880]">
                                    <span class="text-xs text-[#A89880] lg:hidden">Harga</span>
                                    <span>{{ batch.harga }}</span>
                                </div>
                                <div class="lg:pl-4 flex justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">Tanggal Panen</span>
                                    <span>{{ batch.tanggalPanen }}</span>
                                </div>
                                <div class="lg:pl-10 flex justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">Elevasi</span>
                                    <span>{{ batch.elevasi }}</span>
                                </div>
                                <div class="lg:pl-10 flex justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">JML Log</span>
                                    <span>{{ batch.jmlLog }}</span>
                                </div>
                                <div class="flex items-center justify-start lg:justify-center gap-2 mt-4 lg:mt-0 w-full lg:w-auto">
                                    <router-link
                                        :to="{ name: 'exporter.batch-detail', query: { id: batch.batch_id || batch.id } }"
                                        class="px-3 py-2 min-w-[110px] w-full lg:w-auto rounded-md border border-[#4A3728] text-[#F5EFE6] hover:bg-[#2A2118] transition inline-flex items-center justify-center font-bold"
                                    >
                                        Lihat Detail
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '../../components/exporter/Sidebar.vue';
import axios from 'axios';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'exporter.batch-tersedia');

const logoUrl = '/assets/logo-fix.png';
const pageSubtitle = 'Daftar panen petani yang siap diambil';

const availableBatches = ref([]);
const selectedHealth = ref(null);
const selectedSort = ref('');

const setHealthFilter = (health) => {
    selectedHealth.value = health;
    fetchAvailableBatches();
};

const fetchAvailableBatches = async () => {
    try {
        const response = await axios.get('/api/v1/exporter/batches/available', {
            params: {
                health_filter: selectedHealth.value || undefined,
                sort: selectedSort.value || undefined
            }
        });
        if (response.data?.success && response.data?.data) {
            // Check if backend data is in 'data' directly or paginated structure
            const batchesList = Array.isArray(response.data.data) ? response.data.data : (response.data.data.items || []);
            availableBatches.value = batchesList.map((b, index) => ({
                id: b.id,
                batch_id: b.batch_id,
                health: b.health_status || 'normal',
                varietas: b.variety || b.varietas || '-',
                petani: b.farmer_name || '-',
                harga: b.price ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(b.price) : '-',
                tanggalPanen: b.tanggal_panen || b.harvest_date || '-',
                elevasi: b.elevation_mdpl || '-',
                jmlLog: b.logs_count || 0,
                rowClass: index === batchesList.length - 1 ? '' : 'border-b border-[#2E241C]'
            }));
        }
    } catch (err) {
        console.error('Failed to fetch available batches:', err);
    }
};

const healthClass = (health) => {
    if (health === 'warning') return 'bg-[#E8A838]';
    if (health === 'critical') return 'bg-[#D94F4F]';
    return 'bg-[#4CAF7D]';
};

onMounted(() => {
    fetchAvailableBatches();
});

</script>
