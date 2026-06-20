<template>
    <div class="min-h-screen bg-[#0F0D0B] text-[#F5EFE6] theme-exporter font-sans" style="font-family: 'DM Sans', sans-serif;">
        <div class="relative min-h-screen flex flex-col lg:flex-row">
            <div class="absolute inset-0 opacity-60 pointer-events-none" style="background: radial-gradient(1200px 520px at 18% 8%, rgba(75, 56, 50, 0.35), transparent 60%), radial-gradient(900px 520px at 82% 28%, rgba(8, 194, 70, 0.12), transparent 60%);"></div>

            <main class="flex-1 px-4 sm:px-6 lg:px-10 py-6 lg:py-10 relative z-10 overflow-y-auto h-screen">
                <header class="flex flex-col md:flex-row md:items-start justify-between gap-6 mb-8 mt-4 lg:mt-0">
                    <div>
                        <div class="mb-6">
                            <button @click="$router.back()" class="text-[#A89880] hover:text-[#F5EFE6] transition flex items-center gap-2 text-sm bg-[#1C1813]/50 px-3 py-1.5 rounded-full border border-[#2E241C] w-max">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                Kembali
                            </button>
                        </div>
                        <p class="text-[11px] text-[#A89880] uppercase tracking-wider mb-1">{{ mockData.headerLabel }}</p>
                        <h1 class="font-mono text-[32px] font-bold text-[#F5EFE6] tracking-tight">{{ mockData.batchName }}</h1>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            id="open-acq"
                            type="button"
                            class="px-6 py-3 rounded-xl text-[14px] font-bold text-[#F5EFE6] border border-[#8B6355] bg-[#4B3832] hover:bg-[#5C453E] hover:shadow-[0_0_20px_rgba(75,56,50,0.6)] transition-all flex items-center gap-2"
                            @click="openAcquisition"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {{ mockData.acquisitionButtonLabel }}
                        </button>
                    </div>
                </header>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-8 space-y-6">
                        <section class="bg-[#1C1813] border border-[#E8A838]/40 rounded-2xl relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-[#E8A838]"></div>

                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-[18px] font-bold flex items-center gap-2 font-sans">
                                        {{ mockData.healthReport.title }}
                                    </h2>
                                    <span class="px-3 py-1 rounded-full border border-[#E8A838]/30 bg-[#2D2210] text-[#E8A838] text-[12px] font-bold flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-[#E8A838] animate-pulse"></span>
                                        {{ mockData.healthReport.badge }}
                                    </span>
                                </div>

                                <p class="text-[13px] text-[#A89880] mb-4">{{ mockData.healthReport.summary }}</p>

                                <div class="p-4 bg-[#14110D] border border-[#2E241C] rounded-xl flex items-start gap-4">
                                    <span class="text-2xl mt-1">⚠️</span>
                                    <div>
                                        <h4 class="text-[14px] font-bold text-[#E8A838] font-sans">{{ mockData.healthReport.alertTitle }}</h4>
                                        <p class="text-[13px] text-[#A89880] mt-1" v-html="mockData.healthReport.alertBody"></p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6">
                            <div class="flex justify-between items-end mb-6">
                                <h2 class="text-[18px] font-bold flex items-center gap-2 font-sans">
                                    📊 Histori Suhu &amp; Kelembapan
                                </h2>
                                <button id="open-log-full" type="button" class="text-[12px] text-[#A89880] hover:text-[#F5EFE6] border border-[#2E241C] px-3 py-1 rounded-md bg-[#0F0D0B] transition" @click="openLogModal">Log Lengkap ↗</button>
                            </div>

                            <div class="mb-4 p-4 rounded-lg bg-[#0F0D0B] border border-[#2E241C] text-[13px] text-[#A89880]">
                                <strong class="text-[#E8A838]">Peringatan:</strong>
                                {{ mockData.logDisclaimer }}
                            </div>

                            <div class="w-full h-[220px] border border-dashed border-[#4A3728] rounded-xl mb-6 bg-[#0F0D0B] relative">
                                <Line :data="smallChartData" :options="chartOptions" ref="smallChartRef" class="absolute inset-0" />
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-[#2E241C]">
                                <table class="w-full text-left text-[12px]">
                                    <thead class="bg-[#0F0D0B] text-[#A89880]">
                                        <tr>
                                            <th class="px-4 py-3 font-medium">Tanggal</th>
                                            <th class="px-4 py-3 font-medium">Suhu Rata-Rata</th>
                                            <th class="px-4 py-3 font-medium">Suhu Maks.</th>
                                            <th class="px-4 py-3 font-medium">Kelembapan (%)</th>
                                            <th class="px-4 py-3 font-medium">Aktivitas Petani</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#2E241C] text-[#F5EFE6]">
                                        <tr
                                            v-for="row in logSummaryRows"
                                            :key="row.id"
                                            :class="rowHighlightClass(row)"
                                            class="hover:bg-[#14110D]"
                                        >
                                            <td :class="rowLeftBorderClass(row)" class="px-4 py-3">{{ row.date }}</td>
                                            <td class="px-4 py-3 font-mono">{{ row.avgTemp }}</td>
                                            <td :class="rowMaxClass(row)" class="px-4 py-3 font-mono">{{ row.maxTemp }}</td>
                                            <td class="px-4 py-3 font-mono">{{ row.humidity }}</td>
                                            <td class="px-4 py-3">{{ row.note }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <transition name="fade">
                            <div v-show="isLogModalOpen" id="log-full-modal" class="fixed inset-0 z-50 flex items-center justify-center p-6">
                                <div id="log-full-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeLogModal"></div>
                                <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto bg-[#0F0D0B] border border-[#2E241C] rounded-2xl p-6 shadow-lg z-60">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h3 class="text-[18px] font-bold font-sans text-[#F5EFE6]">Histori Suhu &amp; Kelembapan</h3>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button id="close-log-full" aria-label="Tutup" class="px-3 py-2 rounded-md text-[12px] border border-[#2E241C] bg-[#1C1813] text-[#A89880] hover:bg-[#2E241C]" @click="closeLogModal">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                    <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 mb-4">
                                        <div class="col-span-1">
                                                <div class="w-full h-64 bg-[#07100a] border border-[#2E241C] rounded-lg relative overflow-hidden">
                                                <Line :data="overlayChartData" :options="chartOptions" ref="overlayChartRef" class="absolute inset-0" />
                                                <div id="logFullChartFallback" v-show="!overlayChartReady" class="absolute inset-0 flex items-center justify-center text-[#5C4F42] pointer-events-none">Chart.js placeholder</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto rounded-lg border border-[#2E241C]">
                                        <table class="w-full text-left text-[12px]">
                                            <thead class="bg-[#0F0D0B] text-[#A89880]">
                                                <tr>
                                                    <th class="px-4 py-3 font-medium">Tanggal</th>
                                                    <th class="px-4 py-3 font-medium">Jam</th>
                                                    <th class="px-4 py-3 font-medium">Suhu (Avg °C)</th>
                                                    <th class="px-4 py-3 font-medium">Suhu (Max °C)</th>
                                                    <th class="px-4 py-3 font-medium">Kelembaban (%)</th>
                                                    <th class="px-4 py-3 font-medium">Catatan</th>
                                                </tr>
                                            </thead>
                                            <tbody id="log-full-tbody" class="divide-y divide-[#2E241C] text-[#F5EFE6]">
                                                <tr
                                                    v-for="row in logModalRows"
                                                    :key="row.id"
                                                    :class="rowHighlightClass(row)"
                                                    class="hover:bg-[#14110D]"
                                                >
                                                    <td :class="rowLeftBorderClass(row)" class="px-4 py-3">{{ row.date }}</td>
                                                    <td class="px-4 py-3">{{ row.time }}</td>
                                                    <td class="px-4 py-3 font-mono">{{ row.avgTemp }}</td>
                                                    <td :class="rowMaxClass(row)" class="px-4 py-3 font-mono">{{ row.maxTemp }}</td>
                                                    <td class="px-4 py-3">{{ row.humidity }}</td>
                                                    <td class="px-4 py-3">{{ row.note }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </transition>

                        <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6">
                            <h2 class="text-[18px] font-bold mb-5 flex items-center gap-2 font-sans">
                                ⛰️ Spesifikasi Lahan &amp; Panen
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <div class="bg-[#0F0D0B] p-5 rounded-xl border border-[#2E241C]">
                                        <p class="text-[12px] text-[#A89880]">Elevasi Tanam</p>
                                        <h3 class="text-[36px] font-sans font-bold text-[#F5EFE6] leading-tight">{{ mockData.land.elevation }} <span class="text-[16px] text-[#A89880] font-sans font-normal">mdpl</span></h3>
                                    </div>
                                    <div class="space-y-3 mt-4 text-[13px]">
                                        <div class="flex justify-between border-b border-[#2E241C] pb-2">
                                            <span class="text-[#A89880]">Varietas</span>
                                            <span class="font-bold">{{ mockData.land.varietas }}</span>
                                        </div>
                                        <div class="flex justify-between border-b border-[#2E241C] pb-2">
                                            <span class="text-[#A89880]">Proses Pascapanen</span>
                                            <span class="font-bold">{{ mockData.land.process }}</span>
                                        </div>
                                        <div class="flex justify-between border-b border-[#2E241C] pb-2">
                                            <span class="text-[#A89880]">Tanggal Panen</span>
                                            <span>{{ mockData.land.harvestDate }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col h-full">
                                                <div class="flex-1 bg-[#0F0D0B] border border-[#2E241C] rounded-xl relative overflow-hidden group cursor-pointer" @click="openMapOverlay">
                                                    <div ref="mapRoot" class="absolute inset-0"></div>

                                                     <div class="absolute inset-0 bg-[#1C1813] opacity-50 pointer-events-none" style="background-image: repeating-linear-gradient(45deg, #2E241C 25%, transparent 25%, transparent 75%, #2E241C 75%, #2E241C), repeating-linear-gradient(45deg, #2E241C 25%, #1C1813 25%, #1C1813 75%, #2E241C 75%, #2E241C); background-size: 20px 20px;"></div>

                                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                                <span class="text-3xl drop-shadow-[0_5px_5px_rgba(0,0,0,0.5)] transform group-hover:scale-110 transition duration-300">📍</span>
                                                <div class="mt-2 flex items-center gap-2 bg-[#0F0D0B]/80 backdrop-blur px-3 py-1.5 rounded-lg border border-[#2E241C]">
                                                    <span class="w-2 h-2 bg-[#08C246] rounded-full"></span>
                                                    <span class="font-mono text-[10px] text-[#F5EFE6]">{{ mockData.land.coordinates }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    <p class="text-[11px] text-[#A89880] mt-2 italic text-center">{{ mockData.land.mapNote }}</p>
                                </div>
                            </div>
                        </section>

                        <BannerPreview />
                    </div>

                    <div class="lg:col-span-4 space-y-6">
                        <section id="price-detail-card" class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="text-[16px] font-bold mb-1 flex items-center gap-2 font-sans">💰 Detail Harga</h2>
                                </div>
                            </div>

                            <div class="mt-2 space-y-4">
                                <div class="bg-[#0F0D0B] p-4 rounded-xl border border-[#4A3728] mb-4">
                                    <p class="text-[12px] text-[#A89880] mb-1">Harga Buka Saat Ini</p>
                                    <p id="asking-price-text" class="font-sans text-[24px] font-bold text-[#F5EFE6]">{{ formattedPrice }} <span class="text-[14px] font-sans font-normal text-[#A89880]">/kg</span></p>
                                </div>
                            </div>
                        </section>

                        <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6 top-8">
                            <h2 class="text-[16px] font-bold mb-4 flex items-center gap-2 font-sans">
                                📦 Detail Volume Fisik
                            </h2>

                            <div class="mb-3 p-3 bg-[#0F0D0B] border border-[#2E241C] rounded-lg text-[13px]">
                                <div class="flex items-center justify-between">
                                    <div class="text-[12px] text-[#A89880]">Total Batch (Dijual)</div>
                                    <div class="font-bold text-[#F5EFE6]" id="total-batch-kg">{{ mockData.volume.totalKg }} kg</div>
                                </div>
                                <div class="flex items-center justify-between mt-2">
                                    <div class="text-[12px] text-[#A89880]">Terjual</div>
                                    <div class="font-bold text-[#F5EFE6]" id="sold-batch-kg">{{ mockData.volume.soldKg }} kg</div>
                                </div>
                                <div class="flex items-center justify-between mt-2">
                                    <div class="text-[12px] text-[#A89880]">Tersisa</div>
                                    <div class="font-bold text-[#F5EFE6]" id="remaining-batch-kg">{{ remainingKg }} kg</div>
                                </div>
                            </div>

                            <div class="flex items-baseline gap-2 mb-1">
                                <span id="volume-kg-value" class="text-[40px] font-sans font-bold text-[#F5EFE6] leading-none">{{ remainingKg }}</span>
                                <span class="text-[16px] text-[#A89880]">kg</span>
                            </div>
                            <p class="text-[13px] text-[#A89880] mb-5">{{ mockData.volume.estimateNote }}</p>

                            <hr class="border-[#2E241C] mb-4">

                            <p class="text-[11px] text-[#A89880] uppercase tracking-wider mb-2">ID Batch</p>
                            <div class="font-mono text-[14px] p-3 bg-[#0F0D0B] border border-[#4A3728] rounded-lg text-center tracking-widest text-[#E8A838] mb-2 shadow-inner">
                                {{ mockData.volume.batchIdRange }}
                            </div>
                            <p class="text-[11px] text-[#5C4F42] leading-relaxed">
                                {{ mockData.volume.note }}
                            </p>
                        </section>

                        <section class="bg-[#1C1813] border border-[#2E241C] rounded-2xl p-6">
                            <h2 class="text-[14px] font-bold text-[#A89880] mb-4 uppercase tracking-wider font-sans">Mitra Petani</h2>

                            <div class="flex items-center gap-4 mb-4">
                                <img :src="mockData.farmer.avatar" :alt="mockData.farmer.name" class="w-14 h-14 rounded-full border-2 border-[#4A3728]">
                                <div>
                                    <h3 class="text-[16px] font-bold text-[#F5EFE6] font-sans">{{ mockData.farmer.name }}</h3>
                                    <p class="text-[12px] text-[#A89880] mt-0.5">{{ mockData.farmer.location }}</p>
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-[#0F0D0B] border border-[#2E241C] flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] text-[#A89880] mb-0.5">Reputasi Platform</p>
                                    <div class="flex items-center gap-1">
                                        <span class="text-[#E8A838] text-[12px]">★</span>
                                        <span class="text-[13px] font-bold">{{ mockData.farmer.rating }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-[11px] text-[#A89880] mb-0.5">Histori Transaksi</p>
                                    <p class="text-[13px] font-bold">{{ mockData.farmer.history }}</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </main>
        </div>

        <!-- Fullscreen map overlay -->
        <transition name="fade">
            <div v-if="isMapOverlayOpen" class="fixed inset-0 z-60 flex items-center justify-center p-6">
                <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="closeMapOverlay"></div>
                <div class="relative w-full max-w-6xl h-[85vh] bg-[#0F0D0B] border border-[#2E241C] rounded-2xl p-4 z-70">
                    <button aria-label="Tutup peta" class="absolute top-4 right-4 px-3 py-2 rounded-md bg-[#1C1813] border border-[#2E241C] text-[#A89880]" @click="closeMapOverlay">✕</button>
                    <div ref="overlayMapRoot" class="w-full h-full rounded-lg overflow-hidden"></div>
                </div>
            </div>
        </transition>

        <transition name="slide">
            <div
                v-show="isChatOpen"
                id="chat-panel"
                class="fixed inset-y-0 right-0 w-full max-w-md lg:max-w-lg transform transition-transform duration-300 z-50 flex flex-col"
                style="background:var(--color-bg-card); border-left:1px solid var(--color-border-light);"
            >
                <div class="flex items-center justify-between p-4" style="border-bottom:1px solid var(--color-border-light);">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#0F0D0B] border border-[#2E241C] flex items-center justify-center text-sm">{{ mockData.chat.initials }}</div>
                        <div>
                            <div class="text-sm font-bold text-[#F5EFE6]">Chat: {{ mockData.chat.name }}</div>
                            <div class="text-xs text-[#A89880]">{{ mockData.chat.status }}</div>
                        </div>
                    </div>
                    <button id="close-chat" aria-label="Tutup chat" class="p-2 rounded hover:bg-[#2E241C] text-[#A89880]" @click="closeChat">✕</button>
                </div>

                <div id="chat-messages" class="p-4 flex-1 overflow-y-auto text-[#F5EFE6]">
                    <div v-for="message in chatMessages" :key="message.id" class="mb-2 text-right">
                        <div v-if="message.type === 'text'" class="inline-block bg-[#4B3832] text-[#F5EFE6] px-3 py-2 rounded-lg">{{ message.text }}</div>
                        <div v-else class="inline-block bg-[var(--color-role-buyer-bg)] text-[#F5EFE6] px-3 py-2 rounded-lg text-right">
                            <div class="font-bold">Penawaran: {{ message.priceText }} / Kg</div>
                            <div class="text-[12px] text-[#A89880]">Berat dibeli: {{ message.qty }} kg — Total: {{ message.totalText }}</div>
                        </div>
                    </div>
                </div>

                <button
                    id="open-bid-floating"
                    aria-label="Buka Ajukan Penawaran"
                    class="absolute bottom-20 right-6 z-50 px-3 py-1.5 rounded-full bg-[#E8A838] text-black text-sm font-semibold shadow-lg hover:bg-[#d9b93a] focus:outline-none inline-flex items-center gap-2"
                    v-show="isChatOpen && !isBidPanelOpen"
                    @click="showBidPanel"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M21 10v4a2 2 0 01-2 2h-2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" /><path d="M7 6H5a2 2 0 00-2 2v4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" /><circle cx="12" cy="12" r="3" fill="currentColor" /><path d="M12 8v1" stroke="#000" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <span>Ajukan Penawaran</span>
                </button>

                <div id="bid-panel" :class="{ hidden: !isBidPanelOpen }" class="p-4 border-t border-[#2E241C] bg-transparent relative">
                    <div class="relative">
                        <div class="text-[13px] text-[#A89880] mb-4 font-semibold">Ajukan Penawaran Harga ke Petani</div>
                        <button id="hide-bid" aria-label="Tutup penawaran" class="absolute right-0 top-0 text-xs text-[#A89880] px-2 py-1 hover:bg-[#2E241C] rounded inline-flex items-center gap-1" @click="hideBidPanel">
                            <span>Tutup</span>
                            <svg class="w-3 h-3 text-[#A89880]" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-col lg:flex-row gap-4">
                        <div class="flex-1">
                            <div class="mb-3">
                                <label for="bid-input" class="text-[12px] text-[#A89880] block mb-2">Harga / Kg (Rp)</label>
                                <input
                                    id="bid-input"
                                    v-model.number="bidPrice"
                                    type="number"
                                    min="10000"
                                    step="1000"
                                    class="bg-[#0F0D0B] border border-[#4A3728] rounded-lg px-3 py-2 text-[#F5EFE6] w-40"
                                    @change="clampBidPrice"
                                >
                            </div>

                            <div>
                                <label for="bid-qty" class="text-[12px] text-[#A89880] block mb-2">Berat yang ingin dibeli (kg)</label>
                                <input
                                    id="bid-qty"
                                    v-model.number="bidQty"
                                    type="number"
                                    min="100"
                                    step="1"
                                    class="bg-[#0F0D0B] border border-[#4A3728] rounded-lg px-3 py-2 text-[#F5EFE6] w-40"
                                    @change="clampBidQty"
                                >
                            </div>
                        </div>

                        <div class="w-full lg:w-60 text-right mt-2 lg:mt-4">
                            <div class="text-[12px] text-[#A89880] mb-2">Minimum harga: <span id="min-price-text" :class="minPriceClass" class="font-bold text-[#F5EFE6]" @animationend="minPriceShake = false">{{ minPriceText }}</span></div>
                            <div class="text-[12px] text-[#A89880] mb-2">Minimum akuisisi: <span id="min-acq-text" :class="minAcqClass" class="font-bold text-[#F5EFE6]" @animationend="minAcqShake = false">{{ minAcqText }}</span></div>
                            <div class="text-[12px] text-[#A89880] mb-2">Sisa batch: <span id="bid-weight" class="font-bold text-[#F5EFE6]">{{ remainingKg }}</span> kg</div>
                            <div class="text-[12px] text-[#A89880] mt-4">Total: <span id="bid-total" class="font-bold text-[#F5EFE6]">{{ bidTotal }}</span></div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-center">
                        <button
                            id="send-bid"
                            class="px-6 py-2 rounded-lg bg-[var(--color-accent-green)] text-black font-bold"
                            :disabled="isSendBidDisabled"
                            :class="{ 'opacity-60 cursor-not-allowed': isSendBidDisabled }"
                            @click="sendBid"
                        >
                            Ajukan Penawaran
                        </button>
                    </div>
                </div>

                <div class="p-3" style="border-top:1px solid var(--color-border-light);">
                    <div class="flex gap-2">
                        <input id="chat-input" v-model="chatDraft" class="flex-1 bg-[#0F0D0B] border border-[#4A3728] rounded-lg px-3 py-2 text-[#F5EFE6]" placeholder="Tulis pesan..." />
                        <button id="send-chat" class="px-4 py-2 rounded-lg bg-[var(--color-accent-green)] text-black font-bold" @click="sendChat">Kirim</button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BannerPreview from '../../components/exporter/BannerPreview.vue';
import '../../plugins/chart-setup';
import { Line } from 'vue-chartjs';

// Leaflet map
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIconUrl from 'leaflet/dist/images/marker-icon.png';
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png';
import customMarkerUrl from '../../assets/marker-coffee.svg';
import axios from 'axios';

// Fix default icon paths for Vite bundler
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIconUrl,
    iconUrl: markerIconUrl,
    shadowUrl: markerShadowUrl
});

// custom coffee marker (used instead of default)
const customMarker = L.icon({
    iconUrl: customMarkerUrl,
    iconSize: [30, 42],
    iconAnchor: [15, 42],
    popupAnchor: [0, -42],
    shadowUrl: markerShadowUrl,
    shadowSize: [41, 41],
    shadowAnchor: [12, 41]
});

const route = useRoute();
const router = useRouter();
const currentRouteName = computed(() => route?.name ?? 'exporter.batch-detail');

const mockData = reactive({
    headerLabel: 'Preview Batch',
    batchName: 'TORAJA KALOSI',
    acquisitionButtonLabel: 'Ajukan Penawaran Akuisisi',
    healthReport: {
        title: 'Laporan Kesehatan Data',
        badge: 'Peringatan',
        summary: 'Sistem mendeteksi anomali pada log pascapanen yang dapat mempengaruhi kualitas hasil akhir (cupping score).',
        alertTitle: 'Lonjakan Suhu Penjemuran',
        alertBody: 'Peringatan: Suhu terpal bedeng mencapai <strong class="text-[#F5EFE6]">48.5°C</strong> pada tanggal 14 Mei 2026 antara pukul 12:00 - 14:00 WITA. Rekomendasi maksimum untuk proses Natural adalah 40°C untuk mencegah *over-fermentation* atau biji retak.'
    },
    logDisclaimer: 'Setelah transaksi (akuisisi) dilakukan, data batch dalam 30 hari terakhir akan diambil, dicatat, dan dikunci (locked) ke dalam blockchain untuk keperluan audit dan verifikasi. Tindakan ini bersifat permanen dan tidak dapat dibatalkan.',
    land: {
        elevation: '1,650',
        varietas: 'Arabika Lini S-795',
        process: 'Natural Extended',
        harvestDate: '02 Mei 2026',
        coordinates: '-2.9814, 119.8660',
        mapNote: '*Klik peta untuk memverifikasi zona dataran tinggi'
    },
    volume: {
        totalKg: 1000,
        soldKg: 400,
        estimateNote: 'Estimasi serah terima: 12 Karung Grainpro',
        batchIdRange: 'TRJ-26-001 s/d 012',
        note: 'Pastikan kode ini tertulis pada label karung saat barang tiba di gudang (Warehouse Inbound).'
    },
    farmer: {
        name: 'Markus Rante',
        location: 'Kecamatan Bittuang, Tana Toraja',
        rating: '4.9',
        history: '8 Batch Sukses',
        avatar: 'https://ui-avatars.com/api/?name=Markus+Rante&background=2A2118&color=F5EFE6'
    },
    chat: {
        initials: 'MR',
        name: 'Markus Rante',
        status: 'Online'
    },
    bid: {
        minPrice: 10000,
        maxPrice: 500000,
        minAcqKg: 100,
        defaultPrice: 120000,
        defaultQty: 100
    }
});

const logSummaryRows = ref([
    { id: 1, date: '14 Mei 2026', avgTemp: '31.2°C', maxTemp: '48.5°C', humidity: '68%', note: 'Pembalikan biji ditunda karena terik' },
    { id: 2, date: '13 Mei 2026', avgTemp: '26.8°C', maxTemp: '35.0°C', humidity: '62%', note: 'Penjemuran awal (Ketebalan 3cm)' }
]);

const logModalRows = ref([
    { id: 1, date: '14 Mei 2026', time: '12:30', avgTemp: '31.2', maxTemp: '48.5', humidity: '68', note: 'Lonjakan suhu pada penjemuran' },
    { id: 2, date: '13 Mei 2026', time: '10:00', avgTemp: '26.8', maxTemp: '35.0', humidity: '62', note: 'Penjemuran awal' }
]);

// Leaflet map refs
const mapRoot = ref(null);
let mapInstance = null;

// Overlay (expanded) map refs
const isMapOverlayOpen = ref(false);
const overlayMapRoot = ref(null);
let overlayMapInstance = null;

// Open overlay and initialize a separate Leaflet map inside it
async function openMapOverlay() {
    if (isMapOverlayOpen.value) return;
    isMapOverlayOpen.value = true;
    await nextTick();

    try {
        console.debug('[BatchDetail] openMapOverlay called');
        const coordStr = String(mockData.land.coordinates || '');
        const parts = coordStr.split(',').map((s) => s.trim());
        const lat = parseFloat(parts[0]);
        const lng = parseFloat(parts[1]);

        if (!Number.isNaN(lat) && !Number.isNaN(lng) && overlayMapRoot.value) {
            console.debug('[BatchDetail] initializing overlay map', { lat, lng, root: overlayMapRoot.value });
            overlayMapInstance = L.map(overlayMapRoot.value, { zoomControl: true }).setView([lat, lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(overlayMapInstance);
            L.marker([lat, lng], { icon: customMarker }).addTo(overlayMapInstance);

            // ensure proper sizing after mount
            setTimeout(() => {
                try { overlayMapInstance.invalidateSize(); } catch (e) { /* ignore */ }
            }, 120);
        }
    } catch (err) {
        // eslint-disable-next-line no-console
        console.error('Failed to initialize overlay Leaflet map', err);
    }
}

const fetchAvailableBatchDetail = async () => {
    const batchIdFromQuery = route.query.id;
    if (!batchIdFromQuery) return;
    try {
        const response = await axios.get(`/api/v1/exporter/batches/available/${batchIdFromQuery}`);
        if (response.data?.success && response.data?.data?.batch) {
            const b = response.data.data.batch;
            mockData.batchName = `${b.variety || 'Kopi'} - ${b.internal_code || b.batch_code || b.id}`;
            mockData.land.elevation = String(b.elevation_mdpl || '1650');
            mockData.land.varietas = b.variety || 'Arabika';
            mockData.land.harvestDate = b.harvest_date || '2026-05-02';
            mockData.farmer.name = b.farmer?.name || 'Petani';
            mockData.farmer.location = b.farmer?.location || 'Tana Toraja';
            mockData.land.coordinates = b.coordinates || '-2.9814, 119.8660';
            mockData.healthReport.badge = b.health_status || 'normal';

            if (b.logs && b.logs.length > 0) {
                logSummaryRows.value = b.logs.map(log => ({
                    id: log.id,
                    date: log.date || new Date(log.created_at).toLocaleDateString('id-ID'),
                    avgTemp: `${log.temperature || 0}°C`,
                    maxTemp: `${log.max_temperature || log.temperature || 0}°C`,
                    humidity: `${log.humidity || 0}%`,
                    note: log.note || 'Normal'
                }));
                logModalRows.value = b.logs.map(log => ({
                    id: log.id,
                    date: log.date || new Date(log.created_at).toLocaleDateString('id-ID'),
                    time: log.time || new Date(log.created_at).toLocaleTimeString('id-ID'),
                    avgTemp: String(log.temperature || 0),
                    maxTemp: String(log.max_temperature || log.temperature || 0),
                    humidity: String(log.humidity || 0),
                    note: log.note || 'Normal'
                }));
            }

            // Fetch Telemetry dari Supabase
            try {
                const telemetryRes = await axios.get(`/api/v1/exporter/batches/${batchIdFromQuery}/telemetry`);
                if (telemetryRes.data?.success && telemetryRes.data.data?.logs?.length > 0) {
                    const telemetryLogs = telemetryRes.data.data.logs;
                    logSummaryRows.value = telemetryLogs.map((log, idx) => ({
                        id: idx + 1,
                        date: new Date(log.created_at).toLocaleDateString('id-ID'),
                        avgTemp: `${log.temperature || 0}°C`,
                        maxTemp: `${log.temperature || 0}°C`,
                        humidity: `${log.humidity || 0}%`,
                    }));
                    logModalRows.value = telemetryLogs.map((log, idx) => ({
                        id: idx + 1,
                        date: new Date(log.created_at).toLocaleDateString('id-ID'),
                        time: new Date(log.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
                        avgTemp: String(log.temperature || 0),
                        maxTemp: String(log.temperature || 0),
                        humidity: String(log.humidity || 0),
                        note: 'Supabase Real-time'
                    }));
                }
            } catch (telemetryErr) {
                console.warn('Gagal mem-fetch data telemetri Supabase:', telemetryErr);
            }
        }
    } catch (err) {
        console.error('Failed to fetch available batch detail:', err);
    }
};

function closeMapOverlay() {
    if (overlayMapInstance) {
        try { overlayMapInstance.remove(); } catch (e) { /* ignore */ }
        overlayMapInstance = null;
    }
    isMapOverlayOpen.value = false;
}

const formattedPrice = computed(() => formatRupiah(mockData.bid.defaultPrice));

const remainingKg = computed(() => Math.max(mockData.volume.totalKg - mockData.volume.soldKg, 0));

const bidPrice = ref(mockData.bid.defaultPrice);
const bidQty = ref(mockData.bid.defaultQty);

const minPriceShake = ref(false);
const minAcqShake = ref(false);

const isMinPriceActive = computed(() => bidPrice.value > 0 && bidPrice.value < mockData.bid.minPrice);
const isMinAcqActive = computed(() => bidQty.value > 0 && bidQty.value < mockData.bid.minAcqKg);

const minPriceText = computed(() => `${formatRupiah(mockData.bid.minPrice)} / Kg`);
const minAcqText = computed(() => `${mockData.bid.minAcqKg} kg`);

const minPriceClass = computed(() => ({
    'min-price-active': isMinPriceActive.value,
    'min-price-shake': minPriceShake.value
}));

const minAcqClass = computed(() => ({
    'min-acq-active': isMinAcqActive.value,
    'min-acq-shake': minAcqShake.value
}));

const bidTotal = computed(() => formatRupiah((bidPrice.value || 0) * (bidQty.value || 0)));

const isSendBidDisabled = computed(() => remainingKg.value < mockData.bid.minAcqKg);

watch(bidPrice, () => {
    if (isMinPriceActive.value) {
        minPriceShake.value = false;
        requestAnimationFrame(() => {
            minPriceShake.value = true;
        });
    }
});

watch(bidQty, () => {
    if (isMinAcqActive.value) {
        minAcqShake.value = false;
        requestAnimationFrame(() => {
            minAcqShake.value = true;
        });
    }
});

watch(remainingKg, (value) => {
    if (bidQty.value > value) {
        bidQty.value = value;
    }
});

const clampBidPrice = () => {
    let value = Number(bidPrice.value) || 0;
    if (value < mockData.bid.minPrice) value = mockData.bid.minPrice;
    if (value > mockData.bid.maxPrice) value = mockData.bid.maxPrice;
    bidPrice.value = value;
};

const clampBidQty = () => {
    let value = Number(bidQty.value) || 0;
    if (value < mockData.bid.minAcqKg) value = mockData.bid.minAcqKg;
    if (remainingKg.value && value > remainingKg.value) value = remainingKg.value;
    bidQty.value = value;
};

const isLogModalOpen = ref(false);
const isChatOpen = ref(false);
const isBidPanelOpen = ref(false);

// Chart refs and reactive datasets
const smallChartRef = ref(null);
const overlayChartRef = ref(null);
const smallChartReady = ref(false);
const overlayChartReady = ref(false);

function waitForChart(refComp, flagRef, attempts = 40, interval = 50) {
    let tries = 0;
    const id = setInterval(() => {
        tries++;
        try {
            if (refComp.value && (refComp.value.chart || refComp.value.chartInstance)) {
                flagRef.value = true;
                clearInterval(id);
            } else if (tries >= attempts) {
                clearInterval(id);
            }
        } catch (e) { clearInterval(id); }
    }, interval);
    return id;
}

const smallChartData = computed(() => {
    const labels = logSummaryRows.value.map(r => r.date);
    const tempsAvg = logSummaryRows.value.map(r => parseFloat(String(r.avgTemp)) || null);
    const tempsMax = logSummaryRows.value.map(r => parseFloat(String(r.maxTemp)) || null);
    const hums = logSummaryRows.value.map(r => parseFloat(String(r.humidity)) || null);

    return {
        labels: labels.length ? labels : ['14 Mei 2026', '13 Mei 2026'],
        datasets: [
            { label: 'Suhu (Avg °C)', data: tempsAvg.length ? tempsAvg : [31.2, 26.8], borderColor: '#E8A838', backgroundColor: 'rgba(232,168,56,0.06)', tension: 0.3, yAxisID: 'y1' },
            { label: 'Suhu (Max °C)', data: tempsMax.length ? tempsMax : [48.5, 35.0], borderColor: '#FF6B6B', backgroundColor: 'rgba(255,107,107,0.06)', tension: 0.3, yAxisID: 'y1' },
            { label: 'Kelembaban (%)', data: hums.length ? hums : [68, 62], borderColor: '#08C246', backgroundColor: 'rgba(8,194,70,0.08)', tension: 0.3, yAxisID: 'y2' }
        ]
    };
});

const overlayChartData = computed(() => {
    const labels = logModalRows.value.map(r => r.date + (r.time && r.time !== '-' ? ' ' + r.time : ''));
    const tempsAvg = logModalRows.value.map(r => parseFloat(String(r.avgTemp)) || null);
    const tempsMax = logModalRows.value.map(r => parseFloat(String(r.maxTemp)) || null);
    const hums = logModalRows.value.map(r => parseFloat(String(r.humidity)) || null);

    return {
        labels: labels.length ? labels : ['14 Mei 2026 12:30', '13 Mei 2026 10:00'],
        datasets: [
            { label: 'Suhu (Avg °C)', data: tempsAvg.length ? tempsAvg : [31.2, 26.8], borderColor: '#E8A838', backgroundColor: 'rgba(232,168,56,0.06)', tension: 0.3, yAxisID: 'y1' },
            { label: 'Suhu (Max °C)', data: tempsMax.length ? tempsMax : [48.5, 35.0], borderColor: '#FF6B6B', backgroundColor: 'rgba(255,107,107,0.06)', tension: 0.3, yAxisID: 'y1' },
            { label: 'Kelembaban (%)', data: hums.length ? hums : [68, 62], borderColor: '#08C246', backgroundColor: 'rgba(8,194,70,0.08)', tension: 0.3, yAxisID: 'y2' }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { labels: { color: '#A89880' } },
        title: { display: false }
    },
    scales: {
        y1: { type: 'linear', position: 'left', title: { display: true, text: 'Suhu (°C)', color: '#A89880' }, ticks: { color: '#F5EFE6' } },
        y2: { type: 'linear', position: 'right', title: { display: true, text: 'Kelembaban (%)', color: '#A89880' }, grid: { drawOnChartArea: false }, ticks: { color: '#F5EFE6' } },
        x: { ticks: { color: '#A89880' } }
    }
};

function safeDestroyChart(refComp) {
    try {
        if (!refComp || !refComp.value) return;
        const cmp = refComp.value;
        const instance = cmp.chart || cmp.chartInstance || cmp._chart || (cmp.$data && cmp.$data._chart);
        if (instance && typeof instance.destroy === 'function') instance.destroy();
    } catch (e) {
        // ignore
    }
}

const openLogModal = async () => {
    console.debug('[BatchDetail] openLogModal called');
    isLogModalOpen.value = true;
    await nextTick();
    try {
        if (overlayChartRef.value) {
            console.debug('[BatchDetail] overlayChartRef exists, attempting update', overlayChartRef.value);
            try { overlayChartRef.value.chart?.update?.(); } catch (e) { console.warn('[BatchDetail] overlay chart update failed', e); }
        } else {
            console.debug('[BatchDetail] overlayChartRef not ready yet');
        }
    } catch (e) { console.error('[BatchDetail] error updating overlay chart', e); }
};

const closeLogModal = () => {
    console.debug('[BatchDetail] closeLogModal called');
    safeDestroyChart(overlayChartRef);
    isLogModalOpen.value = false;
};

const openChat = () => {
    console.debug('[BatchDetail] openChat called');
    isChatOpen.value = true;
};

const closeChat = () => {
    isChatOpen.value = false;
    isBidPanelOpen.value = false;
};

const openAcquisition = () => {
    if (window.confirm('Apakah Anda yakin ingin mengakuisisi batch ini?')) {
        sendBid();
    }
};

const showBidPanel = () => {
    isBidPanelOpen.value = true;
};

const hideBidPanel = () => {
    isBidPanelOpen.value = false;
};

const chatMessages = ref([]);
const chatDraft = ref('');

const sendChat = () => {
    const text = chatDraft.value.trim();
    if (!text) return;
    chatMessages.value.push({
        id: Date.now(),
        type: 'text',
        text
    });
    chatDraft.value = '';
};

const sendBid = async () => {
    const batchIdFromQuery = route.query.id;
    if (!batchIdFromQuery) return;
    try {
        const response = await axios.post(`/api/v1/exporter/batches/${batchIdFromQuery}/acquire`, {});
        if (response.data?.success) {
            window.alert('Batch berhasil diakuisisi!');
            router.push({ name: 'exporter.batch-saya' });
        }
    } catch (err) {
        window.alert(err.response?.data?.message ?? 'Gagal mengakuisisi batch.');
    }
};

const rowHighlightClass = (row) => {
    const maxVal = Number(String(row.maxTemp).replace(/[^0-9.]/g, '')) || 0;
    return maxVal >= 40 ? 'bg-[#2D2210]/20' : '';
};

const rowLeftBorderClass = (row) => {
    const maxVal = Number(String(row.maxTemp).replace(/[^0-9.]/g, '')) || 0;
    return maxVal >= 40 ? 'border-l-2 border-l-[#E8A838]' : 'border-l-2 border-l-transparent';
};

const rowMaxClass = (row) => {
    const maxVal = Number(String(row.maxTemp).replace(/[^0-9.]/g, '')) || 0;
    return maxVal >= 40 ? 'text-[#E8A838] font-bold' : '';
};

function formatRupiah(value) {
    if (value === null || typeof value === 'undefined') return 'Rp 0';
    return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

onMounted(async () => {
    await fetchAvailableBatchDetail();

    console.debug('[BatchDetail] onMounted - initializing embedded map');

    // Initialize Leaflet map inside the placeholder using coordinates from mockData
    try {
        const coordStr = String(mockData.land.coordinates || '');
        const parts = coordStr.split(',').map((s) => s.trim());
        const lat = parseFloat(parts[0]);
        const lng = parseFloat(parts[1]);

        console.debug('[BatchDetail] parsed coords', { coordStr, lat, lng, mapRoot: mapRoot.value });

        if (!Number.isNaN(lat) && !Number.isNaN(lng) && mapRoot.value) {
            // create map
            mapInstance = L.map(mapRoot.value, { zoomControl: true }).setView([lat, lng], 13);

            // add OSM tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(mapInstance);

            // add marker at the coordinates (custom icon)
            L.marker([lat, lng], { icon: customMarker }).addTo(mapInstance);

            console.debug('[BatchDetail] embedded map initialized', mapInstance);
        }
    } catch (err) {
        // do not break the page if map fails
        // eslint-disable-next-line no-console
        console.error('[BatchDetail] Failed to initialize Leaflet map', err);
    }
});

onBeforeUnmount(() => {
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }
    if (overlayMapInstance) {
        try { overlayMapInstance.remove(); } catch (e) { }
        overlayMapInstance = null;
    }
    // destroy chart instances
    safeDestroyChart(smallChartRef);
    safeDestroyChart(overlayChartRef);
});
</script>

<style>
#min-acq-text.min-acq-active {
    text-decoration: underline;
    text-decoration-color: var(--color-error, #ff4d4f);
    text-decoration-thickness: 2px;
    text-underline-offset: 6px;
}
#min-price-text.min-price-active {
    text-decoration: underline;
    text-decoration-color: var(--color-error, #ff4d4f);
    text-decoration-thickness: 2px;
    text-underline-offset: 6px;
}
#min-price-text.min-price-shake,
#min-acq-text.min-acq-shake {
    display: inline-block;
    animation: minAcqShake 420ms ease-in-out;
}
#min-acq-text.min-acq-shake {
    display: inline-block;
    animation: minAcqShake 420ms ease-in-out;
}
@keyframes minAcqShake {
    0% { transform: translateX(0); }
    20% { transform: translateX(-6px); }
    40% { transform: translateX(6px); }
    60% { transform: translateX(-4px); }
    80% { transform: translateX(4px); }
    100% { transform: translateX(0); }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}

/* Ensure leaflet container doesn't show unexpected box artifacts */
.leaflet-container {
    background: transparent !important;
}

/* Ensure chart canvas background matches theme and avoids visible box artifacts */
.chartjs-render-monitor {
    background: transparent;
}
</style>
