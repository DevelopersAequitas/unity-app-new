import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:in_app_update/in_app_update.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'version_compare.dart';

enum UpdateDecision {
  forceUpdate,
  optionalUpdate,
  noUpdate,
}

class UpdateResult {
  const UpdateResult({
    required this.decision,
    this.playStoreUrl,
    this.latestVersion,
    this.error,
  });

  final UpdateDecision decision;
  final String? playStoreUrl;
  final String? latestVersion;
  final String? error;
}

class UpdateService {
  UpdateService({
    required this.baseUrl,
    http.Client? client,
  }) : _client = client ?? http.Client();

  static const _optionalDialogShownVersionKey = 'optional_dialog_shown_version';

  final String baseUrl;
  final http.Client _client;

  Future<UpdateResult> checkForUpdate() async {
    try {
      final currentVersion = await _getCurrentVersion();
      final payload = await _fetchVersionPayload();

      if (payload == null) {
        return const UpdateResult(decision: UpdateDecision.noUpdate);
      }

      final latestVersion = payload['latest_version'] as String?;
      final minVersion = payload['min_version'] as String?;
      final updateType = (payload['update_type'] as String?)?.toLowerCase();
      final playStoreUrl = payload['playstore_url'] as String?;

      if (latestVersion == null || minVersion == null) {
        return const UpdateResult(decision: UpdateDecision.noUpdate);
      }

      if (VersionCompare.compare(currentVersion, minVersion) < 0 || updateType == 'force') {
        return UpdateResult(
          decision: UpdateDecision.forceUpdate,
          playStoreUrl: playStoreUrl,
          latestVersion: latestVersion,
        );
      }

      final shouldOfferOptional =
          VersionCompare.compare(currentVersion, latestVersion) < 0 && updateType == 'optional';

      if (!shouldOfferOptional) {
        return const UpdateResult(decision: UpdateDecision.noUpdate);
      }

      final shouldShowOptionalDialog = await _shouldShowOptionalDialog(latestVersion);
      if (!shouldShowOptionalDialog) {
        return const UpdateResult(decision: UpdateDecision.noUpdate);
      }

      await _triggerNativeAndroidFlexibleUpdate();

      return UpdateResult(
        decision: UpdateDecision.optionalUpdate,
        playStoreUrl: playStoreUrl,
        latestVersion: latestVersion,
      );
    } catch (error, stackTrace) {
      debugPrint('Update check failed: $error');
      debugPrintStack(stackTrace: stackTrace);

      return UpdateResult(
        decision: UpdateDecision.noUpdate,
        error: error.toString(),
      );
    }
  }

  Future<void> markOptionalDialogShown({required String latestVersion}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_optionalDialogShownVersionKey, latestVersion);
    } catch (error) {
      debugPrint('Failed to save optional update state: $error');
    }
  }

  Future<String> _getCurrentVersion() async {
    final info = await PackageInfo.fromPlatform();
    return info.version;
  }

  Future<Map<String, dynamic>?> _fetchVersionPayload() async {
    final uri = Uri.parse('$baseUrl/api/v1/app/version?platform=android');
    final response = await _client.get(uri);

    if (response.statusCode != 200) {
      return null;
    }

    final decoded = jsonDecode(response.body) as Map<String, dynamic>;
    if (decoded['status'] != true) {
      return null;
    }

    return decoded['data'] as Map<String, dynamic>?;
  }

  Future<bool> _shouldShowOptionalDialog(String latestVersion) async {
    final prefs = await SharedPreferences.getInstance();
    final seenForVersion = prefs.getString(_optionalDialogShownVersionKey);
    return seenForVersion != latestVersion;
  }

  Future<void> _triggerNativeAndroidFlexibleUpdate() async {
    try {
      final checkResult = await InAppUpdate.checkForUpdate();

      if (checkResult.updateAvailability == UpdateAvailability.updateAvailable) {
        await InAppUpdate.startFlexibleUpdate();
      }
    } catch (error) {
      debugPrint('Native in-app update check skipped: $error');
    }
  }
}
