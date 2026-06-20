<template>
    <!-- Mobile Top Bar -->
    <div class="lg:hidden fixed top-0 inset-x-0 h-16 bg-[#1C1813] border-b border-[#2E241C] flex items-center justify-between px-5 z-40">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full border border-[#2D6A4F] flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #2D6A4F, #1A3D2E);">
                <span class="font-sans text-[14px] uppercase">{{ userName ? userName.charAt(0) : 'F' }}</span>
            </div>
            <p class="font-sans text-[16px] font-bold truncate max-w-[150px]">{{ userName || 'BIJI' }} <span class="font-normal text-[#A89880] text-[12px] ml-1">Farmer</span></p>
        </div>
        <button @click="uiStore.toggleMobileMenu()" class="text-[#F5EFE6] focus:outline-none p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>

    <!-- Mobile Backdrop -->
    <transition name="fade">
        <div v-if="uiStore.isMobileMenuOpen" @click="uiStore.closeMobileMenu()" class="fixed inset-0 bg-[#0F0D0B]/80 z-40 lg:hidden backdrop-blur-sm"></div>
    </transition>

    <!-- Desktop Toggle Button -->
    <button 
        @click="isDesktopMenuOpen = !isDesktopMenuOpen" 
        :class="[
            'hidden lg:flex fixed top-1/2 -translate-y-1/2 z-[60] w-8 h-12 bg-[#1C1813] border border-[#2E241C] border-l-0 rounded-r-xl items-center justify-center text-[#A89880] hover:text-[#F5EFE6] transition-all duration-300 shadow-lg cursor-pointer',
            isDesktopMenuOpen ? 'left-[256px]' : 'left-0'
        ]"
    >
        <svg v-if="isDesktopMenuOpen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
    </button>

    <!-- Sidebar / Bottom Sheet -->
    <aside :class="[
        'fixed lg:sticky lg:self-start z-50 flex flex-col gap-6 lg:gap-8 bg-[#1C1813] transition-all duration-300 ease-in-out',
        'lg:top-4 lg:bottom-auto lg:h-[calc(100vh-2rem)] lg:rounded-2xl lg:border lg:border-[#2E241C] lg:w-[240px] lg:px-5 lg:py-8 lg:shrink-0',
        isDesktopMenuOpen ? 'lg:ml-4 lg:opacity-100' : 'lg:-ml-[260px] lg:opacity-0',
        uiStore.isMobileMenuOpen ? 'translate-y-0' : 'translate-y-full lg:translate-y-0',
        'bottom-0 inset-x-0 rounded-t-3xl px-6 py-8 border-t border-[#2E241C] max-h-[85vh] lg:max-h-none overflow-y-auto'
    ]">
        <!-- Drag Handle for Mobile -->
        <div class="w-12 h-1.5 bg-[#2E241C] rounded-full mx-auto mb-2 lg:hidden"></div>

        <div class="flex items-center justify-between hidden lg:flex">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full border border-[#2D6A4F] flex items-center justify-center" style="background: linear-gradient(135deg, #2D6A4F, #1A3D2E);">
                    <span class="font-sans text-[18px] uppercase">{{ userName.charAt(0) }}</span>
                </div>
                <div>
                    <p class="font-sans text-[18px] truncate max-w-[120px]" :title="userName">{{ userName }}</p>
                    <p class="text-[11px] text-[#A89880]">Farmer</p>
                </div>
            </div>

            <button @click="$router.push({ name: 'farmer.pengaturan' })" class="relative w-8 h-8 rounded-lg flex items-center justify-center text-[#A89880] hover:text-[#F5EFE6] hover:bg-[#2A2118] transition focus:outline-none -mr-1" title="Pengaturan">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </button>
        </div>

        <nav class="flex flex-col gap-2 text-[15px]">
            <router-link :to="{ name: 'farmer.dashboard' }" @click="uiStore.closeMobileMenu()" :class="linkClass(currentRouteName === 'farmer.dashboard')" class="px-3 py-2.5 lg:py-2 rounded-md transition">Dashboard</router-link>

            <details class="group" :open="isBatchOpen" @toggle="onDetailsToggle">
                <summary :class="summaryClass(isBatchRouteActive)" class="px-3 py-2.5 lg:py-2 rounded-md transition cursor-pointer flex items-center justify-between list-none">
                    <span>Batch</span>
                    <span class="text-[#5C4F42] group-open:rotate-180 transition">^</span>
                </summary>
                <div class="mt-2 ml-3 flex flex-col gap-2 text-[13px]">
                    <router-link :to="{ name: 'farmer.new-batch' }" @click="uiStore.closeMobileMenu()" :class="linkClass(currentRouteName === 'farmer.new-batch')" class="px-3 py-2.5 lg:py-2 rounded-md transition">Tambah Batch Baru</router-link>
                    <router-link :to="{ name: 'farmer.batch-detail' }" @click="uiStore.closeMobileMenu()" :class="linkClass(currentRouteName === 'farmer.batch-detail')" class="px-3 py-2.5 lg:py-2 rounded-md transition">Batch Saya</router-link>
                </div>
            </details>
            
            <div class="h-px bg-[#2E241C] my-2 lg:hidden"></div>
            
            <button @click="confirmLogout" class="lg:mt-4 px-3 py-2.5 lg:py-2 rounded-md border border-transparent text-[#D94F4F] hover:bg-[#D94F4F]/10 transition text-left flex items-center gap-2">
               Keluar
            </button>
        </nav>
    </aside>

    <!-- Logout Confirmation Modal -->
    <transition name="fade">
        <div v-if="showLogoutModal" class="fixed inset-0 z-[100] flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-[#0F0D0B]/80 backdrop-blur-sm" @click="showLogoutModal = false"></div>
            <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6 w-full max-w-sm relative z-10 shadow-2xl">
                <h3 class="text-xl font-sans font-bold text-[#F5EFE6] mb-2">Konfirmasi Keluar</h3>
                <p class="text-[14px] text-[#A89880] mb-6">Apakah Anda yakin ingin keluar dari akun Anda?</p>
                
                <div class="flex gap-3 justify-end">
                    <button @click="showLogoutModal = false" class="px-4 py-2 rounded-xl text-[#A89880] hover:text-[#F5EFE6] hover:bg-[#2A2118] transition text-[14px] font-medium">
                        Batal
                    </button>
                    <button @click="executeLogout" class="px-4 py-2 rounded-xl bg-[#D94F4F] text-[#F5EFE6] hover:bg-[#B33F3F] transition text-[14px] font-medium shadow-lg shadow-[#D94F4F]/20">
                        Keluar
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUiStore } from '../../stores/ui';
import { useUserProfileStore } from '../../stores/userProfile';
import axios from 'axios';

const props = defineProps({
    currentRouteName: {
        type: String,
        default: ''
    }
});

const router = useRouter();
const route = useRoute();
const uiStore = useUiStore();
const userProfileStore = useUserProfileStore();
const userName = computed(() => userProfileStore.profile.fullName || 'User');
const routeName = computed(() => props.currentRouteName || '');
const isBatchRouteActive = computed(() =>
    routeName.value === 'farmer.new-batch' ||
    routeName.value === 'farmer.batch-detail'
);

const isBatchOpen = ref(isBatchRouteActive.value);
const isDesktopMenuOpen = ref(localStorage.getItem('desktopMenuOpen') !== 'false');

watch(isDesktopMenuOpen, (val) => {
    localStorage.setItem('desktopMenuOpen', val);
});

watch(isBatchRouteActive, (isActive) => {
    if (isActive) {
        isBatchOpen.value = true;
    }
});

// Close mobile menu when route changes
watch(() => route.name, () => {
    if (window.innerWidth < 1024) {
        uiStore.closeMobileMenu();
    }
});

function onDetailsToggle(event) {
    isBatchOpen.value = event.target.open;
}

const linkClass = (isActive) =>
    isActive
        ? 'bg-[#2A2118] text-[#F5EFE6] border border-[#4A3728] font-semibold'
        : 'border border-transparent text-[#A89880] hover:text-[#F5EFE6] hover:bg-[#2A2118]';

const summaryClass = (isActive) =>
    isActive
        ? 'text-[#F5EFE6] bg-[#2A2118] border border-[#4A3728] font-semibold'
        : 'text-[#A89880] hover:text-[#F5EFE6] hover:bg-[#2A2118] border border-transparent';

const showLogoutModal = ref(false);

const confirmLogout = () => {
    showLogoutModal.value = true;
};

const executeLogout = async () => {
    try {
        await axios.post('/api/v1/auth/logout');
    } catch (err) {
        console.error('Logout error:', err);
    } finally {
        localStorage.clear();
        uiStore.closeMobileMenu();
        showLogoutModal.value = false;
        router.push({ name: 'auth.login-lagi' });
    }
};

// Handle window resize to clean up mobile state
const handleResize = () => {
    if (window.innerWidth >= 1024 && uiStore.isMobileMenuOpen) {
        uiStore.closeMobileMenu();
    }
};

onMounted(() => {
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
