<template>
    <div class="min-h-screen bg-[#0F0D0B] flex items-center justify-center p-6 md:p-8 font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="flex flex-col-reverse md:flex-row w-full max-w-[1200px] min-h-max md:min-h-[620px] rounded-2xl overflow-hidden shadow-[0_24px_80px_rgba(0,0,0,0.5)]">
            <div class="md:w-[44%] bg-[#F5EFE6] p-6 md:p-10 lg:p-16 flex flex-col justify-center">
                <div class="mb-4 md:mb-7 flex justify-center md:justify-start">
                    <img :src="mockData.logoUrl" alt="logo" class="w-[120px] h-[120px] md:w-[180px] md:h-[180px] object-contain md:-ml-10 md:-mt-2">
                </div>

                <h1 class="text-[32px] font-bold font-sans text-[#1C1813] leading-tight mb-3">Buat Akun Baru</h1>

                <p class="text-[15px] font-bold text-[#1C1813] leading-relaxed mb-9 max-w-[380px]">
                    {{ mockData.description }}
                </p>

                <!-- Error / Success Banner -->
                <transition name="fade">
                    <div v-if="apiMessage" class="mb-4 px-4 py-3 rounded-lg text-[13px] font-bold"
                        :class="apiSuccess ? 'bg-[#d1fae5] text-[#065f46] border border-[#6ee7b7]' : 'bg-[#fee2e2] text-[#991b1b] border border-[#fca5a5]'">
                        {{ apiMessage }}
                    </div>
                </transition>

                <form class="flex flex-col gap-5 mb-6" @submit.prevent="handleSubmit">
                    <input type="hidden" name="role" :value="role">

                    <div class="flex flex-col gap-1.5">
                        <label for="name" class="text-[13px] text-[#5C4F42]">Nama Lengkap (Sesuai KTP)</label>
                        <div class="relative flex items-center">
                            <input
                                id="name"
                                v-model="name"
                                name="name"
                                type="text"
                                required
                                autocomplete="name"
                                class="w-full h-12 bg-transparent border-b-[1.5px] border-[#D4C9B4] outline-none text-[15px] text-[#1C1813] transition-colors focus:border-[#1C1813] px-1"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-[13px] text-[#5C4F42]">Alamat Email</label>
                        <div class="relative flex items-center">
                            <input
                                id="email"
                                v-model="email"
                                name="email"
                                type="email"
                                required
                                autocomplete="email"
                                class="w-full h-12 bg-transparent border-b-[1.5px] border-[#D4C9B4] outline-none text-[15px] text-[#1C1813] transition-colors focus:border-[#1C1813] px-1"
                            />
                            <div
                                id="login-email-check"
                                class="absolute right-0 top-0 h-12 flex items-center pr-2 hidden text-[#08C246] pointer-events-none"
                                :style="isEmailValid ? 'display: flex;' : ''"
                            >
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <circle cx="10" cy="10" r="10" fill="#08C246" />
                                    <path d="M5.5 10.5L8.5 13.5L14.5 7" stroke="#EFECE5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="text-[13px] text-[#5C4F42]">Password</label>
                        <div class="relative flex items-center">
                            <input
                                id="password"
                                v-model="password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                class="w-full h-12 bg-transparent border-b-[1.5px] border-[#D4C9B4] outline-none text-[15px] text-[#1C1813] transition-colors focus:border-[#1C1813] px-1"
                            />
                            <button type="button" id="login-toggle-pass" class="absolute right-0 top-0 h-12 flex items-center px-2 focus:outline-none" @click="togglePassword">
                                <svg id="login-eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" :style="showPassword ? 'display: none;' : 'display: inline;'">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="#888" stroke-width="2" />
                                    <circle cx="12" cy="12" r="3" stroke="#888" stroke-width="2" />
                                </svg>
                                <svg id="login-eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" :style="showPassword ? 'display: inline;' : 'display: none;'">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" stroke="#888" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>
                        <transition name="fade">
                            <div v-show="password.length > 0" class="flex items-center gap-3 mt-1.5">
                                <div class="flex gap-1">
                                    <span class="h-1 w-12 rounded-sm transition-colors duration-200" :class="passwordScore >= 1 ? (passwordScore === 1 ? 'bg-[#E53E3E]' : (passwordScore === 2 ? 'bg-[#F6AD55]' : 'bg-[#08C246]')) : 'bg-[#D4C9B4]'" />
                                    <span class="h-1 w-12 rounded-sm transition-colors duration-200" :class="passwordScore >= 2 ? (passwordScore === 2 ? 'bg-[#F6AD55]' : 'bg-[#08C246]') : 'bg-[#D4C9B4]'" />
                                    <span class="h-1 w-12 rounded-sm transition-colors duration-200" :class="passwordScore >= 3 ? 'bg-[#08C246]' : 'bg-[#D4C9B4]'" />
                                </div>
                                <span class="text-[12px] font-bold" :class="passwordScore === 1 ? 'text-[#E53E3E]' : (passwordScore === 2 ? 'text-[#F6AD55]' : 'text-[#08C246]')">{{ strengthLabel }}</span>
                            </div>
                        </transition>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password_confirmation" class="text-[13px] text-[#5C4F42]">Konfirmasi Password</label>
                        <div class="relative flex items-center">
                            <input
                                id="password_confirmation"
                                v-model="confirmPassword"
                                name="password_confirmation"
                                :type="showConfirm ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                class="w-full h-12 bg-transparent border-b-[1.5px] border-[#D4C9B4] outline-none text-[15px] text-[#1C1813] transition-colors focus:border-[#1C1813] px-1"
                            />
                            <button type="button" class="absolute right-0 top-0 h-12 flex items-center px-2 focus:outline-none" @click="toggleConfirm">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" :style="showConfirm ? 'display: none;' : 'display: inline;'"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="#888" stroke-width="2" /><circle cx="12" cy="12" r="3" stroke="#888" stroke-width="2" /></svg>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" :style="showConfirm ? 'display: inline;' : 'display: none;'"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" stroke="#888" stroke-width="2" stroke-linecap="round" /><line x1="1" y1="1" x2="23" y2="23" stroke="#888" stroke-width="2" stroke-linecap="round" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Role Selection — semua aktif -->
                    <div class="flex flex-col gap-2 mt-2">
                        <p class="text-[14px] font-bold text-[#1C1813] m-0">Saya adalah:</p>
                        <div class="flex gap-3">
                            <!-- Petani -->
                            <label class="flex-1 cursor-pointer" @click.prevent="selectRole('farmer')">
                                <input type="radio" name="role" value="farmer" class="peer sr-only">
                                <div
                                    class="w-full h-[52px] flex items-center justify-center border rounded-[8px] font-bold transition-transform transform"
                                    :class="{
                                        'scale-95': clickedRole === 'farmer',
                                        'bg-[#08C246] text-white border-[#08C246]': role === 'farmer',
                                        'bg-transparent text-[#1c1813]/50 border-[#1c1813]/30 hover:bg-[#08C246] hover:text-white hover:border-[#08C246]': role !== 'farmer'
                                    }"
                                    style="font-family: 'Inter', sans-serif; font-size: 14px;"
                                >
                                    Petani
                                </div>
                            </label>

                            <!-- Eksportir -->
                            <label class="flex-1 cursor-pointer" @click.prevent="selectRole('exporter')">
                                <input type="radio" name="role" value="exporter" class="peer sr-only">
                                <div
                                    class="w-full h-[52px] flex items-center justify-center border rounded-[8px] font-bold transition-transform transform"
                                    :class="{
                                        'scale-95': clickedRole === 'exporter',
                                        'bg-[#08C246] text-white border-[#08C246]': role === 'exporter',
                                        'bg-transparent text-[#1c1813]/50 border-[#1c1813]/30 hover:bg-[#08C246] hover:text-white hover:border-[#08C246]': role !== 'exporter'
                                    }"
                                    style="font-family: 'Inter', sans-serif; font-size: 14px;"
                                >
                                    Eksportir
                                </div>
                            </label>

                            <!-- Pembeli -->
                            <label class="flex-1 cursor-pointer" @click.prevent="selectRole('buyer')">
                                <input type="radio" name="role" value="buyer" class="peer sr-only">
                                <div
                                    class="w-full h-[52px] flex items-center justify-center border rounded-[8px] font-bold transition-transform transform"
                                    :class="{
                                        'scale-95': clickedRole === 'buyer',
                                        'bg-[#08C246] text-white border-[#08C246]': role === 'buyer',
                                        'bg-transparent text-[#1c1813]/50 border-[#1c1813]/30 hover:bg-[#08C246] hover:text-white hover:border-[#08C246]': role !== 'buyer'
                                    }"
                                    style="font-family: 'Inter', sans-serif; font-size: 14px;"
                                >
                                    Pembeli
                                </div>
                            </label>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="!canSubmit || isLoading"
                        :class="canSubmit && !isLoading ? 'bg-[#1C1813] hover:bg-[#2A2118] active:scale-[0.99]' : 'bg-[#D4C9B4] cursor-not-allowed'"
                        class="mt-2 w-full h-[52px] text-[#F5EFE6] rounded-lg text-[16px] font-bold tracking-wide transition-all flex items-center justify-center gap-2"
                    >
                        <svg v-if="isLoading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        {{ isLoading ? 'Mendaftarkan...' : 'Buat Akun' }}
                    </button>
                </form>

                <div class="flex items-center justify-between mt-1 text-[13px] sm:text-[14px] font-bold">
                    <span class="text-[#A89880]">
                        Sudah punya akun?
                        <router-link :to="{ name: 'auth.login-lagi' }" class="text-[#1C1813] hover:underline">Masuk</router-link>
                    </span>
                    <router-link :to="{ name: 'auth.forgot-password' }" class="text-[#A89880] hover:underline">Lupa Password?</router-link>
                </div>
            </div>

            <div class="flex-1 relative overflow-hidden hidden md:flex items-center justify-end p-10 min-h-full" style="background: linear-gradient(180deg, #1C1813 0%, #4B3832 100%);">
                <div class="absolute inset-0 opacity-[0.18] auth-overlay"></div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useUserProfileStore } from '../../stores/userProfile';

const router = useRouter();
const userProfileStore = useUserProfileStore();

const mockData = {
    logoUrl: '/assets/logo-invert.png',
    description: 'Lengkapi data diri Anda untuk bergabung ke dalam jaringan rantai pasok.'
};

const name = ref('');
const email = ref('');
const password = ref('');
const confirmPassword = ref('');
const role = ref('');
const showPassword = ref(false);
const showConfirm = ref(false);
const clickedRole = ref(null);
const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const isEmailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value));
const isConfirmValid = computed(() => confirmPassword.value.length > 0 && password.value === confirmPassword.value);
const canSubmit = computed(() => isEmailValid.value && isConfirmValid.value && role.value !== '');

const passwordScore = computed(() => {
    if (!password.value) return 0;
    let score = 0;
    if (password.value.length >= 8) score += 1;
    if (/[A-Z]/.test(password.value) && /[a-z]/.test(password.value)) score += 1;
    if (/\d/.test(password.value) && /[^A-Za-z0-9]/.test(password.value)) score += 1;
    return score;
});

const strengthLabel = computed(() => ['Lemah', 'Sedang', 'Kuat'][passwordScore.value - 1] ?? 'Lemah');

const togglePassword = () => { showPassword.value = !showPassword.value; };
const toggleConfirm = () => { showConfirm.value = !showConfirm.value; };

const selectRole = (value) => {
    role.value = role.value === value ? '' : value;
    clickedRole.value = value;
    setTimeout(() => { clickedRole.value = null; }, 160);
};

/** Map role → named route untuk redirect setelah register */
const dashboardRouteMap = {
    farmer: 'farmer.dashboard',
    exporter: 'exporter.dashboard',
    buyer: 'buyer.dashboard',
};

const handleSubmit = async () => {
    if (!canSubmit.value || isLoading.value) return;

    isLoading.value = true;
    apiMessage.value = '';

    try {
        const { data } = await axios.post('/api/v1/auth/register', {
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: confirmPassword.value,
            role: role.value,
        });

        // Simpan token ke localStorage
        if (data?.data?.access_token) {
            localStorage.removeItem('access_token');
            localStorage.removeItem('user_role');
            localStorage.setItem('access_token', data.data.access_token);
            localStorage.setItem('user_role', role.value);
            
            userProfileStore.setProfile({
                fullName: name.value,
                initials: name.value.charAt(0).toUpperCase(),
                email: email.value,
            });
        }

        apiSuccess.value = true;
        apiMessage.value = 'Akun berhasil dibuat! Mengalihkan...';

        setTimeout(() => {
            router.push({ name: dashboardRouteMap[role.value] ?? 'landing' });
        }, 800);
    } catch (err) {
        apiSuccess.value = false;
        const res = err?.response?.data;

        if (res?.code === 'VALIDATION_ERROR' && res?.details) {
            const firstError = Object.values(res.details)[0];
            apiMessage.value = Array.isArray(firstError) ? firstError[0] : firstError;
        } else if (res?.code === 'CONFLICT') {
            apiMessage.value = 'Email sudah terdaftar. Gunakan email lain atau masuk.';
        } else {
            apiMessage.value = res?.message ?? 'Terjadi kesalahan. Coba lagi.';
        }
    } finally {
        isLoading.value = false;
    }
};
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
</style>
