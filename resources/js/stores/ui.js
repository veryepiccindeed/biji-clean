import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
    state: () => ({
        isBatchOpen: false,
        hasManualBatchState: false,
        isMobileMenuOpen: false
    }),
    actions: {
        setBatchOpen(val) {
            this.isBatchOpen = !!val;
            this.hasManualBatchState = true;
        },
        clearManualBatchState() {
            this.hasManualBatchState = false;
        },
        toggleMobileMenu() {
            this.isMobileMenuOpen = !this.isMobileMenuOpen;
        },
        closeMobileMenu() {
            this.isMobileMenuOpen = false;
        }
    }
});
