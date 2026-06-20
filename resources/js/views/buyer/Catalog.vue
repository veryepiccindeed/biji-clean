<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-buyer font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-12 py-6 pt-24 lg:pt-12 lg:py-12 relative z-10 overflow-y-auto">
                <header class="flex justify-between items-start mb-10 lg:mb-12 gap-6">
                    <div class="w-full">
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Katalog Biji Kopi</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">Biji kopi pilihan yang telah terverifikasi blockchain.</p>

                        <div class="mt-6 sm:mt-8 flex items-center gap-4 w-full">
                            <div class="relative w-full sm:w-80">
                                <input
                                    v-model="searchQuery"
                                    @input="onSearch"
                                    type="text"
                                    placeholder="Cari varietas atau daerah..."
                                    class="bg-[#1C1813] border border-[#2E241C] rounded-xl pl-10 pr-4 py-3 text-[14px] w-full focus:border-[#B8902A] outline-none transition-all"
                                />
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#5C4F42]">🔍</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <img
                            :src="logoUrl"
                            alt="Logo"
                            class="w-[138px] h-[138px] object-contain -mt-10 -mr-10 pointer-events-none"
                        >
                    </div>
                </header>

                <!-- Filters -->
                <div class="flex gap-3 mb-10 overflow-x-auto pb-4">
                    <button
                        v-for="filter in filters"
                        :key="filter"
                        :class="activeFilter === filter ? 'bg-[#B8902A] text-white' : 'bg-[#1C1813] border border-[#2E241C] text-[#A89880]'"
                        class="px-6 py-2 rounded-full text-[13px] font-bold whitespace-nowrap transition-all"
                        @click="selectFilter(filter)"
                    >
                        {{ filter }}
                    </button>
                </div>

                <!-- Product Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                    <div v-for="item in catalogItems" :key="item.id" class="group bg-[#1C1813] border border-[#2E241C] rounded-[32px] overflow-hidden hover:border-[#B8902A]/40 transition-all duration-500 flex flex-col">
                        <div class="h-56 bg-gradient-to-br from-[#2A2118] to-[#1C1813] relative overflow-hidden shrink-0">
                            <div class="absolute inset-0 flex items-center justify-center text-[100px] opacity-10 group-hover:scale-110 transition-transform duration-700">
                                ☕
                            </div>
                            <div class="absolute top-4 left-4 flex gap-2">
                                <span class="bg-[#0F0D0B]/80 backdrop-blur-md border border-[#2E241C] px-3 py-1 rounded-full text-[10px] font-bold text-[#F5EFE6] uppercase tracking-widest">
                                    {{ item.origin }}
                                </span>
                                <span v-if="item.isRare" class="bg-[#B8902A] px-3 py-1 rounded-full text-[10px] font-bold text-white uppercase tracking-widest">
                                    Limited
                                </span>
                            </div>
                            <div class="absolute bottom-4 right-4 bg-[#08C246] w-8 h-8 rounded-full flex items-center justify-center text-white text-[12px] shadow-lg shadow-[#08C246]/20" title="Verified Blockchain">
                                ✓
                            </div>
                        </div>

                        <div class="p-8 flex-1 flex flex-col">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-[22px] font-serif font-bold group-hover:text-[#D4AF5A] transition-colors">{{ item.name }}</h3>
                                    <p class="text-[13px] text-[#A89880] font-medium">{{ item.variety }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-8">
                                <div class="bg-[#0F0D0B] border border-[#2E241C] p-3 rounded-2xl">
                                    <p class="text-[10px] text-[#5C4F42] uppercase tracking-widest mb-1">Proses</p>
                                    <p class="text-[12px] font-bold text-[#F5EFE6]">{{ item.process }}</p>
                                </div>
                                <div class="bg-[#0F0D0B] border border-[#2E241C] p-3 rounded-2xl">
                                    <p class="text-[10px] text-[#5C4F42] uppercase tracking-widest mb-1">Elevasi</p>
                                    <p class="text-[12px] font-bold text-[#F5EFE6]">{{ item.elevation }}</p>
                                </div>
                            </div>

                            <div class="mt-auto pt-6 border-t border-[#2E241C] flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] text-[#5C4F42] uppercase tracking-widest mb-1">Harga per Kg</p>
                                    <p class="text-[18px] font-mono font-bold text-[#F5EFE6]">{{ item.price }}</p>
                                </div>
                                <router-link :to="{ name: 'buyer.catalog-detail', query: { id: item.id } }" class="px-6 py-3 bg-[#2A2118] border border-[#4A3728] rounded-2xl text-[12px] font-bold text-[#F5EFE6] hover:bg-[#B8902A] hover:border-[#B8902A] transition-all">
                                    Detail
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="hasMore || cursorStack.length > 0" class="mt-16 flex justify-center gap-4">
                    <button :disabled="cursorStack.length === 0" @click="goPrev" class="px-6 py-3 rounded-xl border border-[#2E241C] flex items-center justify-center text-[#A89880] hover:bg-[#1C1813] disabled:opacity-30 disabled:cursor-not-allowed font-bold">← Sebelumnya</button>
                    <button :disabled="!hasMore" @click="goNext" class="px-6 py-3 rounded-xl border border-[#2E241C] flex items-center justify-center text-[#A89880] hover:bg-[#1C1813] disabled:opacity-30 disabled:cursor-not-allowed font-bold">Berikutnya →</button>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '../../components/buyer/Sidebar.vue';
import axios from 'axios';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'buyer.catalog');
const logoUrl = '/assets/logo-fix.png';

const activeFilter = ref('Semua');
const filters = ['Semua', 'Arabika', 'Robusta', 'Specialty', 'Single Origin', 'Rare Lot'];

const catalogItems = ref([]);
const searchQuery = ref('');

const nextCursor = ref(null);
const cursorStack = ref([]);
const currentCursor = ref(null);
const hasMore = ref(false);

const filterMapping = {
    'Semua': 'all',
    'Arabika': 'arabika',
    'Robusta': 'robusta',
    'Specialty': 'specialty',
    'Single Origin': 'single_origin',
    'Rare Lot': 'rare_lot'
};

const fetchCatalog = async (cursor = null) => {
    try {
        const mappedFilter = filterMapping[activeFilter.value] || 'all';
        const { data } = await axios.get('/api/v1/buyer/catalog', {
            params: {
                search: searchQuery.value || undefined,
                filter: mappedFilter !== 'all' ? mappedFilter : undefined,
                cursor: cursor || undefined,
                limit: 9
            }
        });
        if (data.success) {
            const list = data.data || [];
            catalogItems.value = list.map(item => ({
                id: item.id,
                name: item.name,
                variety: item.variety,
                process: item.process,
                origin: item.origin,
                elevation: item.elevation ? `${item.elevation} mdpl` : '-',
                price: item.price_per_kg_display,
                isRare: item.category === 'rare_lot' || item.category === 'specialty'
            }));
            nextCursor.value = data.pagination?.cursor || null;
            hasMore.value = data.pagination?.hasMore || false;
            currentCursor.value = cursor;
        }
    } catch (err) {
        console.error('Failed to load buyer catalog:', err);
    }
};

const onSearch = () => {
    cursorStack.value = [];
    fetchCatalog();
};

const selectFilter = (filter) => {
    activeFilter.value = filter;
    cursorStack.value = [];
    fetchCatalog();
};

const goNext = () => {
    if (nextCursor.value) {
        cursorStack.value.push(currentCursor.value);
        fetchCatalog(nextCursor.value);
    }
};

const goPrev = () => {
    if (cursorStack.value.length > 0) {
        const prev = cursorStack.value.pop();
        fetchCatalog(prev);
    }
};

onMounted(() => {
    fetchCatalog();
});
</script>
