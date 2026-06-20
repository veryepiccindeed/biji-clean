<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-buyer font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10 relative z-10 overflow-y-auto">
                <header class="flex justify-between items-start mb-10 lg:mb-12">
                    <div>
                        <p class="mb-1 text-[11px] text-[#A89880] uppercase tracking-wider">Checkout Process</p>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Konfirmasi Pembelian</h1>
                    </div>
                    <img :src="logoUrl" alt="Logo" class="w-16 h-16 sm:w-24 sm:h-24 lg:w-[100px] lg:h-[100px] lg:-mt-10 lg:-mr-4 pointer-events-none hidden sm:block">
                </header>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 max-w-6xl">
                    <!-- Left: Form -->
                    <div class="lg:col-span-7 space-y-8">
                        <section class="bg-[#1C1813] border border-[#2E241C] rounded-[32px] p-8">
                            <h2 class="text-[18px] font-serif font-bold mb-6 flex items-center gap-3">
                                <span class="text-xl">📍</span> Alamat Pengiriman
                            </h2>
                            <div class="space-y-6">
                                 <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <button
                                            v-for="port in ports"
                                            :key="port.id"
                                            @click="selectedPort = port"
                                            type="button"
                                            :class="[
                                                'p-4 rounded-xl border text-left transition-all',
                                                selectedPort && selectedPort.id === port.id
                                                    ? 'border-[#B8902A] bg-[#2D2210]/30 shadow-[0_0_15px_rgba(184,144,42,0.15)]'
                                                    : 'border-[#2E241C] bg-[#0F0D0B] hover:border-[#4A3728]'
                                            ]"
                                        >
                                            <div class="flex justify-between items-start">
                                                <p class="font-bold text-[13px] text-[#F5EFE6]">{{ port.name }}</p>
                                                <span v-if="selectedPort && selectedPort.id === port.id" class="text-[#B8902A] text-xs">✓</span>
                                            </div>
                                            <p class="text-[11px] text-[#A89880] mt-1">{{ port.country }}</p>
                                            <div class="flex items-center gap-2 mt-3">
                                                <span class="px-2 py-0.5 bg-[#1C1813] text-[#D4AF5A] text-[9px] font-mono rounded border border-[#B8902A]/10">
                                                    Est. Tiba: {{ port.eta_label || port.eta_days + ' Hari' }}
                                                </span>
                                            </div>
                                        </button>
                                    </div>
                            </div>
                        </section>

                        <section class="bg-[#1C1813] border border-[#2E241C] rounded-[32px] p-8">
                            <h2 class="text-[18px] font-serif font-bold mb-6 flex items-center gap-3">
                                <span class="text-xl">💳</span> Metode Pembayaran
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <button
                                    type="button"
                                    @click="paymentMethod = 'bank_transfer'"
                                    :class="[
                                        'p-6 rounded-2xl border text-left transition-all',
                                        paymentMethod === 'bank_transfer'
                                            ? 'border-[#B8902A] bg-[#2D2210]/30 shadow-[0_0_15px_rgba(184,144,42,0.15)] text-[#F5EFE6]'
                                            : 'border-[#2E241C] bg-[#0F0D0B] hover:border-[#4A3728] text-[#A89880]'
                                    ]"
                                >
                                    <p class="font-bold">Transfer Bank</p>
                                    <p class="text-[12px] mt-1">Transfer Bank Menggunakan Virtual Account</p>
                                </button>
                                <button
                                    type="button"
                                    @click="paymentMethod = 'qris'"
                                    :class="[
                                        'p-6 rounded-2xl border text-left transition-all',
                                        paymentMethod === 'qris'
                                            ? 'border-[#B8902A] bg-[#2D2210]/30 shadow-[0_0_15px_rgba(184,144,42,0.15)] text-[#F5EFE6]'
                                            : 'border-[#2E241C] bg-[#0F0D0B] hover:border-[#4A3728] text-[#A89880]'
                                    ]"
                                >
                                    <p class="font-bold">QRIS</p>
                                    <p class="text-[12px] mt-1">Pembayaran Melalui QR Code Universal</p>
                                </button>
                            </div>
                        </section>
                    </div>

                    <!-- Right: Summary -->
                    <div class="lg:col-span-5">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-[32px] p-8 sticky top-10">
                            <h2 class="text-[18px] font-serif font-bold mb-8">Ringkasan Pesanan</h2>
                            <div class="space-y-6 mb-8">
                                 <div class="flex gap-4 items-center">
                                     <div class="w-16 h-16 rounded-xl bg-[#0F0D0B] border border-[#2E241C] flex items-center justify-center text-2xl">☕</div>
                                     <div class="flex-1">
                                         <p class="font-bold text-[15px] text-[#F5EFE6]">{{ batchName }}</p>
                                         <p class="text-[13px] text-[#A89880] mb-2">{{ formatCurrency(pricePerKg) }} / Kg</p>
                                         
                                         <!-- Interactive Counter -->
                                         <div class="flex items-center gap-2">
                                             <button
                                                 type="button"
                                                 @click="decrementWeight"
                                                 class="w-8 h-8 rounded-lg bg-[#0F0D0B] border border-[#2E241C] flex items-center justify-center text-[#A89880] hover:text-[#F5EFE6] hover:border-[#4A3728] transition-colors"
                                                 :disabled="weight <= 10"
                                                 :class="{ 'opacity-50 cursor-not-allowed': weight <= 10 }"
                                             >
                                                 −
                                             </button>
                                             <div class="relative flex items-center">
                                                 <input
                                                     type="number"
                                                     v-model.number="weight"
                                                     min="10"
                                                     max="5000"
                                                     class="w-24 text-center bg-[#0F0D0B] border border-[#2E241C] rounded-lg py-1 px-2 text-[13px] font-mono focus:outline-none focus:border-[#B8902A] text-[#F5EFE6]"
                                                 />
                                             </div>
                                             <button
                                                 type="button"
                                                 @click="incrementWeight"
                                                 class="w-8 h-8 rounded-lg bg-[#0F0D0B] border border-[#2E241C] flex items-center justify-center text-[#A89880] hover:text-[#F5EFE6] hover:border-[#4A3728] transition-colors"
                                             >
                                                 +
                                             </button>
                                         </div>
                                     </div>
                                 </div>
                             </div>
 
                             <div class="space-y-4 pt-6 border-t border-[#2E241C]">
                                 <div class="flex justify-between text-[14px]">
                                     <span class="text-[#A89880]">Subtotal</span>
                                     <span class="font-mono text-[#F5EFE6]">{{ formatCurrency(subtotal) }}</span>
                                 </div>
                                 <div class="flex justify-between text-[14px]">
                                     <span class="text-[#A89880]">Biaya Pengiriman</span>
                                     <span class="font-mono text-[#F5EFE6]">{{ formatCurrency(shippingCost) }}</span>
                                 </div>
                                 <div class="flex justify-between text-[14px]">
                                     <span class="text-[#A89880]">Biaya Platform</span>
                                     <span class="font-mono text-[#F5EFE6]">{{ formatCurrency(platformFee) }}</span>
                                 </div>
                                 <div class="flex justify-between items-end pt-4">
                                     <span class="font-serif font-bold text-[16px]">Total Pembayaran</span>
                                     <span class="font-mono font-bold text-[20px] text-[#D4AF5A]">{{ formatCurrency(totalPayment) }}</span>
                                 </div>
                             </div>

                             <div v-if="apiMessage" class="mt-4 px-4 py-3 rounded-lg text-[13px] font-bold"
                                 :class="apiSuccess ? 'bg-[#d1fae5] text-[#065f46] border border-[#6ee7b7]' : 'bg-[#fee2e2] text-[#991b1b] border border-[#fca5a5]'">
                                 {{ apiMessage }}
                             </div>

                             <button @click="submitCheckout" :disabled="isLoading || !selectedPort || !paymentMethod" class="w-full mt-10 py-4 bg-[#B8902A] text-white rounded-2xl font-bold text-[16px] shadow-xl shadow-[#B8902A]/20 hover:scale-[1.02] transition-transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed">
                                 {{ isLoading ? 'Memproses...' : 'Bayar Sekarang' }}
                             </button>

                             <p class="mt-6 text-[11px] text-[#5C4F42] text-center leading-relaxed italic">
                                 *Dengan melanjutkan, Anda menyetujui syarat & ketentuan pengadaan BIJI.
                             </p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Sidebar from '../../components/buyer/Sidebar.vue';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const currentRouteName = computed(() => route?.name ?? 'buyer.checkout');
const logoUrl = '/assets/logo-fix.png';

const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const batchName = ref('Memuat detail...');
const pricePerKg = ref(0);
const platformFee = 15000;
const weight = ref(100);

const ports = ref([]);
const selectedPort = ref(null);
const paymentMethod = ref('bank_transfer');

const shippingCost = computed(() => {
    if (!selectedPort.value) return 0;
    const rate = Number(selectedPort.value.shipping_rate_per_kg || selectedPort.value.shipping_cost_per_kg || 2500);
    return weight.value * rate;
});

const subtotal = computed(() => weight.value * pricePerKg.value);
const totalPayment = computed(() => subtotal.value + shippingCost.value + platformFee);

const incrementWeight = () => {
    weight.value += 10;
};

const decrementWeight = () => {
    if (weight.value > 10) {
        weight.value -= 10;
    }
};

watch(weight, (newVal) => {
    if (typeof newVal !== 'number' || isNaN(newVal)) {
        weight.value = 10;
    } else if (newVal < 10) {
        weight.value = 10;
    }
});

const formatCurrency = (val) => {
    return 'Rp ' + (val || 0).toLocaleString('id-ID');
};

const fetchInitialDetails = async () => {
    const listingId = route.query.batch_listing_id;
    if (!listingId) {
        apiMessage.value = 'Silakan pilih produk dari katalog terlebih dahulu.';
        apiSuccess.value = false;
        return;
    }
    
    try {
        const { data } = await axios.get(`/api/v1/buyer/catalog/${listingId}`);
        if (data.success && data.data) {
            const listing = data.data.listing;
            batchName.value = listing.name;
            pricePerKg.value = Number(listing.price_per_kg || 0);
            weight.value = Math.max(10, Math.min(listing.stock_kg || 100, 100));
        }
    } catch (err) {
        console.error('Failed to load product details for checkout:', err);
        apiMessage.value = 'Gagal memuat detail produk.';
        apiSuccess.value = false;
    }

    try {
        const portsRes = await axios.get('/api/v1/buyer/ports');
        if (portsRes.data?.success && portsRes.data?.data) {
            ports.value = portsRes.data.data;
            if (ports.value.length > 0) {
                selectedPort.value = ports.value[0];
            }
        }
    } catch (err) {
        console.error('Failed to load destination ports:', err);
    }
};

const submitCheckout = async () => {
    const listingId = route.query.batch_listing_id;
    if (!listingId || !selectedPort.value || !paymentMethod.value) return;

    isLoading.value = true;
    apiMessage.value = '';

    try {
        const response = await axios.post('/api/v1/buyer/checkout', {
            batch_listing_id: Number(listingId),
            weight_kg: Number(weight.value),
            port_id: Number(selectedPort.value.id),
            payment_method: paymentMethod.value
        });
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Pemesanan berhasil dibuat! Mengalihkan ke riwayat pesanan...';
            setTimeout(() => {
                router.push({ name: 'buyer.orders' });
            }, 2000);
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal membuat pesanan.';
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchInitialDetails();
});
</script>

<style scoped>
/* Chrome, Safari, Edge, Opera */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox */
input[type=number] {
  -moz-appearance: textfield;
}
</style>
