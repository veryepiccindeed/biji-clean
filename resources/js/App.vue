<template>
    <router-view></router-view>
</template>

<script setup>
import { onMounted } from 'vue';
import { useUserProfileStore } from './stores/userProfile';

onMounted(() => {
    const token = localStorage.getItem('access_token');
    if (token) {
        const userProfileStore = useUserProfileStore();
        userProfileStore.fetchProfile();
    }
});
</script>