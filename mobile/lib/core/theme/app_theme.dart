import 'package:flutter/material.dart';

/// Design tokens mirrored from the web app (resources/css/app.css @theme).
/// Keep these in sync with the web so the two clients read as one product.
abstract final class AppColors {
  static const page = Color(0xFFF8FAFC); // slate-50
  static const surface = Color(0xFFFFFFFF);
  static const surfaceSoft = Color(0xFFEFF6FF); // blue-50

  static const ink = Color(0xFF0F2C5C); // near-navy headings
  static const inkMuted = Color(0xFF475569); // slate-600
  static const inkSubtle = Color(0xFF64748B); // slate-500

  static const accent = Color(0xFF2563EB); // blue-600
  static const accentStrong = Color(0xFF1D4ED8); // blue-700
  static const line = Color(0xFFE2E8F0); // slate-200

  static const success = Color(0xFF059669); // emerald-600
  static const warning = Color(0xFFD97706); // amber-600
  static const danger = Color(0xFFE11D48); // rose-600
}

abstract final class AppTheme {
  /// Uncomment the fontFamily once the Noto Sans Thai .ttf assets are added
  /// (see pubspec.yaml). Falls back to platform Thai UI font meanwhile.
  static const String? _fontFamily = null; // 'NotoSansThai'

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.accent,
      primary: AppColors.accent,
      surface: AppColors.surface,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.page,
      fontFamily: _fontFamily,
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.ink,
        elevation: 0,
        centerTitle: false,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surface,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.line),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.line),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.accent,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
    );
  }
}
