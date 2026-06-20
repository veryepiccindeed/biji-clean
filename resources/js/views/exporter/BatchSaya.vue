<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-exporter font-sans">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 500px at 20% 10%, rgba(75, 56, 50, 0.35), transparent 60%), radial-gradient(900px 520px at 80% 30%, rgba(8, 194, 70, 0.15), transparent 60%);"></div>

            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10">
                <header class="flex items-start justify-between gap-6">
                    <div>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Batch Saya</h1>
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
                            <div class="flex flex-wrap items-center justify-between gap-6 pb-4 mb-2 border-b border-[#2E241C]/50 text-[12px] text-[#A89880]">
                                <div class="flex flex-wrap items-center gap-6">
                                    <button @click="setFilter(null)" :class="{'text-[#F5EFE6] font-bold': !selectedFilter}" class="hover:text-[#F5EFE6]">Semua</button>
                                    <button @click="setFilter('draft')" :class="{'text-[#F5EFE6] font-bold': selectedFilter === 'draft'}" class="flex items-center gap-2 hover:text-[#F5EFE6]">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#5C4F42]"></span>
                                        <span>Draft</span>
                                    </button>
                                    <button @click="setFilter('published')" :class="{'text-[#4CAF7D] font-bold': selectedFilter === 'published'}" class="flex items-center gap-2 hover:text-[#4CAF7D]">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#4CAF7D]"></span>
                                        <span>Terbit</span>
                                    </button>
                                    <button @click="setFilter('locked')" :class="{'text-[#E8A838] font-bold': selectedFilter === 'locked'}" class="flex items-center gap-2 hover:text-[#E8A838]">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#E8A838]"></span>
                                        <span>Terkunci</span>
                                    </button>
                                    <button @click="setFilter('sold')" :class="{'text-[#4C8ED9] font-bold': selectedFilter === 'sold'}" class="flex items-center gap-2 hover:text-[#4C8ED9]">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#4C8ED9]"></span>
                                        <span>Terjual</span>
                                    </button>
                                </div>
                            </div>

                            <div class="hidden lg:grid grid-cols-[0.9fr_1.2fr_1.2fr_1fr_1fr_0.7fr_1.3fr] gap-4 text-[13px] text-[#A89880] border-b border-[#2E241C] pb-4 pt-4">
                                <div class="pl-2">No. Batch</div>
                                <div class="pl-12">Varietas</div>
                                <div class="pl-16">Status</div>
                                <div class="pl-4">Blockchain</div>
                                <div class="pl-4">Harga (Rp)</div>
                                <div class="pl-4">Pembeli</div>
                                <div class="text-center">Aksi</div>
                            </div>

                            <div
                                v-for="batch in myBatches"
                                :key="batch.id"
                                :class="[batch.rowClass, 'flex flex-col lg:grid lg:grid-cols-[0.9fr_1.2fr_1.2fr_1fr_1fr_0.7fr_1.3fr] lg:items-center gap-4 py-5 text-[15px] border-b border-[#2E241C] lg:border-transparent']"
                            >
                                <div class="lg:pl-2 font-mono text-[13px] text-[#A89880] flex justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">No. Batch</span>
                                    <span>{{ batch.kode }}</span>
                                </div>
                                <div class="lg:pl-4 flex justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">Varietas</span>
                                    <div class="text-[#F5EFE6]">{{ batch.varietas }}</div>
                                </div>
                                <div class="flex items-center lg:pl-19 justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">Status</span>
                                    <span :class="statusClass(batch.status)" class="w-2.5 h-2.5 rounded-full block" :title="batch.statusLabel"></span>
                                </div>
                                <div class="lg:pl-10 flex justify-between lg:justify-start w-full lg:w-auto items-center">
                                    <span class="text-xs text-[#A89880] lg:hidden">Blockchain</span>
                                    <i v-if="batch.blockchain === 'linked'" class="ri-link-m text-[#5C4F42] text-lg"></i>
                                    <i v-else class="ri-checkbox-circle-fill text-[#4CAF7D] text-lg"></i>
                                </div>
                                <div :class="batch.priceClass" class="flex justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">Harga</span>
                                    <span>{{ batch.harga }}</span>
                                </div>
                                <div :class="batch.buyerClass" class="flex justify-between lg:justify-start w-full lg:w-auto">
                                    <span class="text-xs text-[#A89880] lg:hidden">Pembeli</span>
                                    <span>{{ batch.pembeli }}</span>
                                </div>
                                <div class="flex items-center justify-start lg:justify-center gap-2 mt-4 lg:mt-0 w-full lg:w-auto">
                                    <router-link :to="{ name: 'exporter.batch-saya-detail', query: { id: batch.id } }" class="w-full lg:w-auto">
                                        <button class="px-3 py-2 w-full min-w-[110px] rounded-md border border-[#4A3728] text-[#F5EFE6] hover:bg-[#2A2118] transition font-bold">{{ batch.actionLabel }}</button>
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
const currentRouteName = computed(() => route?.name ?? 'exporter.batch-saya');

const logoUrl = '/assets/logo-fix.png';
const pageSubtitle = 'Kelola proses sertifikasi dan penjualan batch kopi Anda';

const myBatches = ref([]);
const selectedFilter = ref(null);

const setFilter = (filter) => {
    selectedFilter.value = filter;
    fetchMyBatches();
};

const fetchMyBatches = async () => {
    try {
        const response = await axios.get('/api/v1/exporter/batches/mine', {
            params: {
                filter: selectedFilter.value || undefined
            }
        });
        if (response.data?.success && response.data?.data) {
            const list = Array.isArray(response.data.data) ? response.data.data : (response.data.data.items || []);
            myBatches.value = list.map((b, index) => ({
                id: b.id,
                kode: b.batch_code || b.code || '-',
                varietas: b.variety || b.varietas || '-',
                status: b.status || 'draft',
                statusLabel: b.status || 'Draft',
                blockchain: b.certificate_id ? 'verified' : 'linked',
                harga: b.price ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(b.price) : '-',
                pembeli: b.buyer_name || '-',
                actionLabel: 'Kelola',
                rowClass: index === list.length - 1 ? '' : 'border-b border-[#2E241C]',
                priceClass: b.price ? 'pl-2 text-[#F5EFE6]' : 'pl-10 text-[#5C4F42]',
                buyerClass: b.buyer_name ? 'pl-2 truncate text-[#F5EFE6]' : 'pl-8 text-[#5C4F42]'
            }));
        }
    } catch (err) {
        console.error('Failed to fetch my batches:', err);
    }
};

const statusClass = (status) => {
    if (status === 'draft') return 'bg-[#5C4F42]';
    if (status === 'published' || status === 'terbit') return 'bg-[#4CAF7D]';
    if (status === 'locked' || status === 'terkunci') return 'bg-[#E8A838]';
    return 'bg-[#4C8ED9]';
};

onMounted(() => {
    fetchMyBatches();
});

</script>
