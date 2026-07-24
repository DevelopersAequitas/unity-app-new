/**
 * Global Axios Response Interceptor for Concurrent Session Control
 * Handlers HTTP 401 Unauthorized responses with code 'SESSION_SUPERSEDED'
 */
import axios from 'axios';

export function setupApiInterceptor({ onSessionSuperseded, loginUrl = '/login' } = {}) {
    axios.interceptors.response.use(
        (response) => response,
        async (error) => {
            const status = error.response ? error.response.status : null;
            const data = error.response ? error.response.data : null;

            // Check if response status is 401 with SESSION_SUPERSEDED error code
            if (status === 401 && data && data.code === 'SESSION_SUPERSEDED') {
                const message = data.message || 'You have been logged out because your account was accessed on another device.';

                // 1. Clear local & session storage auth artifacts
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user_data');
                localStorage.removeItem('session_id');
                sessionStorage.clear();

                // 2. Disconnect active WebSocket connection
                if (window.Echo) {
                    window.Echo.disconnect();
                }

                // 3. Optional custom callback or alert/toast notification
                if (typeof onSessionSuperseded === 'function') {
                    onSessionSuperseded(message);
                } else if (typeof window.showToast === 'function') {
                    window.showToast(message, 'warning');
                } else {
                    alert(message);
                }

                // 4. Immediately redirect to login screen
                if (window.location.pathname !== loginUrl) {
                    window.location.href = loginUrl;
                }

                return Promise.reject(error);
            }

            return Promise.reject(error);
        }
    );
}

export default setupApiInterceptor;
