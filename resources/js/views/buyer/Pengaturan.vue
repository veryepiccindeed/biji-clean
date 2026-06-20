<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-buyer font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(184, 144, 42, 0.20), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.10), transparent 60%);"></div>
            
            <Sidebar :current-route-name="currentRouteName" />

            <main class="flex-1 px-4 sm:px-6 lg:px-12 py-6 pt-24 lg:pt-12 lg:py-12 relative z-10 overflow-y-auto">
                <header class="flex justify-between items-start mb-10 lg:mb-16 gap-6">
                    <div>
                        <h1 class="font-sans text-2xl sm:text-[28px] font-bold text-[#F5EFE6]">Pengaturan Pembeli</h1>
                        <p class="mt-2 max-w-2xl text-[13px] sm:text-[15px] text-[#A89880]">Kelola profil, preferensi pengadaan, dan koneksi blockchain.</p>
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

                    <div class="mt-8 sticky top-0 z-30 backdrop-blur-md">
                        <nav class="flex flex-wrap gap-6 text-[13px] font-medium border-b border-[#2E241C] px-1" data-scrollspy>
                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#D4AF5A] hover:border-[#B8902A]/60"
                                :class="tabClass('profil')"
                                data-tab
                                href="#profil"
                            >
                                Profil
                            </a>

                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#D4AF5A] hover:border-[#B8902A]/60"
                                :class="tabClass('keamanan')"
                                data-tab
                                href="#keamanan"
                            >
                                Keamanan
                            </a>

                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#D4AF5A] hover:border-[#B8902A]/60"
                                :class="tabClass('preferensi')"
                                data-tab
                                href="#preferensi"
                            >
                                Preferensi
                            </a>

                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#D4AF5A] hover:border-[#B8902A]/60"
                                :class="tabClass('perangkat')"
                                data-tab
                                href="#perangkat"
                            >
                                Perangkat
                            </a>

                            <a
                                class="py-4 text-[#A89880] border-b-2 border-transparent transition-all duration-200 hover:text-[#D4AF5A] hover:border-[#B8902A]/60"
                                :class="tabClass('blockchain')"
                                data-tab
                                href="#blockchain"
                            >
                                Blockchain
                            </a>
                        </nav>
                    </div>

                    <section id="profil" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h2 class="font-sans text-[20px]">Profil Pembeli</h2>
                                    <p class="text-[13px] text-[#A89880] mt-1">Perbarui identitas dan informasi pengiriman Anda.</p>
                                </div>
                                <span class="text-[12px] text-[#A89880] font-medium">Kelengkapan Profil: {{ profile.completionRate }}%</span>
                            </div>

                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div class="md:col-span-2">
                                    <label class="text-[13px] text-[#A89880]" for="nama-lengkap">Nama Lengkap</label>
                                    <input id="nama-lengkap" v-model="profile.fullName" :readonly="!isEditing" :class="{ editing: isEditing }" type="text" class="profile-input mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A]" />

                                    <div class="mt-4">
                                        <label class="text-[13px] text-[#A89880]" for="email">Email</label>
                                        <input id="email" v-model="profile.email" :readonly="true" type="email" class="profile-input mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A] opacity-60 cursor-not-allowed" />
                                    </div>
                                </div>

                                <div class="md:col-span-1 flex flex-col gap-5">
                                    <div>
                                        <label class="text-[13px] text-[#A89880]" for="telepon">Nomor Telepon</label>
                                        <input id="telepon" v-model="profile.phone" :readonly="!isEditing" :class="{ editing: isEditing }" type="text" class="profile-input mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A]" placeholder="Contoh: +62 812-3456-7890" />
                                    </div>

                                    <div>
                                        <label class="text-[13px] text-[#A89880]" for="perusahaan">Perusahaan</label>
                                        <input id="perusahaan" v-model="profile.company" :readonly="!isEditing" :class="{ editing: isEditing }" type="text" class="profile-input mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A]" />
                                    </div>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="text-[13px] text-[#A89880]" for="business_id">NPWP / Business ID</label>
                                    <input id="business_id" v-model="profile.businessId" :readonly="!isEditing" :class="{ editing: isEditing }" type="text" class="profile-input mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A]" placeholder="Masukkan NPWP atau Nomor Registrasi Bisnis" />
                                    <p v-if="profile.businessIdType" class="text-[11px] text-[#A89880] mt-2 font-mono">Tipe Identifikasi: <span class="text-[#D4AF5A]">{{ profile.businessIdType }}</span></p>
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
                                    class="w-11 h-11 rounded-xl border border-[#4A3728] bg-[#2A2118] hover:bg-[#33271D] transition flex items-center justify-center text-[#B8902A]"
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
                            </div>

                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="password-lama">Password Lama</label>
                                    <input id="password-lama" v-model="security.oldPassword" type="password" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A]" />
                                </div>
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="password-baru">Password Baru</label>
                                    <input id="password-baru" v-model="security.newPassword" type="password" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A]" />
                                </div>
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="password-konfirmasi">Konfirmasi Password</label>
                                    <input id="password-konfirmasi" v-model="security.confirmPassword" type="password" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A]" />
                                </div>
                            </div>

                            <div class="mt-8 flex items-center justify-end">
                                <button @click="updatePassword" :disabled="isLoading" class="px-5 py-2 rounded-lg text-[13px] text-[#F5EFE6] border border-[#4A3728]" style="background-color: var(--theme-primary);">Update Password</button>
                            </div>
                        </div>
                    </section>

                    <section id="preferensi" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <h2 class="font-sans text-[20px]">Preferensi Pengadaan</h2>
                            <p class="text-[13px] text-[#A89880] mt-1">Atur mata uang, notifikasi transaksi, dan email pengingat pengadaan Anda.</p>

                            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="mata-uang">Mata Uang Utama</label>
                                    <div class="relative">
                                        <select id="mata-uang" v-model="preferences.currency" @change="savePreferences" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A] appearance-none">
                                            <option value="IDR">IDR (Rp)</option>
                                            <option value="USD">USD ($)</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" /></svg>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="bahasa-pembeli">Bahasa Sistem</label>
                                    <div class="relative">
                                        <select id="bahasa-pembeli" v-model="preferences.language" @change="savePreferences" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A] appearance-none">
                                            <option value="id">Bahasa Indonesia</option>
                                            <option value="en">English</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" /></svg>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[13px] text-[#A89880]" for="pengingat-email-jam">Frekuensi Pengingat Email</label>
                                    <div class="relative">
                                        <select id="pengingat-email-jam" v-model="preferences.email_reminder_hours" @change="savePreferences" class="mt-2 w-full rounded-lg bg-[#0F0D0B] border border-[#4A3728] px-4 py-3 text-[15px] text-[#F5EFE6] focus:outline-none focus:border-[#B8902A] appearance-none">
                                            <option :value="1">Setiap 1 Jam</option>
                                            <option :value="2">Setiap 2 Jam</option>
                                            <option :value="4">Setiap 4 Jam</option>
                                            <option :value="8">Setiap 8 Jam</option>
                                            <option :value="12">Setiap 12 Jam</option>
                                            <option :value="24">Setiap 24 Jam</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center translate-y-[5px] text-[#A89880]">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" /></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 border-t border-[#2E241C] pt-6 space-y-4">
                                <h3 class="text-[14px] text-[#A89880] uppercase tracking-widest font-bold">Pengaturan Notifikasi</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <label class="flex items-center gap-3 cursor-pointer group">
                                        <input type="checkbox" v-model="preferences.notification_order_status" @change="savePreferences" class="rounded border-[#4A3728] bg-[#0F0D0B] text-[#B8902A] focus:ring-[#B8902A] w-5 h-5 transition-all" />
                                        <div>
                                            <p class="text-[14px] font-bold text-[#F5EFE6] group-hover:text-[#D4AF5A] transition-colors">Notifikasi Status Pesanan</p>
                                            <p class="text-[12px] text-[#A89880]">Terima pembaruan ketika status pesanan berubah</p>
                                        </div>
                                    </label>

                                    <label class="flex items-center gap-3 cursor-pointer group">
                                        <input type="checkbox" v-model="preferences.notification_payment" @change="savePreferences" class="rounded border-[#4A3728] bg-[#0F0D0B] text-[#B8902A] focus:ring-[#B8902A] w-5 h-5 transition-all" />
                                        <div>
                                            <p class="text-[14px] font-bold text-[#F5EFE6] group-hover:text-[#D4AF5A] transition-colors">Notifikasi Status Pembayaran</p>
                                            <p class="text-[12px] text-[#A89880]">Terima info verifikasi atau masalah pada pembayaran</p>
                                        </div>
                                    </label>

                                    <label class="flex items-center gap-3 cursor-pointer group">
                                        <input type="checkbox" v-model="preferences.notification_shipment" @change="savePreferences" class="rounded border-[#4A3728] bg-[#0F0D0B] text-[#B8902A] focus:ring-[#B8902A] w-5 h-5 transition-all" />
                                        <div>
                                            <p class="text-[14px] font-bold text-[#F5EFE6] group-hover:text-[#D4AF5A] transition-colors">Notifikasi Status Pengiriman</p>
                                            <p class="text-[12px] text-[#A89880]">Dapatkan notifikasi transit kargo secara real-time</p>
                                        </div>
                                    </label>

                                    <label class="flex items-center gap-3 cursor-pointer group">
                                        <input type="checkbox" v-model="preferences.notification_catalog_update" @change="savePreferences" class="rounded border-[#4A3728] bg-[#0F0D0B] text-[#B8902A] focus:ring-[#B8902A] w-5 h-5 transition-all" />
                                        <div>
                                            <p class="text-[14px] font-bold text-[#F5EFE6] group-hover:text-[#D4AF5A] transition-colors">Notifikasi Katalog Baru</p>
                                            <p class="text-[12px] text-[#A89880]">Terima info instan jika ada batch baru masuk ke marketplace</p>
                                        </div>
                                    </label>

                                    <label class="flex items-center gap-3 cursor-pointer group md:col-span-2">
                                        <input type="checkbox" v-model="preferences.email_reminder" @change="savePreferences" class="rounded border-[#4A3728] bg-[#0F0D0B] text-[#B8902A] focus:ring-[#B8902A] w-5 h-5 transition-all" />
                                        <div>
                                            <p class="text-[14px] font-bold text-[#F5EFE6] group-hover:text-[#D4AF5A] transition-colors">Kirim Pengingat Melalui Email</p>
                                            <p class="text-[12px] text-[#A89880]">Kirim pengingat pembayaran ke inbox email terdaftar</p>
                                        </div>
                                    </label>
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

                    <section id="blockchain" class="mt-10">
                        <div class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-8">
                            <h2 class="font-sans text-[20px]">Koneksi Blockchain</h2>
                            <p class="text-[13px] text-[#A89880] mt-1">Alamat wallet untuk menerima sertifikat digital (Asset Ownership).</p>

                            <div class="mt-6">
                                <div v-if="isWalletConnected" class="rounded-xl border border-[#2E241C] bg-[#0F0D0B] px-6 py-4 flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600"></div>
                                        <div>
                                            <p class="text-[14px] font-mono font-bold text-[#F5EFE6]">{{ walletAddress }}</p>
                                            <p class="text-[11px] text-[#5C4F42] uppercase tracking-widest">MetaMask · Polygon Amoy</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-4">
                                        <button @click="disconnectWallet" class="text-[12px] text-[#D94F4F] font-bold hover:underline">Putuskan</button>
                                    </div>
                                </div>
                                <div v-else class="rounded-xl border border-dashed border-[#4A3728] bg-[#0F0D0B] px-6 py-6 flex flex-col items-center gap-3 justify-center">
                                    <p class="text-[13px] text-[#A89880]">Belum ada wallet blockchain yang terhubung.</p>
                                    <button @click="connectWallet" class="px-5 py-2 rounded-lg text-[13px] text-[#F5EFE6] border border-[#B8902A] bg-[#2D2210]/30 hover:bg-[#B8902A] transition">Hubungkan MetaMask</button>
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
import { useRoute, useRouter } from 'vue-router';
import Sidebar from '../../components/buyer/Sidebar.vue';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const currentRouteName = computed(() => route?.name ?? 'buyer.settings');
const logoUrl = '/assets/logo-fix.png';

const isLoading = ref(false);
const apiMessage = ref('');
const apiSuccess = ref(false);

const profile = reactive({
    fullName: '',
    email: '',
    phone: '',
    company: '',
    businessId: '',
    businessIdType: '',
    completionRate: 0
});

const security = reactive({
    oldPassword: '',
    newPassword: '',
    confirmPassword: ''
});

const preferences = reactive({
    language: 'id',
    currency: 'IDR',
    notification_order_status: true,
    notification_payment: true,
    notification_shipment: true,
    notification_catalog_update: false,
    email_reminder: true,
    email_reminder_hours: 2
});

const isEditing = ref(false);
let profileSnapshot = null;

const startEdit = () => {
    profileSnapshot = { ...profile };
    isEditing.value = true;
};

const cancelEdit = () => {
    if (profileSnapshot) {
        Object.assign(profile, profileSnapshot);
        profileSnapshot = null;
    }
    isEditing.value = false;
};

const fetchProfileAndSettings = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/api/v1/buyer/profile');
        if (data.success && data.data) {
            const p = data.data.profile || {};
            profile.fullName = p.name || '';
            profile.email = p.email || '';
            profile.phone = p.phone || '';
            profile.company = p.company_name || '';
            profile.businessId = p.business_id || '';
            profile.businessIdType = p.business_id_type || '';
            profile.completionRate = p.profile_completion || 0;
        }
    } catch (err) {
        console.error('Failed to load buyer profile:', err);
    }

    try {
        const { data } = await axios.get('/api/v1/buyer/preferences');
        if (data.success && data.data?.preferences) {
            const pref = data.data.preferences;
            preferences.language = pref.language || 'id';
            preferences.currency = pref.currency || 'IDR';
            preferences.notification_order_status = !!pref.notification_order_status;
            preferences.notification_payment = !!pref.notification_payment;
            preferences.notification_shipment = !!pref.notification_shipment;
            preferences.notification_catalog_update = !!pref.notification_catalog_update;
            preferences.email_reminder = !!pref.email_reminder;
            preferences.email_reminder_hours = Number(pref.email_reminder_hours || 2);
        }
    } catch (err) {
        console.error('Failed to load buyer preferences:', err);
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
    } finally {
        isLoading.value = false;
    }
};

const saveProfile = async () => {
    isLoading.value = true;
    apiMessage.value = '';
    
    // Quick validation for phone format matching regex: /^\+\d{1,4}\s\d{3,4}-\d{3,4}-\d{3,5}$/
    const phoneRegex = /^\+\d{1,4}\s\d{3,4}-\d{3,4}-\d{3,5}$/;
    if (profile.phone && !phoneRegex.test(profile.phone)) {
        apiSuccess.value = false;
        apiMessage.value = 'Nomor telepon tidak valid. Gunakan format seperti +62 812-3456-7890';
        isLoading.value = false;
        return;
    }

    try {
        const response = await axios.patch('/api/v1/buyer/profile', {
            name: profile.fullName,
            phone: profile.phone,
            company_name: profile.company,
            business_id: profile.businessId
        });
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Profil berhasil diperbarui!';
            isEditing.value = false;
            profileSnapshot = null;
            await fetchProfileAndSettings();
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal memperbarui profil.';
    } finally {
        isLoading.value = false;
    }
};

const updatePassword = async () => {
    if (security.newPassword !== security.confirmPassword) {
        apiSuccess.value = false;
        apiMessage.value = 'Konfirmasi password baru tidak cocok.';
        return;
    }
    isLoading.value = true;
    apiMessage.value = '';
    try {
        const response = await axios.patch('/api/v1/buyer/security/password', {
            current_password: security.oldPassword,
            new_password: security.newPassword,
            new_password_confirmation: security.confirmPassword
        });
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Password berhasil diperbarui! Sesi Anda berakhir. Mengalihkan...';
            security.oldPassword = '';
            security.newPassword = '';
            security.confirmPassword = '';
            // Session is invalidated on password update, clear token and redirect to login page
            setTimeout(() => {
                localStorage.clear();
                router.push({ name: 'auth.login-lagi' });
            }, 2000);
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal memperbarui password.';
    } finally {
        isLoading.value = false;
    }
};

const savePreferences = async () => {
    isLoading.value = true;
    apiMessage.value = '';
    try {
        const response = await axios.patch('/api/v1/buyer/preferences', {
            language: preferences.language,
            currency: preferences.currency,
            notification_order_status: preferences.notification_order_status,
            notification_payment: preferences.notification_payment,
            notification_shipment: preferences.notification_shipment,
            notification_catalog_update: preferences.notification_catalog_update,
            email_reminder: preferences.email_reminder,
            email_reminder_hours: Number(preferences.email_reminder_hours)
        });
        if (response.data?.success) {
            apiSuccess.value = true;
            apiMessage.value = 'Preferensi berhasil diperbarui!';
            setTimeout(() => {
                if (apiMessage.value === 'Preferensi berhasil diperbarui!') {
                    apiMessage.value = '';
                }
            }, 3000);
        }
    } catch (err) {
        apiSuccess.value = false;
        apiMessage.value = err.response?.data?.message ?? 'Gagal memperbarui preferensi.';
    } finally {
        isLoading.value = false;
    }
};

const devices = ref([]);
const devicesLabel = ref('');

// Simulated wallet addresses for blockchain section
const isWalletConnected = ref(true);
const walletAddress = ref('0x71C2E9A3B50C16458569300557eEd96E4321A9D0');
const disconnectWallet = () => {
    isWalletConnected.value = false;
    walletAddress.value = '';
};
const connectWallet = () => {
    isWalletConnected.value = true;
    walletAddress.value = '0x71C2E9A3B50C16458569300557eEd96E4321A9D0';
};

const activeTab = ref('profil');
const sectionIds = ['profil', 'keamanan', 'preferensi', 'perangkat', 'blockchain'];
let observer = null;

const tabClass = (tab) => (tab === activeTab.value ? 'border-[#B8902A] text-[#D4AF5A]' : '');

onMounted(() => {
    fetchProfileAndSettings();

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
