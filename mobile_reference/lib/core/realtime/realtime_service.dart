import 'dart:async';

import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

enum RealtimeConnectionState {
  disconnected,
  connecting,
  connected,
  reconnecting,
  failed,
  disposed,
}

typedef RealtimeTokenProvider = Future<String?> Function();
typedef RealtimeEventHandler = void Function(PusherEvent event);

class RealtimeService {
  RealtimeService({
    required this.tokenProvider,
    this.host = 'peersunity.com',
    this.port = 443,
    this.useTLS = true,
    this.appKey = 'z8hlmhiqxac2alirbdtx',
    this.authEndpoint = 'https://peersunity.com/api/broadcasting/auth',
    PusherChannelsFlutter? pusher,
  }) : _pusher = pusher ?? PusherChannelsFlutter.getInstance();

  final RealtimeTokenProvider tokenProvider;
  final String host;
  final int port;
  final bool useTLS;
  final String appKey;
  final String authEndpoint;
  final PusherChannelsFlutter _pusher;

  final Set<String> _desiredChannels = <String>{};
  final Set<String> _subscribedChannels = <String>{};
  final Map<String, RealtimeEventHandler> _eventHandlers = <String, RealtimeEventHandler>{};

  RealtimeConnectionState _state = RealtimeConnectionState.disconnected;
  Timer? _retryTimer;
  int _retryAttempt = 0;
  bool _connectInFlight = false;

  RealtimeConnectionState get state => _state;
  bool get isConnected => _state == RealtimeConnectionState.connected;

  Future<void> connect() async {
    if (_state == RealtimeConnectionState.disposed || _connectInFlight || isConnected) {
      return;
    }

    _connectInFlight = true;
    _setState(_state == RealtimeConnectionState.failed
        ? RealtimeConnectionState.reconnecting
        : RealtimeConnectionState.connecting);

    try {
      final token = await tokenProvider();
      if (token == null || token.isEmpty) {
        throw StateError('Realtime auth token is not available.');
      }

      await _pusher.init(
        apiKey: appKey,
        cluster: 'mt1',
        useTLS: useTLS,
        host: host,
        wsPort: useTLS ? 80 : port,
        wssPort: useTLS ? port : 443,
        authEndpoint: authEndpoint,
        authParams: <String, dynamic>{
          'headers': <String, String>{
            'Authorization': 'Bearer $token',
            'Accept': 'application/json',
          },
        },
        onConnectionStateChange: _onConnectionStateChange,
        onError: _onError,
        onEvent: _onEvent,
        onSubscriptionSucceeded: (channelName, data) {
          _subscribedChannels.add(channelName);
        },
        onSubscriptionError: (message, error) {
          _subscribedChannels.clear();
          if (isConnected) {
            _scheduleReconnect();
          }
        },
      );

      // Socket creation is not a successful connection. The service only moves
      // to connected in _onConnectionStateChange after the native client reports
      // the real websocket connected state.
      await _pusher.connect();
    } catch (_) {
      _setState(RealtimeConnectionState.failed);
      _scheduleReconnect();
    } finally {
      _connectInFlight = false;
    }
  }

  Future<void> subscribePrivate(
    String channelName, {
    RealtimeEventHandler? onEvent,
  }) async {
    _desiredChannels.add(channelName);
    if (onEvent != null) {
      _eventHandlers[channelName] = onEvent;
    }

    if (!isConnected || _subscribedChannels.contains(channelName)) {
      return;
    }

    await _pusher.subscribe(channelName: channelName);
    _subscribedChannels.add(channelName);
  }

  Future<void> unsubscribe(String channelName) async {
    _desiredChannels.remove(channelName);
    _eventHandlers.remove(channelName);
    _subscribedChannels.remove(channelName);
    await _pusher.unsubscribe(channelName: channelName);
  }

  Future<void> disconnect() async {
    _retryTimer?.cancel();
    _retryTimer = null;
    _subscribedChannels.clear();
    if (_state != RealtimeConnectionState.disposed) {
      _setState(RealtimeConnectionState.disconnected);
    }
    await _pusher.disconnect();
  }

  Future<void> dispose() async {
    _setState(RealtimeConnectionState.disposed);
    _retryTimer?.cancel();
    _retryTimer = null;
    _desiredChannels.clear();
    _eventHandlers.clear();
    _subscribedChannels.clear();
    await _pusher.disconnect();
  }

  void _onConnectionStateChange(dynamic currentState, dynamic previousState) {
    final normalized = currentState.toString().toUpperCase();

    if (normalized.contains('CONNECTED')) {
      _retryAttempt = 0;
      _retryTimer?.cancel();
      _retryTimer = null;
      _setState(RealtimeConnectionState.connected);
      unawaited(_resubscribeDesiredChannels());
      return;
    }

    if (normalized.contains('CONNECTING')) {
      _setState(RealtimeConnectionState.connecting);
      return;
    }

    if (_state != RealtimeConnectionState.disposed) {
      _subscribedChannels.clear();
      _setState(RealtimeConnectionState.disconnected);
      _scheduleReconnect();
    }
  }

  void _onError(String message, int? code, dynamic error) {
    if (_state == RealtimeConnectionState.disposed) {
      return;
    }

    _subscribedChannels.clear();
    _setState(RealtimeConnectionState.failed);
    _scheduleReconnect();
  }

  void _onEvent(PusherEvent event) {
    final handler = _eventHandlers[event.channelName];
    if (handler != null) {
      handler(event);
    }
  }

  Future<void> _resubscribeDesiredChannels() async {
    for (final channelName in _desiredChannels.toList()) {
      if (!isConnected || _subscribedChannels.contains(channelName)) {
        continue;
      }

      await _pusher.subscribe(channelName: channelName);
      _subscribedChannels.add(channelName);
    }
  }

  void _scheduleReconnect() {
    if (_state == RealtimeConnectionState.disposed || _retryTimer?.isActive == true) {
      return;
    }

    final seconds = _boundedBackoffSeconds(_retryAttempt++);
    _retryTimer = Timer(Duration(seconds: seconds), () {
      _retryTimer = null;
      if (_state != RealtimeConnectionState.disposed && !isConnected) {
        unawaited(connect());
      }
    });
  }

  int _boundedBackoffSeconds(int attempt) {
    const maxDelay = 30;
    final cappedAttempt = attempt.clamp(0, 5).toInt();
    final delay = 1 << cappedAttempt;
    return delay > maxDelay ? maxDelay : delay;
  }

  void _setState(RealtimeConnectionState state) {
    _state = state;
  }
}
