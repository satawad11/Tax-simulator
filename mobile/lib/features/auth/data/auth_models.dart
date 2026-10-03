/// Domain models for authentication, parsed from the `/api/v1/auth/*` payloads.

class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    this.role,
    this.emailVerified,
  });

  final int id;
  final String name;
  final String email;
  final String? role;
  final bool? emailVerified;

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String?,
      emailVerified: json['email_verified_at'] != null
          ? true
          : (json['email_verified'] as bool?),
    );
  }
}

/// The login/register response payload: `{ user, token, token_type }`.
class AuthToken {
  const AuthToken({required this.user, required this.token});

  final AppUser user;
  final String token;

  factory AuthToken.fromJson(Map<String, dynamic> json) {
    return AuthToken(
      user: AppUser.fromJson(json['user'] as Map<String, dynamic>),
      token: json['token'] as String,
    );
  }
}
