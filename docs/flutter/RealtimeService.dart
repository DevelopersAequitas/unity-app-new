import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

class RealtimeService {
  RealtimeService({
    required this.appKey,
    required this.authEndpoint,
    required this.tokenProvider,
    this.host = 'peersunity.com',
    this.wsPort = 443,
    this.wssPort = 443,
    this.forceTLS = true,
  });

  final String appKey;
  final String authEndpoint;
  final Future<String?> Function() tokenProvider;
  final String host;
  final int wsPort;
  final int wssPort;
  final bool forceTLS;

  final PusherChannelsFlutter _pusher = PusherChannelsFlutter.getInstance();
  final Set<String> _channels = <String>{};
  final Random _random = Random();

  Timer? _reconnectTimer;
  bool _disposed = false;
  bool _connecting = false;
  bool _connected = false;
  int _reconnectAttempt = 0;

  String get _scheme => forceTLS ? 'wss' : 'ws';
  int get _port => forceTLS ? wssPort : wsPort;
  String get connectionUrl => '$_scheme://$host:$_port/app/$appKey';

  Future<void> connect() async {
    if (_disposed) {
      _log('connect.ignored', {'reason': 'disposed'});
      return;
    }
    if (_connecting || _connected) {
      _log('connect.ignored', {'connecting': _connecting, 'connected': _connected});
      return;
    }

    _connecting = true;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;

    _log('connect.start', {
      'url': connectionUrl,
      'host': host,
      'wsPort': wsPort,
      'wssPort': wssPort,
      'tls': forceTLS,
      'appKey': appKey,
      'authEndpoint': authEndpoint,
    });

    try {
      await _pusher.init(
        apiKey: appKey,
        cluster: 'mt1',
        host: host,
        wsPort: wsPort,
        wssPort: wssPort,
        useTLS: forceTLS,
        enabledTransports: forceTLS ? const ['wss'] : const ['ws'],
        authEndpoint: authEndpoint,
        onAuthorizer: _authorize,
        onConnectionStateChange: _onConnectionStateChange,
        onError: _onError,
        onSubscriptionSucceeded: _onSubscriptionSucceeded,
        onSubscriptionError: _onSubscriptionError,
        onEvent: _onEvent,
      );

      if (_disposed) {
        await _safeDisconnect();
        return;
      }

      await _pusher.connect();
    } catch (error, stackTrace) {
      _log('connect.error', {'error': error.toString(), 'stackTrace': stackTrace.toString()});
      _scheduleReconnect();
    } finally {
      _connecting = false;
    }
  }

  Future<Map<String, String>> _authorize(String channelName, String socketId, dynamic options) async {
    if (_disposed) {
      throw StateError('RealtimeService is disposed');
    }

    final token = await tokenProvider();
    _log('auth.request', {'channel': channelName, 'socketId': socketId, 'hasToken': token != null});

    final headers = <String, String>{
      'Accept': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };

    _log('auth.headers.ready', {'channel': channelName});
    return headers;
  }

  Future<void> subscribe(String channelName) async {
    if (_disposed) return;
    await connect();
    if (_disposed) return;

    if (_channels.add(channelName)) {
      _log('subscribe.start', {'channel': channelName});
      await _pusher.subscribe(channelName: channelName);
    }
  }

  Future<void> unsubscribe(String channelName) async {
    if (!_channels.remove(channelName)) return;
    try {
      _log('unsubscribe.start', {'channel': channelName});
      await _pusher.unsubscribe(channelName: channelName);
    } catch (error) {
      _log('unsubscribe.error', {'channel': channelName, 'error': error.toString()});
    }
  }

  void _onConnectionStateChange(dynamic currentState, dynamic previousState) {
    if (_disposed) return;
    _log('connection.state', {'previous': previousState?.toString(), 'current': currentState?.toString()});

    final state = currentState?.toString().toLowerCase() ?? '';
    _connected = state.contains('connected');

    if (_connected) {
      _reconnectAttempt = 0;
      return;
    }

    if (state.contains('disconnected') || state.contains('failed') || state.contains('unavailable')) {
      _scheduleReconnect();
    }
  }

  void _onError(String message, int? code, dynamic error) {
    if (_disposed) return;
    _log('connection.error', {'message': message, 'code': code, 'error': error?.toString()});
    _scheduleReconnect();
  }

  void _onSubscriptionSucceeded(String channelName, dynamic data) {
    if (_disposed) return;
    _log('channel.subscribed', {'channel': channelName, 'data': data?.toString()});
  }

  void _onSubscriptionError(String message, dynamic error) {
    if (_disposed) return;
    _log('channel.error', {'message': message, 'error': error?.toString()});
  }

  void _onEvent(PusherEvent event) {
    if (_disposed) return;
    _log('event.received', {'channel': event.channelName, 'event': event.eventName, 'data': event.data});
  }

  void _scheduleReconnect() {
    if (_disposed || _reconnectTimer?.isActive == true || _connecting) return;

    final attempt = ++_reconnectAttempt;
    final exponentialSeconds = min(30, pow(2, attempt).toInt());
    final jitterMs = _random.nextInt(750);
    final delay = Duration(seconds: exponentialSeconds, milliseconds: jitterMs);

    _log('reconnect.scheduled', {'attempt': attempt, 'delayMs': delay.inMilliseconds});
    _reconnectTimer = Timer(delay, () async {
      if (_disposed) return;
      _log('reconnect.attempt', {'attempt': attempt});
      await _safeDisconnect();
      await connect();
      for (final channel in List<String>.from(_channels)) {
        if (_disposed) return;
        await _pusher.subscribe(channelName: channel);
      }
    });
  }

  Future<void> _safeDisconnect() async {
    _connected = false;
    try {
      await _pusher.disconnect();
    } catch (error) {
      _log('disconnect.error', {'error': error.toString()});
    }
  }

  Future<void> dispose() async {
    if (_disposed) return;
    _disposed = true;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;

    for (final channel in List<String>.from(_channels)) {
      try {
        await _pusher.unsubscribe(channelName: channel);
      } catch (_) {}
    }
    _channels.clear();
    await _safeDisconnect();
  }

  void _log(String event, Map<String, Object?> payload) {
    debugPrint('[RealtimeService] $event $payload');
  }
}
