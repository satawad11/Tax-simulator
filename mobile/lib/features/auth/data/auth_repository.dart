import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import 'auth_models.dart';

/// Talks to `/api/v1/auth/*`. Returns domain models; throws ApiException on error.
class AuthRepository {
  AuthRepository(this._api);

  final ApiClient _api;

  /// POST /auth/login — { email, password, device_name }
  Future<AuthToken> login({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    final data = await _api.post('/auth/login', body: {
      'email': email,
      'password': password,
      'device_name': deviceName,
    });
    return AuthToken.fromJson(data as Map<String, dynamic>);
  }

  /// POST /auth/register — { name, email, password, password_confirmation, device_name }
  Future<AuthToken> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    required String deviceName,
  }) async {
    final data = await _api.post('/auth/register', body: {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'device_name': deviceName,
    });
    return AuthToken.fromJson(data as Map<String, dynamic>);
  }

  /// GET /auth/me — the currently authenticated user.
  Future<AppUser> me() async {
    final data = await _api.get('/auth/me');
    return AppUser.fromJson(data as Map<String, dynamic>);
  }

  /// POST /auth/logout — revoke the current token (204).
  Future<void> logout() => _api.post('/auth/logout');
}

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(ref.watch(apiClientProvider));
});
