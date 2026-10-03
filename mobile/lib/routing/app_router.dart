import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/state/auth_controller.dart';
import '../features/auth/ui/login_screen.dart';
import '../features/auth/ui/register_screen.dart';
import '../features/home/home_screen.dart';
import '../features/tax/ui/calculator_screen.dart';

/// Splash shown while the persisted token is being verified on startup.
class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}

final routerProvider = Provider<GoRouter>((ref) {
  // Rebuild redirects whenever auth state changes.
  final refresh = _AuthRefreshNotifier(ref);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: refresh,
    routes: [
      GoRoute(path: '/', builder: (_, __) => const HomeScreen()),
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(path: '/register', builder: (_, __) => const RegisterScreen()),
      GoRoute(path: '/calculator', builder: (_, __) => const CalculatorScreen()),
      GoRoute(path: '/splash', builder: (_, __) => const _SplashScreen()),
    ],
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      final loc = state.matchedLocation;

      if (auth is AuthLoading) return loc == '/splash' ? null : '/splash';

      final loggedIn = auth is AuthAuthenticated;
      // Public routes reachable without a session.
      const public = {'/login', '/register', '/calculator'};

      if (!loggedIn) {
        if (public.contains(loc)) return null;
        return '/login';
      }
      // Signed in: keep them out of the auth/splash pages.
      if (loc == '/login' || loc == '/register' || loc == '/splash') return '/';
      return null;
    },
  );
});

/// Bridges Riverpod state changes to go_router's Listenable refresh.
class _AuthRefreshNotifier extends ChangeNotifier {
  _AuthRefreshNotifier(Ref ref) {
    ref.listen(authControllerProvider, (_, __) => notifyListeners());
  }
}
