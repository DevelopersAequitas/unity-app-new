import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

Future<void> showOptionalUpdateDialog(
  BuildContext context, {
  required String playStoreUrl,
  required VoidCallback onSkip,
}) async {
  await showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (_) => AlertDialog(
      title: const Text('Update Available'),
      content: const Text('A new app version is available. Update now for best experience.'),
      actions: [
        TextButton(
          onPressed: () {
            Navigator.of(context).pop();
            onSkip();
          },
          child: const Text('Later'),
        ),
        ElevatedButton(
          onPressed: () async {
            final uri = Uri.tryParse(playStoreUrl);
            if (uri != null) {
              await launchUrl(uri, mode: LaunchMode.externalApplication);
            }
            if (context.mounted) {
              Navigator.of(context).pop();
            }
          },
          child: const Text('Update'),
        ),
      ],
    ),
  );
}
