import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/device_name.dart';
import '../../../core/storage/token_storage.dart';
import '../data/auth_models.dart';
import '../data/auth_repository.dart';

/// Authentication state for the whole app. The router watches this to
/// redirect between the auth flow and the signed-in area.
sealed class AuthState {
  const AuthState();
}

/// App is still restoring a persisted token on startup.
class AuthLoading extends AuthState {
  const AuthLoading();
}

class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated();
}

class AuthAuthenticated extends AuthState {
  const AuthAuthenticated(this.user);
  final AppUser user;
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._repo, this._storage) : super(const AuthLoading()) {
    _restore();
  }

  final AuthRepository _repo;
  final TokenStorage _storage;

  /// On startup: if we hold a token, verify it with /auth/me.
  Future<void> _restore() async {
    final token = await _storage.read();
    if (token == null || token.isEmpty) {
      state = const AuthUnauthenticated();
      return;
    }
    try {
      final user = await _repo.me();
      state = AuthAuthenticated(user);
    } catch (_) {
      await _storage.clear();
      state = const AuthUnauthenticated();
    }
  }

  Future<void> login({required String email, required String password}) async {
    final device = await resolveDeviceName();
    final result =
        await _repo.login(email: email, password: password, deviceName: device);
    await _storage.write(result.token);
    state = AuthAuthenticated(result.user);
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final device = await resolveDeviceName();
    final result = await _repo.register(
      name: name,
      email: email,
      password: password,
      passwordConfirmation: passwordConfirmation,
      deviceName: device,
    );
    await _storage.write(result.token);
    state = AuthAuthenticated(result.user);
  }

  Future<void> logout() async {
    try {
      await _repo.logout();
    } catch (_) {
      // Even if the network call fails, drop the local session.
    }
    await _storage.clear();
    state = const AuthUnauthenticated();
  }
}

final authControllerProvider =
    StateNotifierProvider<AuthController, AuthState>((ref) {
  return AuthController(
    ref.watch(authRepositoryProvider),
    ref.watch(tokenStorageProvider),
  );
});
