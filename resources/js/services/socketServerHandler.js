/**
 * Node.js / Express + Socket.io WebSocket Server Handler Reference
 * Handles user room mapping and FORCE_LOGOUT emissions across cluster/connections.
 */

const userSocketsMap = new Map(); // userId -> Set of socket instances

/**
 * Register Socket.io authentication and force logout handlers.
 * @param {import('socket.io').Server} io
 */
export function registerSocketHandler(io) {
    io.use((socket, next) => {
        const userId = socket.handshake.auth?.userId || socket.handshake.query?.userId;
        const token = socket.handshake.auth?.token;

        if (!userId || !token) {
            return next(new Error('Authentication error: Missing userId or token'));
        }

        socket.userId = String(userId);
        next();
    });

    io.on('connection', (socket) => {
        const userId = socket.userId;
        console.log(`[SocketServer] User ${userId} connected on socket ${socket.id}`);

        // Join room specific to user ID
        const roomName = `user_${userId}`;
        socket.join(roomName);

        // Track socket connection in map
        if (!userSocketsMap.has(userId)) {
            userSocketsMap.set(userId, new Set());
        }
        userSocketsMap.get(userId).add(socket.id);

        socket.on('disconnect', () => {
            console.log(`[SocketServer] User ${userId} disconnected from socket ${socket.id}`);
            const userSockets = userSocketsMap.get(userId);
            if (userSockets) {
                userSockets.delete(socket.id);
                if (userSockets.size === 0) {
                    userSocketsMap.delete(userId);
                }
            }
        });
    });
}

/**
 * Emit FORCE_LOGOUT event to all socket connections bound to a given user ID.
 * Call this function on new device login BEFORE registering the new active session.
 *
 * @param {import('socket.io').Server} io
 * @param {string} userId
 * @param {Object} payload
 */
export function emitForceLogout(io, userId, payload = {}) {
    const roomName = `user_${userId}`;
    const logoutPayload = {
        code: 'SESSION_SUPERSEDED',
        message: 'You have been logged out because your account was accessed on another device.',
        timestamp: new Date().toISOString(),
        ...payload,
    };

    console.warn(`[SocketServer] Emitting FORCE_LOGOUT to room ${roomName}`);
    io.to(roomName).emit('FORCE_LOGOUT', logoutPayload);
}

export default {
    registerSocketHandler,
    emitForceLogout,
};
