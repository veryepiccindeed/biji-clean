<template>
    <div class="bg-[#1C1813] border border-[#2E241C] rounded-[32px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-[#2E241C]">
                        <th class="px-8 py-6 text-[11px] font-bold text-[#5C4F42] uppercase tracking-[0.2em]">ID Pesanan</th>
                        <th class="px-8 py-6 text-[11px] font-bold text-[#5C4F42] uppercase tracking-[0.2em]">Produk</th>
                        <th class="px-8 py-6 text-[11px] font-bold text-[#5C4F42] uppercase tracking-[0.2em]">Eksportir</th>
                        <th class="px-8 py-6 text-[11px] font-bold text-[#5C4F42] uppercase tracking-[0.2em]">Status</th>
                        <th class="px-8 py-6 text-[11px] font-bold text-[#5C4F42] uppercase tracking-[0.2em]">Total</th>
                        <th class="px-8 py-6 text-[11px] font-bold text-[#5C4F42] uppercase tracking-[0.2em]">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#2E241C]">
                    <tr v-for="order in orders" :key="order.id" class="hover:bg-[#2A2118]/30 transition-colors group">
                        <td class="px-8 py-6">
                            <p class="text-[14px] font-mono font-bold text-[#F5EFE6]">{{ order.orderId }}</p>
                            <p class="text-[12px] text-[#5C4F42] mt-1">{{ order.date }}</p>
                        </td>
                        <td class="px-8 py-6">
                            <p class="text-[15px] font-bold text-[#F5EFE6]">{{ order.product }}</p>
                            <p class="text-[12px] text-[#A89880]">{{ order.variety }}</p>
                        </td>
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-[#4B3832] flex items-center justify-center text-[10px] text-white">
                                    {{ order.exporter.charAt(0) }}
                                </div>
                                <span class="text-[14px] text-[#F5EFE6]">{{ order.exporter }}</span>
                            </div>
                        </td>
                        <td class="px-8 py-6">
                            <span :class="statusClass(order.status)" class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest border">
                                {{ order.status }}
                            </span>
                        </td>
                        <td class="px-8 py-6 text-[15px] font-mono font-bold text-[#F5EFE6]">
                            {{ order.total }}
                        </td>
                        <td class="px-8 py-6">
                            <button class="text-[13px] text-[#D4AF5A] font-bold hover:underline">
                                Detail
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
defineProps({
    orders: Array,
    statusClass: Function
});
</script>
