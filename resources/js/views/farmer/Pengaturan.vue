<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-farmer font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(45, 106, 79, 0.30), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.10), transparent 60%);"></div>
            
            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-10 py-6 pt-24 lg:pb-10 relative z-10">
                <header class="flex items-start justify-between gap-6">
                    <div>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Pengaturan Petani</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">Kelola profil, preferensi log, dan notifikasi batch petani.</p>
                    </div>
                    <div class="flex items-center hidden sm:flex">
                        <img
                            :src="logoUrl"
                            alt="Logo"
                            class="w-16 h-16 sm:w-24 sm:h-24 lg:w-[138px] lg:h-[138px] object-contain lg:-mt-10 lg:-mr-10 pointer-events-none"
                        >
                    </div>
                </header>

                    <!-- Error / Success Banner -->
                    <transition name="fade">
                        <div v-if="apiMessage" class="mt-6 px-4 py-3 rounded-lg text-[13px] font-bold"
                            :class="apiSuccess ? 'bg-[#d1fae5] text-[#065f46] border border-[#6ee7b7]' : 'bg-[#fee2e2] text-[#991b1b] border border-[#fca5a5]'">
                            {{ apiMessage }}
                        </div>
                    </transition>

                    <div class="mt-8 sticky top-0 z-30 backdrop-blur-md">
                        <nav class="flex flex-wrap gap-6 text-[13px] font-medium border-b border-[#2E241C] px-1" data-scrollspy>
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

                    <section id="profil" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h2 class="font-sans text-[20px]">Profil Kebun</h2>
                                    <p class="text-[13px] text-[#A89880] mt-1">Perbarui data yang dipakai untuk batch, lokasi, dan kredibilitas akun petani.</p>
                                </div>
                                <span class="text-[12px] text-[#A89880] font-medium">Terverifikasi dasar</span>
                            </div>

                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div class="md:col-span-2">
                                    <label class="text-[13px] text-[#A89880]" for="nama-lengkap">Nama Lengkap</label>
                                    <input id="nama-lengkap" v-model="profile.fullName" :readonly="!isEditing" :class="{ editing: isEditing }" type="text" class="profile-input mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]" />

                                    <div class="mt-4">
                                        <label class="text-[13px] text-[#A89880]" for="email">Email</label>
                                        <input id="email" v-model="profile.email" readonly type="email" class="profile-input mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#2E241C] px-4 py-3 text-[15px] text-[#A89880] focus:outline-none cursor-not-allowed" />
                                    </div>

                                </div>

                                <div class="md:col-span-1 flex flex-col gap-5">
                                    <div>
                                        <label class="text-[13px] text-[#A89880]" for="telepon">Nomor Telepon</label>
                                        <input id="telepon" v-model="profile.phone" :readonly="!isEditing" :class="{ editing: isEditing }" type="text" class="profile-input mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]" />
                                    </div>

                                    <div>
                                        <label class="text-[13px] text-[#A89880]" for="lokasi">Lokasi Kebun</label>
                                        <input id="lokasi" v-model="profile.location" :readonly="!isEditing" :class="{ editing: isEditing }" type="text" class="profile-input mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]" />
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
                                            {{ isLoading ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                        </button>
                                    </div>
                                </transition>

                                <button
                                    v-if="!isEditing"
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

                    <section id="keamanan" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="font-sans text-[20px]">Keamanan</h2>
                                    <p class="text-[13px] text-[#A89880] mt-1">Jaga akses akun dengan kata sandi yang kuat.</p>
                                </div>
                                <span class="text-[11px] text-[#5C4F42]">Diperbarui: {{ security.updatedAt }}</span>
                            </div>

                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="password-lama">Password Lama</label>
                                    <input id="password-lama" v-model="security.oldPassword" type="password" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]" />
                                </div>
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="password-baru">Password Baru</label>
                                    <input id="password-baru" v-model="security.newPassword" type="password" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]" />
                                </div>
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="password-konfirmasi">Konfirmasi Password</label>
                                    <input id="password-konfirmasi" v-model="security.confirmPassword" type="password" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355]" />
                                </div>
                            </div>

                            <div class="mt-8 flex items-center justify-end">
                                <button @click="updatePassword" :disabled="isLoading" class="px-5 py-2 rounded-lg text-[13px] text-[#F5EFE6] border border-[#4A3728]" style="background-color: var(--theme-primary);">
                                    {{ isLoading ? 'Memproses...' : 'Update Password' }}
                                </button>
                            </div>
                        </div>
                    </section>

                    <section id="preferensi" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="font-sans text-[20px]">Preferensi Petani</h2>
                                    <p class="text-[13px] text-[#A89880] mt-1">Atur notifikasi batch, format suhu, dan mode simpan data.</p>
                                </div>
                                <span class="text-[11px] text-[#5C4F42]">{{ preferences.syncLabel }}</span>
                            </div>

                            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="bahasa">Bahasa</label>
                                    <div class="relative">
                                        <select id="bahasa" v-model="preferences.language" @change="savePreferences" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] appearance-none">
                                            <option value="id">Indonesia</option>
                                            <option value="en">English</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" /></svg>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="notifikasi-batch">Notifikasi Batch</label>
                                    <div class="relative">
                                        <select id="notifikasi-batch" v-model="preferences.batchNotification" @change="savePreferences" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] appearance-none">
                                            <option value="active">Aktif</option>
                                            <option value="ready_only">Hanya Ready</option>
                                            <option value="inactive">Nonaktif</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" /></svg>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="format-suhu">Format Suhu</label>
                                    <div class="relative">
                                        <select id="format-suhu" v-model="preferences.temperature" @change="savePreferences" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] appearance-none">
                                            <option value="celsius">Celsius (°C)</option>
                                            <option value="fahrenheit">Fahrenheit (°F)</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" /></svg>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="mode-simpan">Mode Simpan Data</label>
                                    <div class="relative">
                                        <select id="mode-simpan" v-model="preferences.saveMode" @change="savePreferences" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#8B6355] appearance-none">
                                            <option value="auto">Otomatis</option>
                                            <option value="manual">Manual</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" /></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="perangkat" class="mt-10">
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
                                    class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-[#2E241C] bg-[#0F0D0B] px-5 py-4"
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

                    <section id="koneksi" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <h2 class="font-sans text-[20px]">Koneksi</h2>
                            <p class="text-[13px] text-[#A89880] mt-1">Atur perilaku saat koneksi buruk agar data tetap aman.</p>

                            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="rounded-xl border border-[#2E241C] bg-[#0F0D0B] px-4 py-3">
                                    <p class="text-[13px] text-[#A89880]">Sinkronisasi Otomatis</p>
                                    <p class="text-[14px] text-[#F5EFE6] mt-1">Aktif saat perangkat kembali online.</p>
                                </div>
                                <div class="rounded-xl border border-[#2E241C] bg-[#0F0D0B] px-4 py-3">
                                    <p class="text-[13px] text-[#A89880]">Mode Offline</p>
                                    <p class="text-[14px] text-[#F5EFE6] mt-1">Data log disimpan lokal sebelum dikirim.</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '../../components/farmer/Sidebar.vue';
import axios from 'axios';

const route = useRoute();
const currentRouteName = computed(() => route?.name ?? 'farmer.pengaturan');
const logoUrl = '/assets/logo-fix.png';

const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const profile = reactive({
    fullName: '',
    email: '',
    phone: '',
    location: '',
    coordinates: ''
});

const security = reactive({
    oldPassword: '',
    newPassword: '',
    confirmPassword: '',
    updatedAt: '-'
});

const preferences = reactive({
    language: 'id',
    batchNotification: 'active',
    temperature: 'celsius',
    saveMode: 'auto',
    syncLabel: 'Sinkronisasi aktif'
});

const isEditing = ref(false);
let profileSnapshot = null;

const devices = ref([]);
const devicesLabel = ref('');

const fetchProfileAndPreferences = async () => {
    try {
        const profileRes = await axios.get('/api/v1/farmer/profile');
        if (profileRes.data?.success && profileRes.data?.data?.profile) {
            const p = profileRes.data.data.profile;
            profile.fullName = p.full_name || '';
            profile.email = p.email || '';
            profile.phone = p.phone || '';
            profile.location = p.location || '';
            profile.coordinates = p.coordinates || '';
            
            if (p.updated_at) {
                security.updatedAt = new Date(p.updated_at).toLocaleDateString('id-ID');
            }
        }
    } catch (err) {
        console.error('Error fetching profile:', err);
    }

    try {
        const prefRes = await axios.get('/api/v1/farmer/preferences');
        if (prefRes.data?.success && prefRes.data?.data?.preferences) {
            const pr = prefRes.data.data.preferences;
            preferences.language = pr.language || 'id';
            preferences.batchNotification = pr.batch_notification || 'active';
            preferences.temperature = pr.temperature_unit || 'celsius';
            preferences.saveMode = pr.save_mode || 'auto';
        }
    } catch (err) {
        console.error('Error fetching preferences:', err);
    }

    try {
        const devicesRes = await axios.get('/api/v1/farmer/devices');
        if (devicesRes.data?.success && devicesRes.data?.data?.devices) {
            devices.value = devicesRes.data.data.devices.map(dev => {
                const userAgent = (dev.user_agent || '').toLowerCase();
                const icon = userAgent.includes('iphone') || userAgent.includes('android') || userAgent.includes('phone') ? '📱' : '💻';
                const dateStr = dev.last_activity_at ? new Date(dev.last_activity_at).toLocaleString('id-ID') : 'Belum tercatat';
                return {
                    id: dev.id,
                    icon,
                    title: dev.name === 'auth_token' ? 'Sesi Web Browser' : dev.name,
                    subtitle: `IP: ${dev.ip_address} · Aktif: ${dateStr}`,
                    statusLabel: dev.is_current_device ? 'Aktif' : 'Terkoneksi',
                    statusClass: dev.is_current_device ? 'border-[#4CAF7D] bg-[#1A3D2E] text-[#4CAF7D]' : 'border-[#5C4F42] bg-[#1C1813] text-[#A89880]'
                };
            });
            devicesLabel.value = `${devices.value.length} perangkat aktif`;
        }
    } catch (err) {
        console.error('Error fetching devices:', err);
    }
};

const startEdit = () => {
    profileSnapshot = { ...profile };
    isEditing.value = true;
};

const cancelEdit = () => {
    if (profileSnapshot) {
        profile.fullName = profileSnapshot.fullName;
        profile.email = profileSnapshot.email;
        profile.phone = profileSnapshot.phone;
        profile.location = profileSnapshot.location;
        profile.coordinates = profileSnapshot.coordinates;
        profileSnapshot = null;
    }
    isEditing.value = false;
};

const saveProfile = async () => {
    if (isLoading.value) return;
    isLoading.value = true;
    apiMessage.value = '';

    try {
        const response = await axios.patch('/api/v1/farmer/profile', {
            full_name: profile.fullName,
            phone: profile.phone,
            location: profile.location,
            coordinates: profile.coordinates
        });

        if (response.data?.success && response.data?.data?.profile) {
            const p = response.data.data.profile;
            profile.fullName = p.full_name || '';
            profile.phone = p.phone || '';
            profile.location = p.location || '';
            profile.coordinates = p.coordinates || '';
            
            apiSuccess.value = true;
            apiMessage.value = 'Profil berhasil diperbarui';
            isEditing.value = false;
            profileSnapshot = null;
        }
    } catch (err) {
        apiSuccess.value = false;
        const res = err?.response?.data;
        apiMessage.value = res?.message ?? 'Gagal memperbarui profil.';
    } finally {
        isLoading.value = false;
    }
};

const updatePassword = async () => {
    if (isLoading.value) return;
    isLoading.value = true;
    apiMessage.value = '';

    try {
        const response = await axios.patch('/api/v1/farmer/security/password', {
            old_password: security.oldPassword,
            new_password: security.newPassword,
            new_password_confirmation: security.confirmPassword
        });

        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Password berhasil diubah. Sesi lain mungkin akan berakhir.';
            security.oldPassword = '';
            security.newPassword = '';
            security.confirmPassword = '';
        }
    } catch (err) {
        apiSuccess.value = false;
        const res = err?.response?.data;
        apiMessage.value = res?.message ?? 'Gagal mengubah password.';
    } finally {
        isLoading.value = false;
    }
};

const savePreferences = async () => {
    preferences.syncLabel = 'Menyimpan...';
    try {
        const response = await axios.patch('/api/v1/farmer/preferences', {
            language: preferences.language,
            batch_notification: preferences.batchNotification,
            temperature_unit: preferences.temperature,
            save_mode: preferences.saveMode
        });
        if (response.data?.success) {
            preferences.syncLabel = 'Tersimpan';
            setTimeout(() => {
                preferences.syncLabel = 'Sinkronisasi aktif';
            }, 2000);
        }
    } catch (err) {
        preferences.syncLabel = 'Gagal menyimpan';
        console.error(err);
    }
};

const activeTab = ref('profil');
const sectionIds = ['profil', 'keamanan', 'preferensi', 'perangkat', 'koneksi'];
let observer = null;

const tabClass = (tab) => (tab === activeTab.value ? 'border-[#E8A838] text-[#FFD66B]' : '');

onMounted(() => {
    fetchProfileAndPreferences();

    observer = new IntersectionObserver(
        (entries) => {
            const visibleEntry = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

            if (visibleEntry?.target?.id) {
                activeTab.value = visibleEntry.target.id;
            }
        },
        {
            root: null,
            threshold: [0.25, 0.5, 0.75],
            rootMargin: '-18% 0px -60% 0px'
        }
    );

    sectionIds.forEach((id) => {
        const element = document.getElementById(id);

        if (element) {
            observer.observe(element);
        }
    });
});

onBeforeUnmount(() => {
    observer?.disconnect();
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
