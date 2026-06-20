<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

// Carousel Images (Menggunakan Unsplash premium stock coffee photography)
const heroImages = [
    'https://images.unsplash.com/photo-1497935586351-b67a49e012bf?q=80&w=2000&auto=format&fit=crop', 
    'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=2000&auto=format&fit=crop', 
    'https://images.unsplash.com/photo-1511920170033-f8396924c348?q=80&w=2000&auto=format&fit=crop'  
];
const currentHeroImage = ref(0);

// Shared logo asset
const logoUrl = '/assets/logo-fix.png';
let heroInterval = null;

// Interactive Lifecycle untuk Tab Kerja Blockchain
const activeStep = ref(1);

// Live Automated Blockchain Ticker State
const liveTicker = ref([
    { id: 162001, text: 'Batch #TRJ-304 (Arabika Toraja Sapan) • Berhasil Terkunci di Blok Jaringan #4,829,102', time: 'Baru saja' },
    { id: 162002, text: 'Batch #EKS-092 (Kalosi Enrekang) • Dokumen Sertifikat Asal Di-hash & Di-minting', time: '2m lalu' },
    { id: 162003, text: 'Batch #MMS-115 (Arabika Mamasa) • Status Kepemilikan Dialihkan ke Eksportir Makassar', time: '5m lalu' }
]);

const timelineSteps = [
    {
        id: 1,
        title: 'Petani Kopi (Farmer)',
        subtitle: 'Registrasi Batch Panen Awal',
        icon: '🌾',
        accentColor: '#4CAF7D',
        description: 'Petani memanen ceri kopi pilihan pada elevasi tinggi di Tana Toraja atau Enrekang. Batch didaftarkan ke platform dengan koordinat GPS lokasi kebun dan elevasi lahan secara presisi.',
        metadata: [
            { label: 'Ketinggian Lahan', value: '1.650 mdpl' },
            { label: 'Varietas Utama', value: 'Arabika Lini S-795' },
            { label: 'Proses Utama', value: 'Natural Extended' },
            { label: 'Status Ledger', value: 'Genesis Created' }
        ],
        txHash: '0x3a9f78bc4e8d251109ea2b542c8d2345e4d2719a'
    },
    {
        id: 2,
        title: 'Eksportir (Exporter Hub)',
        subtitle: 'Uji Mutu & Blockchain Certification',
        icon: '🏢',
        accentColor: '#8B6355',
        description: 'Eksportir mengklaim kepemilikan batch melalui sistem, mengunggah sertifikat formal, menghitung SHA-256 doc_hash, serta menyauhkannya langsung ke Smart Contract Polygon Amoy Testnet.',
        metadata: [
            { label: 'Cupping Score', value: '85.50 (Specialty)' },
            { label: 'Batas Cacat Fisik', value: 'Grade 1 Quality' },
            { label: 'Nomor Sertifikat', value: 'IG-SLW-2026-004' },
            { label: 'Status Ledger', value: 'Published & Watermarked' }
        ],
        txHash: '0x7c8b21ef45a2789fa1202bc4539ef2a089fae019'
    },
    {
        id: 3,
        title: 'Pembeli Global (End xBuyer)',
        subtitle: 'Verifikasi Keaslian Otentik',
        icon: '🚢',
        accentColor: '#E8A838',
        description: 'Pembeli memindai QR Code kemasan produk fisik untuk mencocokkan manifest pengapalan dan melakukan tracing data on-chain guna memastikan integritas dari hulu hingga hilir.',
        metadata: [
            { label: 'Pelabuhan Tujuan', value: 'Yokohama Port' },
            { label: 'Kondisi Logistik', value: 'Reefer Container Secured' },
            { label: 'Verifikasi Kripto', value: '100% Match Function' },
            { label: 'Status Order', value: 'Completed / Handover' }
        ],
        txHash: '0xf2e459a112bc7809ea2b542c8d2345e4d2719a2b'
    }
];

let tickerInterval = null;
const mockOrigins = ['Toraja Sapan', 'Enrekang Kalosi', 'Mamasa Arabika', 'Luwu Excelsa'];
const mockEvents = [
    'Sukses diakuisisi oleh eksportir terdaftar.',
    'Nilai hash log harian divalidasi sistem.',
    'Sertifikat asal diterbitkan ke jaringan terdesentralisasi.',
    'Konfirmasi pembayaran selesai, hak milik digital beralih.'
];

onMounted(() => {
    heroInterval = setInterval(() => {
        currentHeroImage.value = (currentHeroImage.value + 1) % heroImages.length;
    }, 6000);

    tickerInterval = setInterval(() => {
        const randomOrigin = mockOrigins[Math.floor(Math.random() * mockOrigins.length)];
        const randomEvent = mockEvents[Math.floor(Math.random() * mockEvents.length)];
        const randomId = Math.floor(Math.random() * 800) + 100;
        const newFeed = {
            id: Date.now(),
            text: `Batch #SLW-${randomId} (${randomOrigin}) — ${randomEvent}`,
            time: 'Baru saja'
        };
        liveTicker.value = [newFeed, ...liveTicker.value.slice(0, 2)];
    }, 7000);
});

onUnmounted(() => {
    if (heroInterval) clearInterval(heroInterval);
    if (tickerInterval) clearInterval(tickerInterval);
});
</script>

<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] font-sans antialiased selection:bg-[#08C246] selection:text-[#0F0D0B]">
        
        <!-- Header Navigasi (Pure Code, No Image Dependencies) -->
        <header class="fixed top-0 inset-x-0 z-50 border-b border-[#2E241C]/50 bg-[#0F0D0B]/80 backdrop-blur-md">
            <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <img :src="logoUrl" alt="BIJI" class="w-20 h-20 sm:w-28 sm:h-28 object-contain rounded" />
                </div>
                
                <div class="flex items-center gap-6">
                    <router-link :to="{ name: 'auth.login-lagi' }" class="text-xs font-mono uppercase tracking-wider text-[#A89880] hover:text-[#F5EFE6] transition-colors">Masuk</router-link>
                    <router-link :to="{ name: 'auth.register-lagi' }" class="h-10 px-4 flex items-center text-xs font-bold font-mono rounded bg-[#1C1813] border border-[#4A3728] text-[#F5EFE6] hover:bg-[#2A2118] transition-all">Daftar</router-link>
                </div>
            </div>
        </header>

        <!-- HERO BANNER CAROUSEL SYSTEM -->
        <section class="relative h-[100vh] w-full flex flex-col items-center justify-center overflow-hidden">
            <!-- Background Images Layer -->
            <div class="absolute inset-0 z-0">
                <transition-group name="carousel-fade">
                    <div 
                        v-for="(image, index) in heroImages" 
                        :key="image"
                        v-show="currentHeroImage === index"
                        class="absolute inset-0 bg-cover bg-center bg-no-repeat"
                        :style="`background-image: url('${image}');`"
                    ></div>
                </transition-group>
            </div>

            <!-- Controlled Dark Dimmer Overlay Layer -->
            <div class="absolute inset-0 bg-gradient-to-b from-[#0F0D0B]/90 via-[#0F0D0B]/60 to-[#0F0D0B] z-10"></div>
            
            <!-- Content Wrapper (Stacked Perfectly Above the Carousel Banner) -->
            <div class="relative z-20 max-w-4xl mx-auto px-6 flex flex-col items-center text-center space-y-8">
                
                <h1 class="text-4xl md:text-6xl lg:text-7xl font-bold font-serif leading-[1.12] text-[#F5EFE6]">
                    Melacak Keaslian,<br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#E8A838] via-[#F5EFE6] to-[#A89880]">
                        Menjamin Integritas
                    </span><br />
                    Kopi Sulawesi
                </h1>
                
                <p class="text-xs md:text-base text-[#A89880] leading-relaxed max-w-2xl font-sans">
                    Ekosistem rantai pasok tertutup terautomasi yang mengunci kekayaan agroklimat kebun kopi Sulawesi secara real-time dan transparan di dalam jaringan blockchain publik.
                </p>
                
                <!-- THE SPECIFIC SPECIFIED CENTER CTA BUTTON -->
                <div class="relative group pt-4 w-full sm:w-auto px-4 sm:px-0">
                    <div class="absolute -inset-1 rounded-lg bg-[#08C246] opacity-20 blur-xl group-hover:opacity-45 transition duration-500"></div>
                    <router-link 
                        :to="{ name: 'auth.register-lagi' }" 
                        class="relative flex sm:inline-flex justify-center px-8 py-4 sm:px-14 sm:py-5 rounded bg-[#08C246] text-[#0F0D0B] font-bold text-sm sm:text-base uppercase tracking-widest transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] shadow-2xl w-full sm:w-auto"
                    >
                        Gabung Sekarang
                    </router-link>
                </div>
            </div>

            <!-- Carousel Slide Dots -->
            <div class="absolute bottom-10 z-20 flex gap-2.5">
                <button 
                    v-for="(_, index) in heroImages" 
                    :key="index"
                    @click="currentHeroImage = index"
                    class="h-1 rounded-full transition-all duration-300"
                    :class="currentHeroImage === index ? 'w-8 bg-[#08C246]' : 'w-2 bg-[#4A3728]'"
                ></button>
            </div>
        </section>

        <!-- LIVE LEDGER TICKER & CARD MONITOR -->
        <section class="py-16 md:py-24 max-w-7xl mx-auto px-4 md:px-6 grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-12 items-center">
            <div class="lg:col-span-5 space-y-6">
                <div class="space-y-2">
                    <h2 class="text-3xl md:text-4xl font-bold font-serif text-[#F5EFE6]">
                        Aktivitas Rantai Pasok Terautomasi
                    </h2>
                </div>
                <p class="text-xs md:text-sm text-[#A89880] leading-relaxed">
                    Setiap log suhu pascapanen, koordinat elevasi, hingga data sertifikat mutu SCA diikat menggunakan hash kriptografi unik untuk proteksi total dari manipulasi eksternal.
                </p>
                
                <!-- JS Driven Ticker Panel -->
                <div class="bg-[#1C1813] border border-[#2E241C] rounded p-5 relative overflow-hidden shadow-xl">
                    <div class="flex items-center justify-between mb-4 border-b border-[#2E241C] pb-3">
                        <span class="text-[9px] font-mono tracking-widest text-[#A89880] uppercase font-bold">Ledger Live Monitor</span>
                        <span class="text-[9px] font-mono text-[#08C246] bg-[#08C246]/10 px-2 py-0.5 rounded flex items-center gap-1.5">
                            <span class="w-1 h-1 rounded-full bg-[#08C246] animate-ping"></span> Live Syncing
                        </span>
                    </div>
                    <div class="space-y-2 h-[140px] overflow-hidden relative">
                        <transition-group name="ticker-list">
                            <div v-for="ticker in liveTicker" :key="ticker.id" class="text-[11px] font-mono p-3 rounded bg-[#0F0D0B]/60 border border-[#2E241C] flex flex-col gap-1">
                                <div class="flex justify-between text-[9px] text-[#5C4F42]">
                                    <span>REF-ID #{{ ticker.id }}</span>
                                    <span>{{ ticker.time }}</span>
                                </div>
                                <p class="text-[#F5EFE6]/90 truncate"><span class="text-[#08C246] font-bold">></span> {{ ticker.text }}</p>
                            </div>
                        </transition-group>
                    </div>
                </div>
            </div>

            <!-- Sample Certificate Wireframe (Strictly Digital, No External Assets) -->
            <div class="lg:col-span-7 bg-[#1C1813] border border-[#2E241C] rounded overflow-hidden shadow-2xl relative">
                <div class="absolute top-0 left-0 w-full h-[3px] bg-gradient-to-r from-[#4CAF7D] via-[#E8A838] to-[#8B6355]"></div>
                <div class="p-6 md:p-8 space-y-6">
                    <div class="flex justify-between items-start">
                        <div class="space-y-1">
                            <h3 class="text-2xl font-serif font-bold text-[#F5EFE6]">Toraja Kalosi Specialty</h3>
                        </div>   
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono">
                        <div class="bg-[#0F0D0B] p-4 rounded border border-[#2E241C]">
                            <span class="text-[#5C4F42] block text-[10px] uppercase mb-1">Origin Lahan</span>
                            <span class="text-[#F5EFE6] font-bold">Bittuang, Tana Toraja</span>
                        </div>
                        <div class="bg-[#0F0D0B] p-4 rounded border border-[#2E241C]">
                            <span class="text-[#5C4F42] block text-[10px] uppercase mb-1">Varietas</span>
                            <span class="text-[#F5EFE6] font-bold">Arabika Lini S-795</span>
                        </div>
                        <div class="bg-[#0F0D0B] p-4 rounded border border-[#2E241C]">
                            <span class="text-[#5C4F42] block text-[10px] uppercase mb-1">KELEMBAPAN MAKSIMAL</span>
                            <span class="text-[#E8A838] font-bold">11.8% (Lulus Verifikasi)</span>
                        </div>
                        <div class="bg-[#0F0D0B] p-4 rounded border border-[#2E241C]">
                            <span class="text-[#5C4F42] block text-[10px] uppercase mb-1">Status Akuisisi</span>
                            <span class="text-[#4CAF7D] font-bold">Unique Certificate Minted</span>
                        </div>
                    </div>

                    <div class="bg-[#0F0D0B] p-4 rounded border border-[#2E241C] space-y-1.5 font-mono text-xs">
                        <span class="block text-[9px] uppercase tracking-widest text-[#5C4F42]">Document Ledger SHA-256 Data Root</span>
                        <p class="break-all text-[#A89880] bg-[#1C1813] p-2.5 rounded border border-[#2E241C] text-[11px] leading-relaxed select-all">
                            e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- INTERACTIVE WORKFLOW STEP TIMELINE (JS DATA CARD FLIP) -->
        <section class="py-24 bg-[#14120F] border-y border-[#2E241C]">
            <div class="max-w-4xl mx-auto px-6">
                <div class="text-center mb-16 space-y-2">
                    <h2 class="text-3xl font-serif font-bold text-[#F5EFE6]">Arsitektur Kerja Tiga Aktor Utama</h2>
                </div>

                <!-- Interactive JS Switcher Navigation Tab Row -->
                <div class="flex flex-row justify-between items-start sm:items-center relative mb-12 gap-2 sm:gap-0 w-full overflow-x-auto sm:overflow-visible pb-4 sm:pb-0 scrollbar-hide">
                    <div class="absolute left-0 right-0 top-[28px] h-[1px] bg-[#2E241C] z-0 hidden sm:block"></div>
                    
                    <button 
                        v-for="step in timelineSteps" 
                        :key="step.id"
                        @click="activeStep = step.id"
                        class="relative z-10 flex flex-col items-center focus:outline-none w-full sm:w-auto"
                    >
                        <div 
                            class="w-14 h-14 rounded-full flex items-center justify-center text-base border-2 transition-all duration-300 bg-[#0F0D0B]"
                            :style="{
                                borderColor: activeStep === step.id ? step.accentColor : '#2E241C',
                                boxShadow: activeStep === step.id ? `0 0 20px ${step.accentColor}30` : 'none'
                            }"
                        >
                            <span>{{ step.icon }}</span>
                        </div>
                        <span class="text-[10px] font-mono mt-3 uppercase tracking-wider" :class="activeStep === step.id ? 'text-[#F5EFE6] font-bold' : 'text-[#5C4F42]'">Langkah 0{{ step.id }}</span>
                    </button>
                </div>

                <!-- Active Step Rendering Area -->
                <transition name="step-fade" mode="out-in">
                    <div 
                        :key="activeStep" 
                        class="bg-[#1C1813] border border-[#2E241C] rounded p-6 md:p-8 space-y-6 shadow-2xl"
                        :style="`border-top: 3px solid ${timelineSteps[activeStep - 1].accentColor};`"
                    >
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                            <h3 class="text-lg font-serif font-bold text-[#F5EFE6]">{{ timelineSteps[activeStep - 1].subtitle }}</h3>
                            <span class="text-[11px] font-mono font-bold uppercase tracking-wider px-2.5 py-0.5 rounded bg-[#0F0D0B] border border-[#2E241C]" :style="`color: ${timelineSteps[activeStep - 1].accentColor};`">
                                {{ timelineSteps[activeStep - 1].title }}
                            </span>
                        </div>

                        <p class="text-xs md:text-sm text-[#A89880] leading-relaxed">
                            {{ timelineSteps[activeStep - 1].description }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 pt-5 border-t border-[#2E241C]/60">
                            <div v-for="(meta, i) in timelineSteps[activeStep - 1].metadata" :key="i" class="space-y-1">
                                <span class="text-[9px] font-mono text-[#5C4F42] uppercase tracking-wider block">{{ meta.label }}</span>
                                <span class="text-xs font-mono font-bold text-[#F5EFE6] block">{{ meta.value }}</span>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </section>

        <!-- FOOTER LOGO INLINE SVG -->
        <footer class="py-12 bg-[#0F0D0B] text-center border-t border-[#2E241C] text-[10px] font-mono text-[#5C4F42] tracking-widest space-y-4">
            <div class="flex justify-center items-center gap-2 opacity-50">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
                <span>BIJI BLOCKCHAIN ECOSYSTEM PROTOCOL</span>
            </div>
            <p class="uppercase">&copy; 2026 BIJI NETWORK PROTOCOL DEVELOPMENT. ALL RIGHTS RESERVED.</p>
        </footer>

    </div>
</template>

<style scoped>
/* Buttery Smooth Carousel Banner Image Fade Transitions */
.carousel-fade-enter-active,
.carousel-fade-leave-active {
    transition: opacity 2s ease-in-out;
}
.carousel-fade-enter-from,
.carousel-fade-leave-to {
    opacity: 0;
}

/* Tab Workflow Content Panel Crossfade Animation */
.step-fade-enter-active,
.step-fade-leave-active {
    transition: all 0.35s ease;
}
.step-fade-enter-from {
    opacity: 0;
    transform: translateY(12px);
}
.step-fade-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}

/* Live Automated Blockchain Ticker Item Shift System */
.ticker-list-enter-active,
.ticker-list-leave-active {
    transition: all 0.5s ease;
}
.ticker-list-enter-from {
    opacity: 0;
    transform: translateY(-15px);
}
.ticker-list-leave-to {
    opacity: 0;
    transform: translateY(15px);
}
</style>
