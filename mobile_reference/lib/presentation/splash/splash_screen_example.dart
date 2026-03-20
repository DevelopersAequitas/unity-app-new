import 'package:flutter/material.dart';

import '../../core/update/update_service.dart';
import '../update/force_update_screen.dart';
import '../update/update_dialog.dart';

class SplashScreenExample extends StatefulWidget {
  const SplashScreenExample({super.key});

  @override
  State<SplashScreenExample> createState() => _SplashScreenExampleState();
}

class _SplashScreenExampleState extends State<SplashScreenExample> {
  final _updateService = UpdateService(baseUrl: 'https://api.example.com');

  @override
  void initState() {
    super.initState();
    _checkVersion();
  }

  Future<void> _checkVersion() async {
    final result = await _updateService.checkForUpdate();

    if (!mounted) {
      return;
    }

    switch (result.decision) {
      case UpdateDecision.forceUpdate:
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => ForceUpdateScreen(playStoreUrl: result.playStoreUrl ?? ''),
          ),
        );
        break;
      case UpdateDecision.optionalUpdate:
        await showOptionalUpdateDialog(
          context,
          playStoreUrl: result.playStoreUrl ?? '',
          onSkip: () async {
            if (result.latestVersion != null) {
              await _updateService.markOptionalDialogShown(latestVersion: result.latestVersion!);
            }
          },
        );
        _openHome();
        break;
      case UpdateDecision.noUpdate:
        _openHome();
        break;
    }
  }

  void _openHome() {
    Navigator.of(context).pushReplacementNamed('/home');
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}
