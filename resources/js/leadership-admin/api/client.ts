import axios, { AxiosError } from 'axios';
import { ApiResponse } from '../types';

const getCsrfToken = (): string => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
};

export const apiClient = axios.create({
    baseURL: '/api/v1/leadership',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
    },
});

apiClient.interceptors.request.use((config) => {
    const csrf = getCsrfToken();
    if (csrf) {
        config.headers['X-CSRF-TOKEN'] = csrf;
    }
    return config;
});

apiClient.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiResponse>) => {
        if (error.response?.status === 401) {
            console.warn('Session expired or unauthorized. Please re-login.');
        }
        return Promise.reject(error);
    }
);

export const handleApiError = (error: any): string => {
    if (error?.response?.data?.message) {
        return error.response.data.message;
    }
    if (error?.message) {
        return error.message;
    }
    return 'An unexpected error occurred while communicating with the server.';
};
