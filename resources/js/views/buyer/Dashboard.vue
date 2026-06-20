<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-buyer font-sans selection:bg-[#B8902A]/30" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row overflow-hidden">
            <!-- Decorative Elements -->
            <div class="absolute top-0 right-0 w-[1000px] h-[1000px] bg-[#B8902A]/5 rounded-full blur-[120px] -mr-96 -mt-96 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-[800px] h-[800px] bg-[#08C246]/5 rounded-full blur-[100px] -ml-96 -mb-96 pointer-events-none"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full opacity-[0.02] pointer-events-none" style="background-image: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');"></div>

            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-12 py-6 pt-24 lg:pt-12 lg:py-12 relative z-10 overflow-y-auto">
                <!-- Header Section -->
                <header class="flex justify-between items-start mb-10 lg:mb-16 gap-6">
                    <div class="space-y-2">
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">
                            Selamat Datang, <span class="text-[#D4AF5A] italic">{{ userName }}</span>
                        </h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">
                            Jelajahi biji kopi terbaik dengan verifikasi blockchain yang menjamin transparansi dari kebun hingga ke tangan Anda.
                        </p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="Logo"
                            class="w-[138px] h-[138px] object-contain -mt-10 -mr-10 pointer-events-none"
                        >
                    </div>
                </header>

                <!-- Featured Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
                    <div class="relative group">
                        <div class="absolute inset-0 bg-[#B8902A] opacity-0 group-hover:opacity-[0.02] blur-xl transition-opacity rounded-3xl"></div>
                        <div class="bg-[#1C1813] border border-[#2E241C] p-8 rounded-3xl relative overflow-hidden h-full flex flex-col">
                            <div class="flex justify-between items-start mb-8">
                                <div class="w-12 h-12 rounded-2xl bg-[#B8902A]/10 border border-[#B8902A]/20 flex items-center justify-center text-[#D4AF5A] text-xl font-serif italic">
                                    Σ
                                </div>
                            </div>
                            <p class="text-[13px] text-[#A89880] uppercase tracking-widest font-semibold mb-2">Total Pengadaan Selesai</p>
                            <h3 class="text-[36px] font-serif font-bold text-[#F5EFE6]">{{ stats.totalTransactions }}</h3>
                            <div class="mt-auto pt-6 border-t border-[#2E241C] flex justify-between items-center">
                                <span class="text-[11px] text-[#5C4F42]">Periode: 2026</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#1C1813] border border-[#2E241C] p-8 rounded-3xl relative overflow-hidden h-full flex flex-col">
                        <div class="flex justify-between items-start mb-8">
                            <div class="w-12 h-12 rounded-2xl bg-[#08C246]/10 border border-[#08C246]/20 flex items-center justify-center text-[#08C246] text-xl font-serif italic">
                                ◈
                            </div>
                        </div>
                        <p class="text-[13px] text-[#A89880] uppercase tracking-widest font-semibold mb-2">Pesanan Aktif</p>
                        <h3 class="text-[36px] font-serif font-bold text-[#F5EFE6]">{{ stats.activeOrders }} <span class="text-[16px] font-sans text-[#5C4F42]">Pesanan</span></h3>
                        <div class="mt-auto pt-6 border-t border-[#2E241C] flex justify-between items-center">
                            <span class="text-[11px] text-[#5C4F42]">Verified on Polygon</span>
                            <span class="text-[11px] text-[#08C246]">Tersertifikasi ✓</span>
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-[#2D2210] to-[#1C1813] border border-[#B8902A]/20 p-8 rounded-3xl relative overflow-hidden h-full flex flex-col shadow-2xl">
                        <div class="flex justify-between items-start mb-8">
                            <div class="w-12 h-12 rounded-2xl bg-[#D4AF5A] flex items-center justify-center text-[#1C1813] text-xl font-serif italic">
                                ⬘
                            </div>
                        </div>
                        <p class="text-[13px] text-[#D4AF5A] uppercase tracking-widest font-semibold mb-2">Dalam Pengiriman</p>
                        <h3 class="text-[36px] font-serif font-bold text-[#F5EFE6]">{{ stats.inTransit }} <span class="text-[16px] font-sans text-[#5C4F42]">Kargo</span></h3>
                        <div class="mt-auto pt-6 flex flex-col gap-2">
                            <span class="text-[11px] text-[#A89880]">Menuju pelabuhan tujuan</span>
                        </div>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- Catalog Teaser -->
                    <div class="lg:col-span-8 space-y-8">
                        <div class="flex justify-between items-end">
                            <h2 class="text-[24px] font-serif font-bold">Rekomendasi Eksklusif</h2>
                            <router-link :to="{ name: 'buyer.catalog' }" class="text-[13px] text-[#D4AF5A] hover:underline font-medium uppercase tracking-widest">Lihat Semua →</router-link>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-for="item in featuredItems" :key="item.id" class="group bg-[#1C1813] border border-[#2E241C] rounded-[32px] overflow-hidden hover:border-[#B8902A]/40 transition-all duration-500">
                                <div class="h-48 bg-gradient-to-br from-[#2A2118] to-[#1C1813] relative overflow-hidden">
                                    <div class="absolute inset-0 flex items-center justify-center text-[80px] opacity-20 filter grayscale group-hover:grayscale-0 group-hover:scale-110 transition-all duration-700">
                                        {{ item.icon }}
                                    </div>
                                    <div class="absolute top-4 right-4 bg-[#0F0D0B]/60 backdrop-blur-md border border-[#2E241C] px-3 py-1 rounded-full text-[10px] font-bold text-[#F5EFE6] uppercase tracking-widest">
                                        {{ item.origin }}
                                    </div>
                                    <div class="absolute bottom-0 left-0 w-full p-6 bg-gradient-to-t from-[#1C1813] to-transparent">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-[#4CAF7D]"></span>
                                            <span class="text-[11px] font-bold text-[#4CAF7D] uppercase tracking-widest">{{ item.status }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-8">
                                    <h4 class="text-[20px] font-serif font-bold mb-1">{{ item.name }}</h4>
                                    <p class="text-[13px] text-[#A89880] mb-6 font-medium">{{ item.variety }} • {{ item.process }}</p>

                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-[10px] text-[#5C4F42] uppercase tracking-[0.2em] mb-1">Mulai Dari</p>
                                            <p class="text-[18px] font-bold text-[#F5EFE6] font-mono">{{ item.price }}</p>
                                        </div>
                                        <button @click="$router.push({ name: 'buyer.catalog-detail', query: { id: item.id } })" class="w-12 h-12 rounded-2xl bg-[#2A2118] border border-[#4A3728] flex items-center justify-center text-[#F5EFE6] hover:bg-[#B8902A] hover:border-[#B8902A] transition-all group-hover:shadow-[0_0_20px_rgba(184,144,42,0.2)]">
                                            →
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Content -->
                    <div class="lg:col-span-4 space-y-8">
                        <h2 class="text-[24px] font-serif font-bold">Aktivitas Terkini</h2>

                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-[32px] p-8 space-y-8 relative overflow-hidden">

                            <div v-for="(activity, index) in recentActivities" :key="index" class="flex gap-4 group cursor-pointer" @click="$router.push({ name: 'buyer.orders' })">
                                <div class="shrink-0 w-12 h-12 rounded-2xl bg-[#0F0D0B] border border-[#2E241C] flex items-center justify-center text-lg group-hover:border-[#B8902A]/40 transition-colors">
                                    {{ activity.icon }}
                                </div>
                                <div class="space-y-1">
                                    <p class="text-[14px] font-bold text-[#F5EFE6] group-hover:text-[#D4AF5A] transition-colors leading-tight">{{ activity.title }}</p>
                                    <p class="text-[12px] text-[#A89880]">{{ activity.time }}</p>
                                    <p class="text-[12px] text-[#5C4F42] font-mono truncate w-48">{{ activity.hash }}</p>
                                </div>
                            </div>

                            <button @click="$router.push({ name: 'buyer.orders' })" class="w-full py-4 bg-[#2A2118] border border-[#4A3728] rounded-2xl text-[12px] font-bold uppercase tracking-[0.2em] text-[#A89880] hover:text-[#F5EFE6] hover:border-[#B8902A]/40 transition-all">
                                Seluruh Aktivitas
                            </button>
                        </div>
                    </div>
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
import { useUserProfileStore } from '../../stores/userProfile';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'buyer.dashboard');
const logoUrl = '/assets/logo-fix.png';
const userProfileStore = useUserProfileStore();
const userName = computed(() => userProfileStore.profile.fullName || 'User');

const stats = ref({
    activeOrders: 0,
    inTransit: 0,
    pendingPayment: 0,
    totalTransactions: 'Rp 0'
});

const featuredItems = ref([]);
const recentActivities = ref([]);

const fetchDashboardData = async () => {
    try {
        const { data } = await axios.get('/api/v1/buyer/dashboard');
        if (data.success && data.data) {
            const payload = data.data;
            stats.value = {
                activeOrders: payload.stats?.active_orders ?? 0,
                inTransit: payload.stats?.in_transit ?? 0,
                pendingPayment: payload.stats?.pending_payment ?? 0,
                totalTransactions: payload.stats?.total_transactions ?? 'Rp 0'
            };
            const ordersList = Array.isArray(payload.recent_orders) ? payload.recent_orders : (payload.recent_orders?.data || []);
            recentActivities.value = ordersList.map(order => ({
                icon: order.status === 'completed' ? '📦' : '💳',
                title: `${order.batch?.name || 'Pesanan'} (${order.status_label})`,
                time: new Date(order.created_at).toLocaleDateString('id-ID'),
                hash: `Total: Rp ${Number(order.total).toLocaleString('id-ID')}`
            }));
        }
    } catch (err) {
        console.error('Failed to load buyer dashboard:', err);
    }
};

const fetchFeaturedItems = async () => {
    try {
        const { data } = await axios.get('/api/v1/buyer/catalog', {
            params: { limit: 2 }
        });
        if (data.success && data.data) {
            const list = Array.isArray(data.data) ? data.data : (data.data.items || []);
            featuredItems.value = list.map(item => ({
                id: item.id,
                icon: '🫘',
                name: item.name,
                variety: item.variety,
                process: item.process,
                origin: item.origin,
                status: item.badge || 'Premium',
                price: item.price_per_kg_display + ' /kg'
            }));
        }
    } catch (err) {
        console.error('Failed to load featured items:', err);
    }
};

onMounted(() => {
    fetchDashboardData();
    fetchFeaturedItems();
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
