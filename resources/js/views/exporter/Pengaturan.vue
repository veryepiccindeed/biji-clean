<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-exporter font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(75, 56, 50, 0.35), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.12), transparent 60%);"></div>
            
            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10">
                <header class="flex items-start justify-between gap-6">
                    <div>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Pengaturan Akun</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">{{ pageSubtitle }}</p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="Logo"
                            class="w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mt-10 lg:-mr-10 pointer-events-none"
                        >
                    </div>
                </header>

                    <transition name="fade">
                        <div v-if="apiMessage" class="mt-6 px-4 py-3 rounded-lg text-[13px] font-bold"
                            :class="apiSuccess ? 'bg-[#d1fae5] text-[#065f46] border border-[#6ee7b7]' : 'bg-[#fee2e2] text-[#991b1b] border border-[#fca5a5]'">
                            {{ apiMessage }}
                        </div>
                    </transition>

                    <div class="mt-8 sticky top-0 z-30  backdrop-blur-md">
                        <nav class=" flex flex-wrap gap-6 text-[13px] font-medium border-b border-[#2E241C] px-1" data-scrollspy>
                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#FFD66B] hover:border-[#E8A838]/60"
                                :class="tabClass('profil')"
                                data-tab
                                href="#profil"
                            >
                                Profil
                            </a>

                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#FFD66B] hover:border-[#E8A838]/60"
                                :class="tabClass('keamanan')"
                                data-tab
                                href="#keamanan"
                            >
                                Keamanan
                            </a>

                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#FFD66B] hover:border-[#E8A838]/60"
                                :class="tabClass('preferensi')"
                                data-tab
                                href="#preferensi"
                            >
                                Preferensi
                            </a>

                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#FFD66B] hover:border-[#E8A838]/60"
                                :class="tabClass('perangkat')"
                                data-tab
                                href="#perangkat"
                            >
                                Perangkat
                            </a>

                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#FFD66B] hover:border-[#E8A838]/60"
                                :class="tabClass('koneksi')"
                                data-tab
                                href="#koneksi"
                            >
                                Koneksi
                            </a>
                        </nav>
                    </div>

                    <section id="profil" ref="profilRef" class="mt-10" data-profile-section>
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="font-sans text-[20px]">Profil</h2>
                                    <p class="text-[13px] text-[#A89880] mt-1">Perbarui detail profil agar tetap akurat dan profesional.</p>
                                </div>
                                <span class="text-[12px] text-[#A89880] font-medium">Terakhir diperbarui: {{ profile.updatedAt }}</span>
                            </div>

                            <div class="mt-6 flex flex-col xl:flex-row gap-8">
                                <div class="flex flex-col items-center gap-4">
                                    <div class="w-28 h-28 rounded-full border border-[#4A3728] bg-[#2A2118] flex items-center justify-center text-[24px] text-[#A89880]">{{ profile.initials }}</div>
                                    <button class="px-4 py-2 rounded-lg border border-[#4A3728] text-[13px] text-[#F5EFE6] hover:bg-[#2A2118] transition">Unggah Foto</button>
                                </div>

<div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="text-[13px] text-[#A89880]" for="nama-lengkap">Nama Lengkap</label>
        <input
            id="nama-lengkap"
            v-model="profile.fullName"
            type="text"
            :readonly="!isEditing"
            :class="{ editing: isEditing }"
            class="profile-input mt-2 w-full max-w-md rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]"
        >
    </div>

    <div>
        <label class="text-[13px] text-[#A89880]" for="telepon">Nomor Telepon</label>
        <input
            id="telepon"
            v-model="profile.phone"
            type="text"
            :readonly="!isEditing"
            :class="{ editing: isEditing }"
            class="profile-input mt-2 w-full max-w-md rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]"
        >
    </div>

    <div> <label class="text-[13px] text-[#A89880]" for="email">Email</label>
        <div>
            <input
                id="email"
                v-model="profile.email"
                type="email"
                :readonly="!isEditing"
                :class="{ editing: isEditing }"
                class="profile-input mt-2 w-full max-w-md rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]"
            >
            <div>
                <span
                    v-if="profile.verified"
                    class="inline-flex items-center gap-2 rounded-full border border-[#4CAF7D] bg-[#1A3D2E] px-3 py-1 text-[11px] text-[#4CAF7D] mt-4 ml-2"
                >
                    Terverifikasi
                </span>
            </div>
        </div>
    </div>

    <div>
        <label class="text-[13px] text-[#A89880]" for="lokasi">Lokasi</label>
        <input
            id="lokasi"
            v-model="profile.location"
            type="text"
            :readonly="!isEditing"
            :class="{ editing: isEditing }"
            class="profile-input mt-2 w-full max-w-md rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]"
        >
    </div>
</div>
                            </div>

                            <div class="mt-8 flex items-center justify-end gap-3">
                                <transition name="fade">
                                    <div v-if="isEditing" data-edit-actions class="flex items-center gap-3">
                                        <button
                                            type="button"
                                            data-cancel-edit
                                            class="px-4 py-2 rounded-lg border border-[#4A3728] text-[13px] text-[#A89880] hover:text-[#F5EFE6] hover:bg-[#2A2118] transition"
                                            @click="cancelEdit"
                                        >
                                            Batal
                                        </button>

                                        <button
                                            type="button"
                                            data-save-profile
                                            class="px-5 py-2 rounded-lg text-[13px] text-[#F5EFE6] border border-[#4A3728]"
                                            style="background-color: var(--theme-primary);"
                                            @click="saveProfile"
                                        >
                                            Simpan Perubahan
                                        </button>
                                    </div>
                                </transition>

                                <button
                                    type="button"
                                    data-edit-trigger
                                    class="w-11 h-11 rounded-xl border border-[#4A3728] bg-[#2A2118] hover:bg-[#33271D] transition flex items-center justify-center text-[#E8A838]"
                                    @click="startEdit"
                                >
                                    ✎
                                </button>
                            </div>
                        </div>
                    </section>

                    <section id="keamanan" ref="keamananRef" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="font-sans text-[20px]">Keamanan</h2>
                                    <p class="text-[13px] text-[#A89880] mt-1">Jaga akses akun dengan kata sandi yang kuat.</p>
                                </div>
                                <span class="text-[11px] text-[#5C4F42]">{{ security.updatedAt }}</span>
                            </div>

                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="password-lama">Password Lama</label>
                                    <input id="password-lama" v-model="security.oldPassword" type="password" placeholder="Masukkan password lama" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]" />
                                </div>
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="password-baru">Password Baru</label>
                                    <input id="password-baru" v-model="security.newPassword" type="password" placeholder="Password baru" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]" />
                                </div>
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="password-konfirmasi">Konfirmasi Password</label>
                                    <input id="password-konfirmasi" v-model="security.confirmPassword" type="password" placeholder="Ulangi password" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]" />
                                </div>
                            </div>

                            <div class="mt-8 flex items-center justify-end">
                                <button @click="updatePassword" :disabled="isLoading" class="px-5 py-2 rounded-lg text-[13px] text-[#F5EFE6] border border-[#4A3728] disabled:opacity-50 disabled:cursor-not-allowed" style="background-color: var(--theme-primary);">
                                    {{ isLoading ? 'Memproses...' : 'Update Password' }}
                                </button>
                            </div>
                        </div>
                    </section>

                    <section id="preferensi" ref="preferensiRef" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="font-sans text-[20px]">Preferensi</h2>
                                    <p class="text-[13px] text-[#A89880] mt-1">Atur bahasa, notifikasi, dan tampilan sesuai kebutuhan Anda.</p>
                                </div>
                                <span class="text-[11px] text-[#5C4F42]">{{ preferences.syncLabel }}</span>
                            </div>                             <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="bahasa">Bahasa</label>
                                    <div class="relative">
                                        <select id="bahasa" v-model="preferences.language" @change="savePreferences" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] appearance-none">
                                            <option>Indonesia</option>
                                            <option>English</option>
                                        </select>

                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="zona">Zona Waktu</label>
                                    <div class="relative">
                                        <select id="zona" v-model="preferences.timezone" @change="savePreferences" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] appearance-none">
                                            <option>WITA (UTC+8)</option>
                                            <option>WIB (UTC+7)</option>
                                            <option>WIT (UTC+9)</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="suhu">
                                        Format Suhu
                                    </label>
                                    <div class="relative">
                                        <select
                                            id="suhu"
                                            v-model="preferences.temperature"
                                            @change="savePreferences"
                                            class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] appearance-none"
                                        >
                                            <option>Celsius (°C)</option>
                                            <option>Fahrenheit (°F)</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="elevasi">
                                        Format Elevasi
                                    </label>

                                    <div class="relative">
                                        <select
                                            v-model="preferences.elevation"
                                            @change="savePreferences"
                                            class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728]
                                                px-4 py-3 pr-10 text-[15px] text-[#F5EFE6]
                                                focus:outline-none focus:border-[#8B6355]
                                                appearance-none"
                                        >
                                            <option>Meter di atas permukaan laut (mdpl)</option>
                                            <option>Feet (ft)</option>
                                        </select>

                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="perangkat" ref="perangkatRef" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="font-sans text-[20px]">Perangkat</h2>
                                    <p class="text-[13px] text-[#A89880] mt-1">Daftar perangkat yang saat ini terhubung dengan akun Anda.</p>
                                </div>
                                <span class="text-[11px] text-[#5C4F42]">{{ devicesLabel }}</span>
                            </div>

                            <div class="mt-6 space-y-4">
                                <div
                                    v-for="device in devices"
                                    :key="device.id"
                                    class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-[#2E241C] bg-[#14110D] px-5 py-4"
                                >
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-full bg-[#2A2118] border border-[#4A3728] flex items-center justify-center text-[16px]">{{ device.icon }}</div>
                                        <div>
                                            <p class="text-[15px]">{{ device.title }}</p>
                                            <p class="text-[12px] text-[#A89880]">{{ device.subtitle }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span :class="device.statusClass" class="rounded-full border px-3 py-1 text-[11px]">{{ device.statusLabel }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="koneksi" ref="koneksiRef" class="mt-10">
                        <div class="mb-6">
                            <h2 class="font-sans text-[20px] font-bold text-[#F5EFE6]">Koneksi Jaringan Blockchain</h2>
                            <p class="text-[13px] text-[#A89880] mt-1">{{ koneksiIntro }}</p>
                        </div>

                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <p class="text-[13px] text-[#A89880] mb-4">Status Jaringan Polygon (Saat Ini)</p>

                            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-[#2E241C] bg-[#0F0D0B] px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-[#08C246] shadow-[0_0_8px_rgba(8,194,70,0.3)]"></div>
                                    <span class="text-[15px] font-bold text-[#08C246]">{{ networkStatus.label }}</span>
                                    <span class="text-[13px] text-[#A89880] font-mono ml-2 tracking-wide">{{ networkStatus.detail }}</span>
                                </div>
                                <button type="button" class="flex items-center gap-2 px-4 py-2 rounded-lg border border-[#4A3728] text-[13px] text-[#F5EFE6] hover:bg-[#2A2118] transition-colors focus:outline-none focus:border-[#08C246]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Refresh Status
                                </button>
                            </div>

                            <div class="mt-8">
                                <div class="flex items-center gap-3 px-5">
                                    <div class="w-3 h-3 rounded-full bg-[#D94F4F] shadow-[0_0_8px_rgba(217,79,79,0.3)]"></div>
                                    <span class="text-[15px] font-bold text-[#D94F4F]">{{ networkWarning }}</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
import { useUserProfileStore } from '../../stores/userProfile';
import Sidebar from '../../components/exporter/Sidebar.vue';
import axios from 'axios';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'exporter.pengaturan');

const logoUrl = '/assets/logo-fix.png';
const pageSubtitle = 'Kelola profil, keamanan, preferensi, perangkat, dan koneksi akun Anda.';

const userProfileStore = useUserProfileStore();
const { profile } = storeToRefs(userProfileStore);

const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const isEditing = ref(false);
let profileSnapshot = null;

const startEdit = () => {
    profileSnapshot = { ...profile.value };
    isEditing.value = true;
};

const cancelEdit = () => {
    if (profileSnapshot) {
        userProfileStore.resetProfile(profileSnapshot);
    }
    isEditing.value = false;
};

const saveProfile = async () => {
    isLoading.value = true;
    apiMessage.value = '';
    try {
        const response = await axios.patch('/api/v1/me/profile', {
            name: profile.value.fullName,
            phone: profile.value.phone,
            location: profile.value.location
        });
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Profil berhasil diperbarui!';
            isEditing.value = false;
            await fetchProfileAndSettings();
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal memperbarui profil.';
    } finally {
        isLoading.value = false;
    }
};

const security = reactive({
    updatedAt: 'Terakhir diperbarui 18 Mar 2026',
    oldPassword: '',
    newPassword: '',
    confirmPassword: ''
});

const updatePassword = async () => {
    if (security.newPassword !== security.confirmPassword) {
        apiSuccess.value = false;
        apiMessage.value = 'Konfirmasi password baru tidak cocok.';
        return;
    }
    isLoading.value = true;
    apiMessage.value = '';
    try {
        const response = await axios.patch('/api/v1/me/security/password', {
            old_password: security.oldPassword,
            password: security.newPassword,
            password_confirmation: security.confirmPassword
        });
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Password berhasil diperbarui!';
            security.oldPassword = '';
            security.newPassword = '';
            security.confirmPassword = '';
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal memperbarui password.';
    } finally {
        isLoading.value = false;
    }
};

const preferences = reactive({
    syncLabel: 'Sinkron otomatis',
    language: 'Indonesia',
    timezone: 'WITA (UTC+8)',
    temperature: 'Celsius (°C)',
    elevation: 'Meter di atas permukaan laut (mdpl)'
});

const savePreferences = async () => {
    isLoading.value = true;
    apiMessage.value = '';
    try {
        const lang = preferences.language === 'English' ? 'en' : 'id';
        const tz = preferences.timezone.includes('WITA') ? 'Asia/Makassar' : (preferences.timezone.includes('WIB') ? 'Asia/Jakarta' : 'Asia/Jayapura');
        const temp = preferences.temperature.includes('Fahrenheit') ? 'fahrenheit' : 'celsius';

        const response = await axios.patch('/api/v1/me/settings', {
            language: lang,
            timezone: tz,
            temperature_unit: temp,
            notifications_enabled: true,
            email_notifications: true
        });
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Preferensi berhasil diperbarui!';
            setTimeout(() => { apiMessage.value = ''; }, 3000);
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal memperbarui preferensi.';
    } finally {
        isLoading.value = false;
    }
};

const devicesLabel = ref('3 perangkat aktif');
const devices = ref([]);

const koneksiIntro = 'Pantau status integrasi node Anda dengan jaringan publik Polygon.';
const networkStatus = reactive({
    label: 'Terkoneksi',
    detail: 'Ping: 24ms • Block: #4592011'
});
const networkWarning = 'Terputus — Periksa koneksi server';

const activeSection = ref('profil');
const profilRef = ref(null);
const keamananRef = ref(null);
const preferensiRef = ref(null);
const perangkatRef = ref(null);
const koneksiRef = ref(null);

const sectionRefs = {
    profil: profilRef,
    keamanan: keamananRef,
    preferensi: preferensiRef,
    perangkat: perangkatRef,
    koneksi: koneksiRef
};

const tabClass = (section) =>
    activeSection.value === section ? 'text-[#FFD66B] border-[#E8A838]/60' : '';

const updateActiveSection = () => {
    const offset = 140;
    let current = 'profil';

    Object.entries(sectionRefs).forEach(([key, refEl]) => {
        const el = refEl.value;
        if (!el) return;
        const top = el.getBoundingClientRect().top - offset;
        if (top <= 0) {
            current = key;
        }
    });

    activeSection.value = current;
};

const fetchProfileAndSettings = async () => {
    try {
        const profileRes = await axios.get('/api/v1/me/profile');
        if (profileRes.data?.success && profileRes.data?.data) {
            const p = profileRes.data.data;
            userProfileStore.setProfile({
                fullName: p.name || '',
                email: p.email || '',
                phone: p.phone || '',
                location: p.location || '',
                initials: (p.name || 'AC').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase(),
                verified: p.phone_verified_at ? true : false,
                updatedAt: p.updated_at ? new Date(p.updated_at).toLocaleDateString('id-ID') : 'Baru saja'
            });
        }
    } catch (err) {
        console.error('Failed to load exporter profile:', err);
    }

    try {
        const settingsRes = await axios.get('/api/v1/me/settings');
        if (settingsRes.data?.success && settingsRes.data?.data) {
            const s = settingsRes.data.data;
            preferences.language = s.language === 'en' ? 'English' : 'Indonesia';
            preferences.timezone = s.timezone === 'Asia/Makassar' || s.timezone === 'WITA' ? 'WITA (UTC+8)' : (s.timezone === 'Asia/Jakarta' || s.timezone === 'WIB' ? 'WIB (UTC+7)' : 'WIT (UTC+9)');
            preferences.temperature = s.temperature_unit === 'fahrenheit' ? 'Fahrenheit (°F)' : 'Celsius (°C)';
        }
    } catch (err) {
        console.error('Failed to load exporter settings:', err);
    }

    try {
        const devicesRes = await axios.get('/api/v1/me/devices');
        if (devicesRes.data?.success && devicesRes.data?.data) {
            devices.value = devicesRes.data.data.map(dev => ({
                id: dev.id,
                icon: dev.name.toLowerCase().includes('iphone') || dev.name.toLowerCase().includes('android') || dev.name.toLowerCase().includes('phone') ? '📱' : '💻',
                title: dev.name === 'auth_token' ? 'Sesi Web Browser' : dev.name,
                subtitle: dev.last_used_at ? `Aktif ${new Date(dev.last_used_at).toLocaleString('id-ID')}` : 'Belum tercatat',
                statusLabel: dev.is_current ? 'Aktif' : 'Terkoneksi',
                statusClass: dev.is_current ? 'border-[#4CAF7D] bg-[#1A3D2E] text-[#4CAF7D]' : 'border-[#5C4F42] bg-[#1C1813] text-[#A89880]'
            }));
            devicesLabel.value = `${devices.value.length} perangkat aktif`;
        }
    } catch (err) {
        console.error('Failed to load devices:', err);
    }
};

onMounted(() => {
    updateActiveSection();
    window.addEventListener('scroll', updateActiveSection, { passive: true });
    fetchProfileAndSettings();
});

onUnmounted(() => {
    window.removeEventListener('scroll', updateActiveSection);
});
</script>

<style>
.profile-input[readonly],
.profile-input:disabled {
    opacity: 0.7;
    cursor: default;
}

.profile-input.editing {
    opacity: 1;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
