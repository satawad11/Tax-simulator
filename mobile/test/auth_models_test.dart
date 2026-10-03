import 'package:flutter_test/flutter_test.dart';
import 'package:tax_simulator/features/auth/data/auth_models.dart';

void main() {
  group('AuthToken.fromJson', () {
    test('parses the /auth/login data payload', () {
      final token = AuthToken.fromJson({
        'user': {
          'id': 7,
          'name': 'Somchai',
          'email': 'somchai@example.com',
          'role': 'member',
          'email_verified_at': '2026-01-01T00:00:00Z',
        },
        'token': '1|abcdef',
        'token_type': 'Bearer',
      });

      expect(token.token, '1|abcdef');
      expect(token.user.id, 7);
      expect(token.user.name, 'Somchai');
      expect(token.user.emailVerified, isTrue);
    });
  });

  group('AppUser.fromJson', () {
    test('tolerates a missing role and unverified email', () {
      final user = AppUser.fromJson({
        'id': 1,
        'name': 'Nok',
        'email': 'nok@example.com',
      });

      expect(user.role, isNull);
      expect(user.emailVerified, isNull);
    });
  });
}
