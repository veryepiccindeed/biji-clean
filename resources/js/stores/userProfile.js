import { defineStore } from 'pinia';
import axios from 'axios';

export const useUserProfileStore = defineStore('userProfile', {
    state: () => ({
        profile: {
            initials: '',
            fullName: '',
            email: '',
            phone: '',
            location: '',
            updatedAt: '',
            verified: false,
            avatarUrl: ''
        }
    }),
    actions: {
        setProfile(updates) {
            this.profile = {
                ...this.profile,
                ...updates
            };
        },
        resetProfile(snapshot) {
            this.profile = {
                ...snapshot
            };
        },
        async fetchProfile() {
            const token = localStorage.getItem('access_token');
            if (!token) return;

            try {
                const { data } = await axios.get('/api/v1/me/profile');
                if (data && data.success && data.data) {
                    const user = data.data;
                    this.setProfile({
                        fullName: user.name,
                        initials: (user.name || 'U').charAt(0).toUpperCase(),
                        email: user.email || 'contact@arunacoffee.id',
                        phone: user.phone || '+62 812 4455 2210',
                        location: user.location || 'Indonesia',
                    });
                }
            } catch (error) {
                console.error('Failed to fetch profile:', error);
            }
        }
    }
});
