import 'package:flutter/material.dart';

import 'RealtimeService.dart';

void main() {
  runApp(const PeersUnityRealtimeExample());
}

class PeersUnityRealtimeExample extends StatefulWidget {
  const PeersUnityRealtimeExample({super.key});

  @override
  State<PeersUnityRealtimeExample> createState() => _PeersUnityRealtimeExampleState();
}

class _PeersUnityRealtimeExampleState extends State<PeersUnityRealtimeExample> {
  late final RealtimeService realtime;
  bool connected = false;

  @override
  void initState() {
    super.initState();
    realtime = RealtimeService(
      appKey: const String.fromEnvironment('REVERB_APP_KEY', defaultValue: 'change-me-public-app-key'),
      authEndpoint: 'https://peersunity.com/api/broadcasting/auth',
      tokenProvider: _loadBearerToken,
      host: 'peersunity.com',
      wsPort: 443,
      wssPort: 443,
      forceTLS: true,
    );
    _connect();
  }

  Future<String?> _loadBearerToken() async {
    return null;
  }

  Future<void> _connect() async {
    await realtime.connect();
    if (!mounted) return;
    setState(() => connected = true);
    await realtime.subscribe('reverb-test');
  }

  @override
  void dispose() {
    realtime.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: Scaffold(
        appBar: AppBar(title: const Text('PeersUnity Reverb')),
        body: Center(
          child: Text(connected ? 'Realtime connecting over WSS :443' : 'Disconnected'),
        ),
      ),
    );
  }
}
