import 'dart:io' show Platform;

import 'package:device_info_plus/device_info_plus.dart';

/// Builds a human-readable device label for the required `device_name`
/// field on /auth/login and /auth/register (also shown in the user's
/// session list). Falls back gracefully if the plugin fails.
Future<String> resolveDeviceName() async {
  final info = DeviceInfoPlugin();
  try {
    if (Platform.isAndroid) {
      final a = await info.androidInfo;
      return '${a.manufacturer} ${a.model} (Android ${a.version.release})';
    }
    if (Platform.isIOS) {
      final i = await info.iosInfo;
      return '${i.name} (${i.systemName} ${i.systemVersion})';
    }
  } catch (_) {
    // ignore and fall through
  }
  return 'Tax Simulator Mobile';
}
