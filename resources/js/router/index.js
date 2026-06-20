import { createRouter, createWebHistory } from 'vue-router';

// Import komponen-komponen lu nanti
const routes = [
    {
        path: '/',
        name: 'landing',
        component: () => import('../views/LandingPage.vue')
    },
    {
        path: '/login',
        name: 'auth.login-lagi',
        component: () => import('../views/auth/LoginLagi.vue')
    },
    {
        path: '/register',
        name: 'auth.register-lagi',
        component: () => import('../views/auth/RegisterLagi.vue')
    },
    {
        path: '/forgot-password',
        name: 'auth.forgot-password',
        component: () => import('../views/auth/ForgotPass.vue')
    },
    {
        path: '/exporter/dashboard',
        name: 'exporter.dashboard',
        component: () => import('../views/exporter/Dashboard.vue')
    },
    {
        path: '/exporter/new-batch',
        name: 'exporter.new-batch',
        component: () => import('../views/exporter/NewBatch.vue')
    },
    {
        path: '/exporter/batch-tersedia',
        name: 'exporter.batch-tersedia',
        component: () => import('../views/exporter/BatchTersedia.vue')
    },
    {
        path: '/exporter/batch-saya',
        name: 'exporter.batch-saya',
        component: () => import('../views/exporter/BatchSaya.vue')
    },
    {
        path: '/exporter/pengaturan',
        name: 'exporter.pengaturan',
        component: () => import('../views/exporter/Pengaturan.vue')
    },
    {
        path: '/exporter/batch-saya-detail',
        name: 'exporter.batch-saya-detail',
        component: () => import('../views/exporter/BatchSayaDetail.vue')
    },
    {
        path: '/exporter/batch-detail',
        name: 'exporter.batch-detail',
        component: () => import('../views/exporter/BatchDetail.vue')
    },
    {
        path: '/exporter/modify-batch',
        name: 'exporter.modify-batch',
        component: () => import('../views/exporter/ModifyBatch.vue')
    },
    {
        path: '/farmer',
        redirect: { name: 'farmer.dashboard' }
    },
    {
        path: '/farmer/dashboard',
        name: 'farmer.dashboard',
        component: () => import('../views/farmer/Dashboard.vue')
    },
    {
        path: '/farmer/new-batch',
        name: 'farmer.new-batch',
        component: () => import('../views/farmer/NewBatch.vue')
    },
    {
        path: '/farmer/batch-detail',
        name: 'farmer.batch-detail',
        component: () => import('../views/farmer/BatchDetail.vue')
    },
    {
        path: '/farmer/pengaturan',
        name: 'farmer.pengaturan',
        component: () => import('../views/farmer/Pengaturan.vue')
    },
    {
        path: '/buyer/dashboard',
        name: 'buyer.dashboard',
        component: () => import('../views/buyer/Dashboard.vue')
    },
    {
        path: '/buyer/catalog',
        name: 'buyer.catalog',
        component: () => import('../views/buyer/Catalog.vue')
    },
    {
        path: '/buyer/catalog-detail',
        name: 'buyer.catalog-detail',
        component: () => import('../views/buyer/CatalogDetail.vue')
    },
    {
        path: '/buyer/checkout',
        name: 'buyer.checkout',
        component: () => import('../views/buyer/Checkout.vue')
    },
    {
        path: '/buyer/orders',
        name: 'buyer.orders',
        component: () => import('../views/buyer/Orders.vue')
    },
    {
        path: '/buyer/settings',
        name: 'buyer.settings',
        component: () => import('../views/buyer/Pengaturan.vue')
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('access_token');
    const role = localStorage.getItem('user_role');

    // Public authentication pages and landing page
    const isPublic = ['landing', 'auth.login-lagi', 'auth.register-lagi', 'auth.forgot-password'].includes(to.name);

    if (!token && !isPublic) {
        // Redirect to landing page if unauthenticated and trying to access a protected page
        return next({ name: 'landing' });
    }

    if (token && to.name === 'auth.forgot-password') {
        // Redirect authenticated users trying to access forgot-password to their role dashboard
        if (role === 'farmer') return next({ name: 'farmer.dashboard' });
        if (role === 'exporter') return next({ name: 'exporter.dashboard' });
        if (role === 'buyer') return next({ name: 'buyer.dashboard' });
    }

    // Role verification to prevent cross-dashboard access
    if (to.path.startsWith('/farmer') && role !== 'farmer') {
        return next({ name: 'landing' });
    }
    if (to.path.startsWith('/exporter') && role !== 'exporter') {
        return next({ name: 'landing' });
    }
    if (to.path.startsWith('/buyer') && role !== 'buyer') {
        return next({ name: 'landing' });
    }

    next();
});

export default router;