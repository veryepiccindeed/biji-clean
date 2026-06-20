import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Request interceptor: inject Sanctum Bearer token if it exists
window.axios.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('access_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor: redirect on 401 Unauthorized
window.axios.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('access_token');
            localStorage.removeItem('user_role');
            const path = window.location.pathname;
            if (path !== '/' && path !== '/login' && path !== '/register' && path !== '/forgot-password') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

