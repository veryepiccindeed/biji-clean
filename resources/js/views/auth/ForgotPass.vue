<template>
    <div class="min-h-screen bg-[#0F0D0B] flex items-center justify-center p-6 md:p-8 font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="flex flex-col-reverse md:flex-row w-full max-w-[1200px] min-h-max md:min-h-[620px] rounded-2xl overflow-hidden shadow-[0_24px_80px_rgba(0,0,0,0.5)]">
            <div class="md:w-[44%] bg-[#F5EFE6] p-6 md:p-10 lg:p-16 flex flex-col justify-center">
                <div class="mb-4 md:mb-7 flex justify-center md:justify-start">
                    <img :src="mockData.logoUrl" alt="logo" class="w-[120px] h-[120px] md:w-[180px] md:h-[180px] object-contain md:-ml-10 md:-mt-2">
                </div>

                <h1 class="text-[32px] font-bold font-sans text-[#1C1813] leading-tight mb-3">Reset Password</h1>

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

                    <button
                        type="submit"
                        :disabled="!isEmailValid || isLoading"
                        :class="(isEmailValid && !isLoading) ? 'bg-[#1C1813] hover:bg-[#2A2118] active:scale-[0.99]' : 'bg-[#D4C9B4] cursor-not-allowed'"
                        class="mt-2 w-full h-[52px] text-[#F5EFE6] rounded-lg text-[16px] font-bold tracking-wide transition-all flex items-center justify-center gap-2"
                    >
                        <svg v-if="isLoading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        {{ isLoading ? 'Memproses...' : 'Kirim Tautan' }}
                    </button>
                </form>

                <div class="flex items-center justify-between mt-1 text-[13px] sm:text-[14px] font-bold">
                    <span class="text-[#A89880]"></span>
                    <router-link :to="{ name: 'auth.login-lagi' }" class="text-[#A89880] hover:underline">Kembali ke Login</router-link>
                </div>
            </div>

            <div class="flex-1 relative overflow-hidden hidden md:flex items-center justify-end p-10 min-h-full" style="background: linear-gradient(180deg, #1C1813 0%, #4B3832 100%);">
                <div class="absolute inset-0 opacity-[0.18] auth-overlay"></div>

                <div class="relative z-10">
                    <svg width="56" height="72" viewBox="0 0 56 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="32" width="52" height="38" rx="6" fill="#F5EFE6" opacity="0.85" />
                        <path d="M12 32V22C12 12.059 20.059 4 30 4C39.941 4 48 12.059 48 22V32" stroke="#F5EFE6" stroke-width="5" stroke-linecap="round" fill="none" opacity="0.85" />
                        <circle cx="28" cy="51" r="6" fill="#4B3832" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';

const mockData = {
    logoUrl: '/assets/logo-invert.png',
    description: 'Masukkan email terdaftar untuk menerima tautan pengaturan ulang kata sandi.'
};

const email = ref('');
const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const isEmailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value));

const handleSubmit = async () => {
    if (!isEmailValid.value || isLoading.value) return;

    isLoading.value = true;
    apiMessage.value = '';

    try {
        const { data } = await axios.post('/api/v1/auth/forgot-password', {
            email: email.value,
        });

        apiSuccess.value = true;
        apiMessage.value = data?.message ?? 'Instruksi reset password telah dikirim ke email Anda.';
    } catch (err) {
        apiSuccess.value = false;
        const res = err?.response?.data;
        apiMessage.value = res?.message ?? 'Terjadi kesalahan saat memproses permintaan.';
    } finally {
        isLoading.value = false;
    }
};
</script>
