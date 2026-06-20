<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-buyer font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-12 py-6 pt-24 lg:pt-12 lg:py-12 relative z-10 overflow-y-auto">
                <header class="flex justify-between items-start mb-10 lg:mb-12 gap-6">
                    <div>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Pesanan Saya</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">Lacak status pengadaan dan dokumen digital Anda.</p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="Logo"
                            class="w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mt-10 lg:-mr-10 pointer-events-none"
                        >
                    </div>
                </header>

                <section class="mt-10">
                    <div class="bg-[#1C1813] border border-[#2E241C] rounded-lg p-6 max-w-6xl mx-auto">
                        <div class="flex flex-wrap items-center gap-4 text-[12px] text-[#A89880] pb-4 mb-2 border-b border-[#2E241C]/50">
                            <button @click="filterStatus(null)" :class="{'text-[#F5EFE6] font-bold': !selectedStatus}" class="hover:text-[#F5EFE6]">Semua</button>
                            <button @click="filterStatus('pending_payment')" :class="{'text-[#eab308] font-bold': selectedStatus === 'pending_payment'}" class="flex items-center gap-2 hover:text-[#eab308]">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#eab308]"></span>
                                <span>Menunggu Pembayaran</span>
                            </button>
                            <button @click="filterStatus('payment_verifying')" :class="{'text-[#eab308] font-bold': selectedStatus === 'payment_verifying'}" class="flex items-center gap-2 hover:text-[#eab308]">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#eab308]"></span>
                                <span>Verifikasi Pembayaran</span>
                            </button>
                            <button @click="filterStatus('paid')" :class="{'text-[#3b82f6] font-bold': selectedStatus === 'paid'}" class="flex items-center gap-2 hover:text-[#3b82f6]">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6]"></span>
                                <span>Lunas</span>
                            </button>
                            <button @click="filterStatus('in_transit')" :class="{'text-[#3b82f6] font-bold': selectedStatus === 'in_transit'}" class="flex items-center gap-2 hover:text-[#3b82f6]">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6]"></span>
                                <span>Dikirim</span>
                            </button>
                            <button @click="filterStatus('completed')" :class="{'text-[#22c55e] font-bold': selectedStatus === 'completed'}" class="flex items-center gap-2 hover:text-[#22c55e]">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#22c55e]"></span>
                                <span>Selesai</span>
                            </button>
                            <button @click="filterStatus('cancelled')" :class="{'text-[#ef4444] font-bold': selectedStatus === 'cancelled'}" class="flex items-center gap-2 hover:text-[#ef4444]">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span>
                                <span>Dibatalkan</span>
                            </button>
                        </div>

                        <!-- Table Header -->
                        <div class="hidden lg:grid grid-cols-[1.2fr_1.5fr_1.5fr_1fr_1.2fr_0.8fr] gap-4 text-[13px] text-[#A89880] border-b border-[#2E241C] pb-4 pt-4 px-4 uppercase tracking-widest font-bold">
                            <div>ID Pesanan</div>
                            <div>Produk</div>
                            <div>Eksportir</div>
                            <div class="text-center">Status</div>
                            <div class="text-right">Total</div>
                            <div class="text-center">Aksi</div>
                        </div>

                        <!-- Table Body -->
                        <div v-if="orders.length === 0" class="py-12 text-center text-[#A89880] text-[14px]">
                            Tidak ada pesanan ditemukan.
                        </div>
                        <div
                            v-else
                            v-for="order in orders"
                            :key="order.id"
                            class="flex flex-col lg:grid lg:grid-cols-[1.2fr_1.5fr_1.5fr_1fr_1.2fr_0.8fr] lg:items-center gap-4 py-5 text-[15px] border-b border-[#2E241C] last:border-0 hover:bg-[#2A2118]/30 transition-colors px-4"
                        >
                            <div class="font-mono text-[13px] text-[#A89880] flex justify-between lg:justify-start w-full lg:w-auto">
                                <span class="text-xs text-[#A89880] lg:hidden">ID Pesanan</span>
                                <span>{{ order.id }}</span>
                            </div>
                            <div class="flex flex-row lg:flex-col justify-between lg:justify-start w-full lg:w-auto">
                                <span class="text-xs text-[#A89880] lg:hidden">Produk</span>
                                <div class="text-right lg:text-left">
                                    <div class="text-[#F5EFE6] font-bold">{{ order.product_name }}</div>
                                    <div class="text-[12px] text-[#A89880]">{{ order.product_variety }} · {{ order.weight_kg }} kg</div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between lg:justify-start gap-2 w-full lg:w-auto">
                                <span class="text-xs text-[#A89880] lg:hidden">Eksportir</span>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-[#4B3832] flex items-center justify-center text-[10px] text-white">
                                        {{ (order.exporter?.name || 'E').charAt(0) }}
                                    </div>
                                    <span class="text-[14px] text-[#F5EFE6]">{{ order.exporter?.name }}</span>
                                </div>
                            </div>
                            <div class="flex lg:flex-col items-center justify-between lg:justify-center w-full lg:w-auto">
                                <span class="text-xs text-[#A89880] lg:hidden">Status</span>
                                <span :style="{ backgroundColor: order.status_color + '15', color: order.status_color }" class="px-2 py-0.5 rounded text-[11px] font-bold">
                                    {{ order.status_label }}
                                </span>
                            </div>
                            <div class="text-right font-mono text-[#F5EFE6] font-bold flex justify-between lg:justify-end w-full lg:w-auto">
                                <span class="text-xs text-[#A89880] lg:hidden">Total</span>
                                <span>{{ order.total_display }}</span>
                            </div>
                            <div class="flex items-center justify-start lg:justify-center mt-4 lg:mt-0 w-full lg:w-auto">
                                <button @click="openOrderDetail(order.id)" class="px-3 py-1.5 w-full lg:w-auto rounded-md border border-[#4A3728] text-[#D4AF5A] text-[13px] font-bold hover:bg-[#2A2118] transition">Detail</button>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <!-- Detail Modal Overlay -->
        <transition name="fade">
            <div v-show="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-6">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeDetailModal"></div>
                
                <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto bg-[#1C1813] border border-[#2E241C] rounded-[32px] p-8 shadow-2xl z-60 text-left">
                    <button @click="closeDetailModal" class="absolute top-6 right-6 text-[#A89880] hover:text-[#F5EFE6] transition-colors text-xl">✕</button>
                    
                    <div v-if="!selectedOrder" class="py-12 text-center text-[#A89880]">
                        Memuat rincian pesanan...
                    </div>
                    
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Left side: General info, Pricing and Payment -->
                        <div class="space-y-6">
                            <div>
                                <p class="text-[11px] text-[#A89880] uppercase tracking-widest">Detail Pesanan</p>
                                <h3 class="text-[24px] font-serif font-bold text-[#F5EFE6] mt-1">{{ selectedOrder.id }}</h3>
                                <p class="text-[12px] text-[#A89880] mt-1">Dibuat pada: {{ new Date(selectedOrder.created_at).toLocaleString('id-ID') }}</p>
                            </div>

                            <div class="bg-[#0F0D0B] border border-[#2E241C] p-6 rounded-2xl space-y-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl bg-[#1C1813] border border-[#2E241C] flex items-center justify-center text-xl">☕</div>
                                    <div>
                                        <p class="font-bold text-[#F5EFE6] text-[15px]">{{ selectedOrder.product?.name }}</p>
                                        <p class="text-[12px] text-[#A89880]">{{ selectedOrder.product?.variety }} · {{ selectedOrder.product?.origin }}</p>
                                    </div>
                                </div>
                                <div class="pt-4 border-t border-[#2E241C] space-y-2 text-[13px]">
                                    <div class="flex justify-between">
                                        <span class="text-[#A89880]">Jumlah</span>
                                        <span class="font-bold text-[#F5EFE6]">{{ selectedOrder.quantity?.weight_display }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-[#A89880]">Harga per Kg</span>
                                        <span class="font-bold text-[#F5EFE6]">{{ formatCurrency(selectedOrder.price_per_kg) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-[#0F0D0B] border border-[#2E241C] p-6 rounded-2xl space-y-3 text-[13px]">
                                <p class="text-[11px] text-[#A89880] uppercase tracking-widest mb-1">Rincian Biaya</p>
                                <div class="flex justify-between">
                                    <span class="text-[#A89880]">Subtotal</span>
                                    <span class="font-mono text-[#F5EFE6]">{{ selectedOrder.pricing?.subtotal_display }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[#A89880]">Biaya Pengiriman</span>
                                    <span class="font-mono text-[#F5EFE6]">{{ selectedOrder.pricing?.shipping_cost_display }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[#A89880]">Biaya Platform</span>
                                    <span class="font-mono text-[#F5EFE6]">{{ selectedOrder.pricing?.platform_fee_display }}</span>
                                </div>
                                <div class="flex justify-between pt-3 border-t border-[#2E241C] items-end">
                                    <span class="font-bold text-[14px]">Total Pembayaran</span>
                                    <span class="font-mono font-bold text-[18px] text-[#D4AF5A]">{{ selectedOrder.pricing?.total_display }}</span>
                                </div>
                            </div>

                            <!-- Payment Section -->
                            <div v-if="selectedOrder.status === 'pending_payment'" class="bg-[#2D2210]/30 border border-[#B8902A]/20 p-6 rounded-2xl space-y-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-[12px] text-[#D4AF5A] uppercase tracking-widest font-bold">Metode: {{ selectedOrder.payment?.method_label }}</p>
                                        <p class="text-[11px] text-[#A89880] mt-1">{{ selectedOrder.payment?.payment_deadline_label }}</p>
                                    </div>
                                </div>

                                <!-- BCA VA -->
                                <div v-if="selectedOrder.payment?.method === 'bank_transfer'" class="bg-[#0F0D0B] p-4 rounded-xl border border-[#2E241C] flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] text-[#A89880] uppercase tracking-widest">Nomor Virtual Account (BCA)</p>
                                        <p class="font-mono text-[16px] font-bold text-[#F5EFE6] mt-1">{{ selectedOrder.payment?.va_number }}</p>
                                    </div>
                                    <button @click="copyToClipboard(selectedOrder.payment?.va_number)" class="text-[11px] text-[#D4AF5A] hover:underline">Salin</button>
                                </div>

                                <!-- QRIS -->
                                <div v-if="selectedOrder.payment?.method === 'qris'" class="flex flex-col items-center gap-3">
                                    <div class="bg-white p-3 rounded-xl">
                                        <img :src="selectedOrder.payment?.qr_image" alt="QRIS Code" class="w-40 h-40">
                                    </div>
                                    <p class="text-[11px] text-[#A89880]">Scan QRIS di atas dengan aplikasi e-wallet Anda.</p>
                                </div>

                                <!-- Action Upload -->
                                <div v-if="selectedOrder.actions_available?.can_upload_payment_proof" class="pt-2">
                                    <div v-if="uploadError" class="mb-3 px-3 py-2 bg-[#fee2e2] text-[#991b1b] border border-[#fca5a5] rounded-lg text-[11px] font-bold">
                                        {{ uploadError }}
                                    </div>
                                    <div v-if="uploadSuccess" class="mb-3 px-3 py-2 bg-[#d1fae5] text-[#065f46] border border-[#6ee7b7] rounded-lg text-[11px] font-bold">
                                        {{ uploadSuccess }}
                                    </div>
                                    
                                    <button @click="$refs.proof_file.click()" :disabled="isUploading" class="w-full py-3 bg-[#B8902A] text-white rounded-xl text-[13px] font-bold hover:bg-[#d9b93a] transition-all flex items-center justify-center gap-2 disabled:opacity-50">
                                        {{ isUploading ? 'Mengunggah...' : 'Unggah Bukti Pembayaran' }}
                                    </button>
                                    <input type="file" ref="proof_file" class="hidden" accept="image/*" @change="handleFileUpload" />
                                </div>
                            </div>
                        </div>

                        <!-- Right side: Timeline & Documents -->
                        <div class="space-y-6">
                            <div>
                                <p class="text-[11px] text-[#A89880] uppercase tracking-widest">Status Pengiriman</p>
                                <p class="font-bold text-[15px] text-[#F5EFE6] mt-1">{{ selectedOrder.port?.full_name }}</p>
                                <p class="text-[12px] text-[#A89880]">{{ selectedOrder.port?.eta_label }}</p>
                            </div>

                            <!-- Timeline -->
                            <div class="space-y-4">
                                <p class="text-[12px] text-[#A89880] uppercase tracking-wider">Histori Status</p>
                                <div class="relative border-l border-[#2E241C] ml-3 pl-6 space-y-6">
                                    <div v-for="step in selectedOrder.timeline" :key="step.id" class="relative">
                                        <!-- dot -->
                                        <div :class="step.is_current ? 'bg-[#D4AF5A] ring-4 ring-[#D4AF5A]/20' : 'bg-[#2E241C]'" class="absolute -left-[30px] top-1 w-3 h-3 rounded-full"></div>
                                        <div>
                                            <p :class="step.is_current ? 'text-[#F5EFE6] font-bold' : 'text-[#A89880]'" class="text-[13px]">{{ step.status_label }}</p>
                                            <p class="text-[12px] text-[#5C4F42] mt-0.5">{{ step.description }}</p>
                                            <p class="text-[10px] text-[#5C4F42] font-mono mt-0.5">{{ new Date(step.timestamp).toLocaleString('id-ID') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents -->
                            <div v-if="selectedOrder.documents && selectedOrder.documents.length > 0" class="pt-4 border-t border-[#2E241C] space-y-3">
                                <p class="text-[12px] text-[#A89880] uppercase tracking-wider">Dokumen Digital</p>
                                <div v-for="doc in selectedOrder.documents" :key="doc.id" class="flex items-center justify-between p-3 bg-[#0F0D0B] border border-[#2E241C] rounded-xl">
                                    <div>
                                        <p class="text-[13px] font-bold text-[#F5EFE6]">{{ doc.type_label }}</p>
                                        <p class="text-[11px] text-[#A89880] font-mono truncate w-48">{{ doc.filename }}</p>
                                    </div>
                                    <a :href="doc.url" download class="text-[#D4AF5A] text-[13px] font-bold hover:underline">Download ↗</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '../../components/buyer/Sidebar.vue';
import axios from 'axios';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'buyer.orders');
const logoUrl = '/assets/logo-fix.png';

const orders = ref([]);
const selectedStatus = ref(null);

const isModalOpen = ref(false);
const selectedOrder = ref(null);
const isUploading = ref(false);
const uploadError = ref('');
const uploadSuccess = ref('');

const fetchOrders = async () => {
    try {
        const { data } = await axios.get('/api/v1/buyer/orders', {
            params: {
                status: selectedStatus.value || undefined
            }
        });
        if (data.success && data.data) {
            orders.value = data.data;
        }
    } catch (err) {
        console.error('Failed to load orders:', err);
    }
};

const filterStatus = (status) => {
    selectedStatus.value = status;
    fetchOrders();
};

const openOrderDetail = async (orderId) => {
    isModalOpen.value = true;
    selectedOrder.value = null;
    uploadError.value = '';
    uploadSuccess.value = '';
    try {
        const { data } = await axios.get(`/api/v1/buyer/orders/${orderId}`);
        if (data.success && data.data) {
            selectedOrder.value = data.data.order;
        }
    } catch (err) {
        console.error('Failed to load order detail:', err);
    }
};

const closeDetailModal = () => {
    isModalOpen.value = false;
    selectedOrder.value = null;
};

const handleFileUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('proof_file', file);

    isUploading.value = true;
    uploadError.value = '';
    uploadSuccess.value = '';

    try {
        const response = await axios.post(`/api/v1/buyer/orders/${selectedOrder.value.id}/payment/confirm`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        if (response.data?.success) {
            uploadSuccess.value = 'Bukti pembayaran berhasil diunggah!';
            await openOrderDetail(selectedOrder.value.id);
            await fetchOrders();
        }
    } catch (err) {
        uploadError.value = err.response?.data?.message ?? 'Gagal mengunggah bukti pembayaran.';
    } finally {
        isUploading.value = false;
    }
};

const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        alert('VA BCA disalin ke clipboard');
    });
};

const formatCurrency = (val) => {
    return 'Rp ' + (val || 0).toLocaleString('id-ID');
};

onMounted(() => {
    fetchOrders();
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
