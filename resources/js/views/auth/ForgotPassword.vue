<!-- <template>
    <div class="w-full min-h-screen bg-[#FDFDFC]">
        <div class="relative w-full min-h-screen overflow-hidden bg-[#FDFDFC] flex flex-col items-center">
            <div class="absolute left-[135px] top-[150px] w-[138px] h-[138px] -translate-y-[150px] z-10">
                <img alt="BIJI Logo" class="w-full h-full object-cover" :src="mockData.logoUrl" />
            </div>

            <div class="absolute right-0 -top-px h-[calc(100%+1px)] w-[41.6%] bg-[#1c1813] overflow-hidden z-0">
                <div class="w-full h-full flex items-center justify-center border border-dashed border-[#A89880] opacity-50 relative pointer-events-none">
                    <span class="text-[#A89880] font-bold text-lg">Banner Asset Placeholder</span>
                </div>
            </div>

            <div class="absolute inset-0 -translate-y-[150px] z-10">
                <div class="absolute" style="left: 177.38px; top: 288px; width: 400px; height: 69.39px; display: flex; align-items: center;">
                    <p class="font-bold text-[42px] text-[#1e1e24] leading-[normal]" style="font-family: 'DM Sans', sans-serif; font-variation-settings: 'opsz' 14;">{{ mockData.heading }}</p>
                </div>

                <div class="absolute" style="left: 177px; top: 378px; width: 416.28px; height: 46px; display: flex; align-items: center;">
                    <p class="font-bold text-[16px] text-[#1c1813] leading-[normal]" style="font-family: 'DM Sans', sans-serif; font-variation-settings: 'opsz' 14;">{{ mockData.description }}</p>
                </div>

                <p class="absolute text-[13px] text-[#666] font-normal leading-normal" style="left: 177px; top: 491px; width: 84.32px; font-family: 'Inter', sans-serif;">Alamat Email</p>

                <div class="absolute group" style="left: 177px; top: 517.7px; width: 490.99px; height: 51.26px;">
                    <img alt="" class="absolute inset-0 w-full h-full block pointer-events-none" :src="mockData.inputBorderUrl" />
                    <input
                        id="email"
                        v-model="email"
                        type="email"
                        name="email"
                        :placeholder="mockData.emailPlaceholder"
                        class="absolute left-0 top-0 w-full h-full bg-transparent border-0 px-[17px] py-[14px] text-[15px] text-[#1a1a1a] placeholder-[#1a1a1a]/50 focus:outline-none focus:ring-0 font-bold peer"
                        style="font-family: 'Inter', sans-serif;"
                        required
                    />
                    <div
                        id="email-check"
                        class="absolute right-[17px] top-[14px] hidden text-[#08C246] pointer-events-none"
                        :style="isEmailValid ? 'display: block;' : ''"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>

                <button
                    type="submit"
                    class="absolute focus:outline-none hover:opacity-90 transition-opacity active:scale-[0.98]"
                    style="left: 177px; top: 608px; width: 156.59px; height: 62.63px; background: none; border: none; padding: 0; cursor: pointer;"
                    @click.prevent="submitRequest"
                >
                    <img alt="" class="absolute inset-0 w-full h-full block pointer-events-none" :src="mockData.buttonUrl" />
                    <span
                        class="absolute left-0 top-0 w-full h-full flex items-center justify-center font-bold text-[16px] text-white"
                        style="font-family: 'Inter', sans-serif;"
                        :style="buttonTextStyle"
                    >
                        {{ submitLabel }}
                    </span>
                </button>

                <transition name="fade">
                    <div
                        id="success-message"
                        class="absolute hidden"
                        style="left: 177px; top: 700px; width: 490px;"
                        :style="isSubmitted ? 'display: block;' : ''"
                    >
                        <div class="bg-[#08C246]/10 border border-[#08C246] rounded-lg p-4 flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#08C246] mt-0.5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-[#08C246] text-[14px] font-medium" style="font-family: 'Inter', sans-serif;">
                                {{ mockData.successMessage }}
                            </p>
                        </div>
                    </div>
                </transition>

                <router-link
                    :to="{ name: 'auth.login-lagi' }"
                    class="absolute font-bold text-[16px] text-[#889] hover:text-[#1c1813] transition-colors"
                    style="left: 177px; top: 922px; width: 200px; font-family: 'Inter', sans-serif;"
                >
                    <span class="mr-1">←</span> Kembali ke Login
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

const mockData = {
    logoUrl: 'http://localhost:3845/assets/983b6104ae3ef5f982afc7c2955b7e9b4aa74da4.png',
    inputBorderUrl: 'http://localhost:3845/assets/3a4fcdd897c25faef8c8ffe956e819f1e8ff94ca.svg',
    buttonUrl: 'http://localhost:3845/assets/6692f3e06e44ae5a09fecee523eafe8811db54ff.svg',
    heading: 'Lupa Password',
    description: 'Masukkan alamat email Anda untuk menerima link reset password akun BIJI Anda.',
    emailPlaceholder: 'andi@kopi-sulawesi.com',
    successMessage: 'Link reset password telah dikirim. Silakan cek kotak masuk atau folder spam di email Anda.'
};

const email = ref('');
const isSubmitted = ref(false);

const isEmailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value));
const submitLabel = computed(() => (isSubmitted.value ? 'Kirim Ulang (60s)' : 'Kirim Link'));
const buttonTextStyle = computed(() => (isSubmitted.value ? 'color: rgba(255,255,255,0.5);' : ''));

const submitRequest = () => {
    isSubmitted.value = true;
};

onMounted(() => {
    // TODO: axios.get('/api/auth/forgot-password') untuk menginisialisasi data jika dibutuhkan.
});
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
</style> -->
