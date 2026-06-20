<template>
    <div class="min-h-screen lg:h-screen w-full bg-[#0F0D0B] text-[#F5EFE6] theme-exporter font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative flex flex-col lg:flex-row h-full w-full">
            <div class="absolute inset-0 pointer-events-none opacity-60" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(75, 56, 50, 0.35), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.12), transparent 60%);"></div>

            <Sidebar :current-route-name="currentRouteName" />

            <main class="relative z-10 flex-1 overflow-y-auto px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10">
                <header class="mb-10 flex items-start justify-between gap-6">
                    <div> 
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Tambah Batch Baru</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">
                            Daftarkan batch kopi baru yang siap untuk proses ekspor. Isi detail varietas, origin, dan data blockchain untuk transparansi rantai pasok.
                        </p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="Logo BIJI"
                            class="pointer-events-none w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mt-10 lg:-mr-10"
                        >
                    </div>
                </header>

                <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-12 items-start">
                    <div class="xl:col-span-8 space-y-6">
                        <div class="rounded-2xl border border-[#2E241C] bg-[#1C1813] p-8">
                            <div class="space-y-8">
                                <!-- Section 1: Core Data -->
                                <div>
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-[11px] uppercase tracking-[0.18em] text-[#A89880]">Tahap 1</p>
                                            <h3 class="mt-1 text-[18px] font-semibold text-[#F5EFE6]">Data Batch & Origin</h3>
                                        </div>
                                        <span class="text-[12px] text-[#A89880] font-mono">{{ batchCodePreview }}</span>
                                    </div>

                                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                                        <div v-for="field in coreFields" :key="field.key">
                                            <label class="text-[13px] text-[#A89880]" :for="field.key">{{ field.label }}</label>
                                            <component
                                                :is="field.as"
                                                :id="field.key"
                                                v-model="form[field.key]"
                                                :type="field.type || 'text'"
                                                :placeholder="field.placeholder"
                                                class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] transition"
                                            >
                                                <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
                                            </component>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: Technical Specs -->
                                <div class="pt-8 border-t border-[#2E241C]">
                                    <div>
                                        <p class="text-[11px] uppercase tracking-[0.18em] text-[#A89880]">Tahap 2</p>
                                        <h3 class="mt-1 text-[18px] font-semibold text-[#F5EFE6]">Spesifikasi Teknis</h3>
                                    </div>

                                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                                        <div v-for="field in technicalFields" :key="field.key" :class="field.full ? 'md:col-span-2' : ''">
                                            <label class="text-[13px] text-[#A89880]" :for="field.key">{{ field.label }}</label>
                                            <component
                                                :is="field.as"
                                                :id="field.key"
                                                v-model="form[field.key]"
                                                :type="field.type || 'text'"
                                                :rows="field.rows"
                                                :placeholder="field.placeholder"
                                                class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] transition"
                                            >
                                                <option v-for="option in field.options || []" :key="option" :value="option">{{ option }}</option>
                                            </component>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Preview -->
                    <div class="xl:col-span-4 xl:sticky xl:top-6">
                        <div class="rounded-2xl border border-[#2E241C] bg-[#1C1813] p-8 shadow-xl">
                            <div class="flex items-center justify-between gap-4 mb-6">
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-[#A89880]">Pratinjau Batch</p>
                                    <h3 class="mt-1 text-[20px] font-bold text-[#F5EFE6]">{{ batchCodePreview }}</h3>
                                </div>
                                <span class="rounded-full border border-[#4B3832] bg-[#2C1F1A] px-3 py-1 text-[11px] font-bold text-[#8B6355] uppercase tracking-wider">Draft</span>
                            </div>

                            <div class="space-y-4 rounded-2xl border border-[#2E241C] bg-[#0F0D0B] p-5">
                                <div class="flex justify-between items-center text-[13px]">
                                    <span class="text-[#A89880]">Varietas</span>
                                    <span class="text-[#F5EFE6] font-medium">{{ form.varietas || '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center text-[13px]">
                                    <span class="text-[#A89880]">Origin</span>
                                    <span class="text-[#F5EFE6] font-medium">{{ form.origin || '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center text-[13px]">
                                    <span class="text-[#A89880]">Berat</span>
                                    <span class="text-[#F5EFE6] font-medium">{{ form.berat ? form.berat + ' Kg' : '-' }}</span>
                                </div>
                                <div class="flex justify-between items-center text-[13px]">
                                    <span class="text-[#A89880]">Proses</span>
                                    <span class="text-[#F5EFE6] font-medium">{{ form.proses || '-' }}</span>
                                </div>
                            </div>

                            <div class="mt-8 space-y-3">
                                <button class="w-full py-4 bg-[#4B3832] border border-[#8B6355] rounded-xl text-[14px] font-bold text-[#F5EFE6] hover:bg-[#5D463E] transition shadow-lg shadow-[#4B3832]/20">
                                    Simpan ke Blockchain
                                </button>
                                <button class="w-full py-3 bg-transparent border border-[#4A3728] rounded-xl text-[13px] font-medium text-[#A89880] hover:text-[#F5EFE6] hover:bg-[#2A2118] transition">
                                    Simpan Draft Lokal
                                </button>
                            </div>

                            <p class="mt-6 text-[11px] text-[#5C4F42] leading-relaxed text-center italic">
                                *Menyimpan ke blockchain membutuhkan biaya gas (Polygon Amoy Testnet).
                            </p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '../../components/exporter/Sidebar.vue';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'exporter.new-batch');
const logoUrl = '/assets/logo-fix.png';

const form = reactive({
    varietas: '',
    origin: '',
    berat: '',
    panenBulan: 'Mei',
    panenTahun: '2026',
    proses: 'Full Wash',
    kadarAir: '12%',
    density: '',
    grade: 'Grade 1',
    catatan: ''
});

const coreFields = [
    { key: 'varietas', label: 'Varietas Kopi', placeholder: 'Contoh: Arabika Toraja Sapan', as: 'input' },
    { key: 'origin', label: 'Origin / Daerah Asal', placeholder: 'Contoh: Tana Toraja, Sulsel', as: 'input' },
    { key: 'berat', label: 'Total Berat (Kg)', placeholder: 'Misal: 1000', as: 'input', type: 'number' },
    {
        key: 'grade',
        label: 'Kualitas / Grade',
        as: 'select',
        options: ['Grade 1', 'Grade 2', 'Specialty', 'Commercial']
    }
];

const technicalFields = [
    {
        key: 'proses',
        label: 'Metode Pengolahan',
        as: 'select',
        options: ['Full Wash', 'Semi Wash', 'Natural', 'Honey', 'Anaerob']
    },
    {
        key: 'kadarAir',
        label: 'Kadar Air (%)',
        as: 'select',
        options: ['11%', '12%', '13%', '14%']
    },
    { key: 'density', label: 'Density (g/ml)', placeholder: 'Misal: 0.72', as: 'input', type: 'number' },
    { key: 'catatan', label: 'Catatan Eksportir', placeholder: 'Detail tambahan mengenai batch ini...', as: 'textarea', rows: 4, full: true }
];

const batchCodePreview = computed(() => {
    const varietyCode = (form.varietas || 'EXP').replace(/[^A-Za-z0-9]/g, '').slice(0, 3).toUpperCase();
    const originCode = (form.origin || 'ID').replace(/[^A-Za-z0-9]/g, '').slice(0, 3).toUpperCase();

    return `BJI-${originCode}-${varietyCode}-2026`;
});
</script>
