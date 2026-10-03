import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';
import '../storage/token_storage.dart';
import 'api_exception.dart';

/// Called when the backend rejects the token (401), so the app can log out.
typedef UnauthorizedCallback = void Function();

/// Thin wrapper over dio that:
///   * targets the versioned API base URL
///   * attaches the Sanctum bearer token on every request
///   * unwraps the `{ data: ... }` envelope
///   * converts DioException into a typed [ApiException]
///   * fires [onUnauthorized] when a 401 is seen
class ApiClient {
  ApiClient({
    required TokenStorage tokenStorage,
    UnauthorizedCallback? onUnauthorized,
    Dio? dio,
  })  : _tokenStorage = tokenStorage,
        _dio = dio ??
            Dio(BaseOptions(
              baseUrl: Env.apiBaseUrl,
              connectTimeout: const Duration(seconds: 15),
              receiveTimeout: const Duration(seconds: 20),
              headers: {'Accept': 'application/json'},
            )) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStorage.read();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (e, handler) {
          if (e.response?.statusCode == 401) {
            onUnauthorized?.call();
          }
          handler.next(e);
        },
      ),
    );
  }

  final Dio _dio;
  final TokenStorage _tokenStorage;

  /// GET returning the unwrapped `data` payload.
  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    return _run(() => _dio.get(path, queryParameters: query));
  }

  Future<dynamic> post(String path, {Object? body}) async {
    return _run(() => _dio.post(path, data: body));
  }

  Future<dynamic> patch(String path, {Object? body}) async {
    return _run(() => _dio.patch(path, data: body));
  }

  Future<dynamic> delete(String path) async {
    return _run(() => _dio.delete(path));
  }

  Future<dynamic> _run(Future<Response<dynamic>> Function() request) async {
    try {
      final res = await request();
      final data = res.data;
      // Laravel resources wrap the payload in a `data` key.
      if (data is Map && data.containsKey('data')) return data['data'];
      return data;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}

/// Provider wiring: on 401 we clear the token so [authControllerProvider]
/// (which watches token state) can send the user back to login.
final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(tokenStorageProvider);
  return ApiClient(
    tokenStorage: storage,
    onUnauthorized: () => storage.clear(),
  );
});
