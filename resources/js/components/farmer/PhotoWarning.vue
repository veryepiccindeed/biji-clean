<template>
    <div class="rounded-2xl border border-[var(--color-warning)] bg-[var(--color-bg-hover)] p-4">
        <p class="text-[13px] font-semibold text-[var(--color-warning)]">{{ title }}</p>
        <p class="mt-2 text-[12px] leading-5 text-[var(--color-text-secondary)]">{{ description }}</p>
    </div>
</template>

<script setup>
const props = defineProps({
    title: {
        type: String,
        default: 'Wajib minimal 3 foto dengan posisi yang berbeda.'
    },
    description: {
        type: String,
        default: 'Semakin banyak foto yang diunggah, semakin detail verifikasi awal yang bisa dilakukan oleh tim BIJI sebelum survey lapangan.'
    }
});
</script>
