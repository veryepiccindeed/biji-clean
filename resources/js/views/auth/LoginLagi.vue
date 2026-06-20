<template>
    <div class="min-h-screen bg-[#0F0D0B] flex items-center justify-center p-6 md:p-8 font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="flex flex-col-reverse md:flex-row w-full max-w-[1200px] min-h-max md:min-h-[620px] rounded-2xl overflow-hidden shadow-[0_24px_80px_rgba(0,0,0,0.5)]">
            <div class="md:w-[44%] bg-[#F5EFE6] p-6 md:p-10 lg:p-16 flex flex-col justify-center">
                <div class="mb-4 md:mb-7 flex justify-center md:justify-start">
                    <img :src="mockData.logoUrl" alt="logo" class="w-[120px] h-[120px] md:w-[180px] md:h-[180px] object-contain md:-ml-10 md:-mt-2">
                </div>

                <h1 class="text-[32px] font-bold font-sans text-[#1C1813] leading-tight mb-3">Halo!</h1>

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

                <form class="flex flex-col gap-5 mb-6" novalidate @submit.prevent="handleSubmit">
                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-[13px] text-[#5C4F42]">Alamat Email</label>
                        <div class="relative flex items-center">
                            <input
                                id="login-email"
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
                                id="login-password"
                                v-model="password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
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
                    </div>

                    <button
                        type="submit"
                        :disabled="!isEmailValid || password.length === 0 || isLoading"
                        :class="(isEmailValid && password.length > 0 && !isLoading) ? 'bg-[#1C1813] hover:bg-[#2A2118] active:scale-[0.99]' : 'bg-[#D4C9B4] cursor-not-allowed'"
                        class="mt-2 w-full h-[52px] text-[#F5EFE6] rounded-lg text-[16px] font-bold tracking-wide transition-all flex items-center justify-center gap-2"
                    >
                        <svg v-if="isLoading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        {{ isLoading ? 'Memproses...' : 'Masuk' }}
                    </button>
                </form>

                <div class="flex items-center justify-between mt-1 text-[13px] sm:text-[14px] font-bold">
                    <span class="text-[#A89880]">
                        Belum punya akun?
                        <router-link :to="{ name: 'auth.register-lagi' }" class="text-[#1C1813] hover:underline">Daftar</router-link>
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
    description: 'Masuk ke ekosistem BIJI untuk mengelola rantai pasok kopi Anda yang transparan dan terdesentralisasi.'
};

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const isEmailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value));

const togglePassword = () => {
    showPassword.value = !showPassword.value;
};

const dashboardRouteMap = {
    farmer: 'farmer.dashboard',
    exporter: 'exporter.dashboard',
    buyer: 'buyer.dashboard',
};

const handleSubmit = async () => {
    if (!isEmailValid.value || password.value.length === 0 || isLoading.value) return;

    isLoading.value = true;
    apiMessage.value = '';

    try {
        const { data } = await axios.post('/api/v1/auth/login', {
            email: email.value,
            password: password.value,
        });

        if (data?.data?.access_token) {
            localStorage.removeItem('access_token');
            localStorage.removeItem('user_role');
            localStorage.setItem('access_token', data.data.access_token);
            
            const userRole = data.data.user?.role ?? '';
            const userName = data.data.user?.name ?? 'User';
            localStorage.setItem('user_role', userRole);

            userProfileStore.setProfile({
                fullName: userName,
                initials: userName.charAt(0).toUpperCase(),
                email: data.data.user?.email || '',
                phone: data.data.user?.phone || '',
                location: data.data.user?.location || '',
            });

            apiSuccess.value = true;
            apiMessage.value = 'Login berhasil! Mengalihkan...';

            setTimeout(() => {
                router.push({ name: dashboardRouteMap[userRole] ?? 'landing' });
            }, 800);
        } else {
            apiSuccess.value = false;
            apiMessage.value = 'Data respon tidak valid.';
        }
    } catch (err) {
        apiSuccess.value = false;
        const res = err?.response?.data;
        apiMessage.value = res?.message ?? 'Email atau password salah.';
    } finally {
        isLoading.value = false;
    }
};
</script>
