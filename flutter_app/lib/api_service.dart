import 'dart:convert';
import 'package:http/http.dart' as http;
import 'models.dart';

class ApiService {
  ApiService({required this.baseUrl, http.Client? client}) : client = client ?? http.Client();

  final String baseUrl;
  final http.Client client;

  Uri _u(String path) => Uri.parse('${baseUrl.replaceAll(RegExp(r'/$'), '')}$path');

  Future<Map<String, dynamic>> _json(http.Response r) async {
    final body = r.body.isEmpty ? <String, dynamic>{} : Map<String, dynamic>.from(jsonDecode(r.body) as Map);
    if (r.statusCode < 200 || r.statusCode >= 300) {
      throw ApiException(r.statusCode, '${body['error'] ?? body['message'] ?? 'HTTP ${r.statusCode}'}');
    }
    return body;
  }

  Future<DeviceProfile> getDevice(String deviceId) async {
    final r = await client.get(_u('/devices/$deviceId'));
    return DeviceProfile.fromJson(await _json(r));
  }

  Future<Map<String, dynamic>> reserve(String deviceId, int channel, String tariffId) async {
    final r = await client.post(
      _u('/devices/$deviceId/channels/$channel/reserve'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'tariff_id': tariffId}),
    );
    return _json(r);
  }

  Future<PaymentStart> createPayment(String reservationId, String tariffId) async {
    final r = await client.post(
      _u('/payments'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'reservation_id': reservationId, 'tariff_id': tariffId}),
    );
    return PaymentStart.fromJson(await _json(r));
  }

  Future<void> testPayByUrl(String url) async {
    final r = await client.post(Uri.parse(url));
    await _json(r);
  }

  Future<PaymentState> paymentStatus(String paymentId) async {
    final r = await client.get(_u('/payments/$paymentId'));
    return PaymentState.fromJson(await _json(r));
  }

  Future<Map<String, dynamic>> sessionCommand(String sessionId) async {
    final r = await client.get(_u('/sessions/$sessionId/command'));
    return _json(r);
  }

  Future<void> sendTelemetry(String deviceId, DeviceBleStatus status) async {
    final r = await client.post(
      _u('/devices/$deviceId/telemetry'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'channels': status.channels}),
    );
    await _json(r);
  }
}

class ApiException implements Exception {
  const ApiException(this.statusCode, this.message);
  final int statusCode;
  final String message;
  @override
  String toString() => message;
}
