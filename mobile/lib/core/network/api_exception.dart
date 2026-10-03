import 'package:dio/dio.dart';

/// A normalized API error the UI can display, built from the Laravel
/// error envelope: `{ "message": "...", "errors": { "field": ["..."] } }`.
class ApiException implements Exception {
  ApiException({
    required this.statusCode,
    required this.message,
    this.errors = const {},
  });

  final int? statusCode;
  final String message;

  /// Field -> list of validation messages (from a 422 response).
  final Map<String, List<String>> errors;

  bool get isUnauthorized => statusCode == 401;
  bool get isValidation => statusCode == 422;

  /// First validation message for a field, if any.
  String? firstError(String field) =>
      errors[field]?.isNotEmpty ?? false ? errors[field]!.first : null;

  factory ApiException.fromDio(DioException e) {
    final response = e.response;
    final status = response?.statusCode;
    final data = response?.data;

    if (data is Map) {
      final rawErrors = data['errors'];
      final parsed = <String, List<String>>{};
      if (rawErrors is Map) {
        rawErrors.forEach((key, value) {
          if (value is List) {
            parsed['$key'] = value.map((v) => '$v').toList();
          } else if (value != null) {
            parsed['$key'] = ['$value'];
          }
        });
      }
      return ApiException(
        statusCode: status,
        message: (data['message'] as String?)?.trim().isNotEmpty == true
            ? data['message'] as String
            : _defaultMessage(status, e),
        errors: parsed,
      );
    }

    return ApiException(
      statusCode: status,
      message: _defaultMessage(status, e),
    );
  }

  static String _defaultMessage(int? status, DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.sendTimeout:
        return 'การเชื่อมต่อหมดเวลา ลองใหม่อีกครั้ง';
      case DioExceptionType.connectionError:
        return 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ ตรวจอินเทอร์เน็ต';
      default:
        if (status == 401) return 'กรุณาเข้าสู่ระบบใหม่';
        if (status == 403) return 'ไม่มีสิทธิ์เข้าถึง';
        if (status == 429) return 'ทำรายการถี่เกินไป กรุณารอสักครู่';
        if (status != null && status >= 500) {
          return 'เซิร์ฟเวอร์ขัดข้อง ลองใหม่ภายหลัง';
        }
        return 'เกิดข้อผิดพลาด กรุณาลองใหม่';
    }
  }

  @override
  String toString() => 'ApiException($statusCode): $message';
}
