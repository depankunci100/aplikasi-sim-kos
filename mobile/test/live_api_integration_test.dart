import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Live Flutter Dio -> Laravel API Integration Test', () {
    final dio = Dio(
      BaseOptions(
        baseUrl: 'http://127.0.0.1:8000/api/v1',
        connectTimeout: const Duration(seconds: 5),
        receiveTimeout: const Duration(seconds: 5),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    test('Flutter Dio can login to local Laravel server and receive Sanctum token', () async {
      try {
        final response = await dio.post('/auth/login', data: {
          'email': 'penyewa@simkos.id',
          'password': 'Password123!',
        });

        expect(response.statusCode, 200);
        expect(response.data['success'], true);
        expect(response.data['data']['token'], isNotNull);
        expect(response.data['data']['user']['email'], 'penyewa@simkos.id');

        final token = response.data['data']['token'] as String;

        // Query invoices with Bearer token
        final invoicesResp = await dio.get(
          '/invoices',
          options: Options(headers: {'Authorization': 'Bearer $token'}),
        );

        expect(invoicesResp.statusCode, 200);
        expect(invoicesResp.data['success'], true);
        expect(invoicesResp.data['data'], isNotNull);
      } on DioException catch (e) {
        fail('Dio request failed: ${e.message} - ${e.response?.data}');
      }
    });

    test('Flutter Dio receives 401 when token is invalid or missing', () async {
      try {
        await dio.get('/auth/profile');
        fail('Expected DioException 401');
      } on DioException catch (e) {
        expect(e.response?.statusCode, 401);
      }
    });
  });
}
