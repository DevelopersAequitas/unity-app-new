/**
 * Real-Time WebSocket Listener for Force Logout Events
 * Subscribes to private channel user.{userId} via Echo (or Socket.io)
 */

export function setupForceLogoutListener(userId, { onForceLogout, loginUrl = '/login' } = {}) {
    if (!userId || !window.Echo) {
        console.warn('[SocketListener] Echo or userId missing. Real-time force logout listener skipped.');
        return null;
    }

    const channelName = `user.${userId}`;
    console.log(`[SocketListener] Listening on private channel: ${channelName}`);

    const channel = window.Echo.private(channelName);

    channel.listen('.FORCE_LOGOUT', (data) => {
        console.warn('[SocketListener] Received FORCE_LOGOUT event:', data);

        const message = data.message || 'You have been logged out because your account was accessed on another device.';

        // 1. Instantly clear client auth state
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        localStorage.removeItem('session_id');
        sessionStorage.clear();

        // 2. Disconnect socket connection
        if (window.Echo) {
            window.Echo.leave(channelName);
            window.Echo.disconnect();
        }

        // 3. Display user notification
        if (typeof onForceLogout === 'function') {
            onForceLogout(data);
        } else if (typeof window.showToast === 'function') {
            window.showToast(message, 'error');
        } else {
            alert(message);
        }

        // 4. Redirect immediately to login without waiting for API request
        if (window.location.pathname !== loginUrl) {
            window.location.href = loginUrl;
        }
    });

    return () => {
        if (window.Echo) {
            window.Echo.leave(channelName);
        }
    };
}

export default setupForceLogoutListener;
