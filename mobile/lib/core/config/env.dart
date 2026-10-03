import 'dart:io' show Platform;

/// Runtime configuration for the API backend.
///
/// Override the base URL at build/run time without editing code:
///   flutter run --dart-define=API_BASE_URL=https://api.example.com
///
/// Defaults are chosen per platform so the local Docker backend
/// (http://localhost:8088) is reachable from each emulator:
///   * Android emulator reaches the host via 10.0.2.2
///   * iOS simulator reaches the host via localhost
class Env {
  const Env._();

  static const String _override =
      String.fromEnvironment('API_BASE_URL', defaultValue: '');

  /// Base origin of the Laravel app (no trailing slash), e.g. http://10.0.2.2:8088
  static String get baseUrl {
    if (_override.isNotEmpty) return _override;
    // Web/desktop and iOS use localhost; Android emulator remaps the host.
    if (!Platform.isAndroid) return 'http://localhost:8088';
    return 'http://10.0.2.2:8088';
  }

  /// Versioned REST API prefix.
  static const String apiPrefix = '/api/v1';

  static String get apiBaseUrl => '$baseUrl$apiPrefix';
}
