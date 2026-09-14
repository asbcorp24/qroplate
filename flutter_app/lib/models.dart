import 'dart:convert';

class QrDevicePayload {
  final String deviceId;
  final String mac;
  final String accessCode;

  const QrDevicePayload({required this.deviceId, required this.mac, required this.accessCode});

  static QrDevicePayload parse(String raw) {
    final uri = Uri.parse(raw.trim());
    if (uri.scheme != 'qroplate' || uri.host != 'connect') {
      throw const FormatException('Неверный QR QROplate');
    }
    final device = uri.queryParameters['device'] ?? '';
    final mac = uri.queryParameters['mac'] ?? '';
    final code = uri.queryParameters['code'] ?? '';
    if (device.isEmpty || code.isEmpty) {
      throw const FormatException('QR не содержит device/code');
    }
    return QrDevicePayload(deviceId: device, mac: mac.toUpperCase(), accessCode: code);
  }
}

class Tariff {
  final String id;
  final String title;
  final int durationSec;
  final double price;
  final String currency;

  const Tariff({required this.id, required this.title, required this.durationSec, required this.price, required this.currency});

  factory Tariff.fromJson(Map<String, dynamic> j) => Tariff(
        id: '${j['id'] ?? ''}',
        title: '${j['title'] ?? ''}',
        durationSec: (j['duration_sec'] as num?)?.toInt() ?? 0,
        price: (j['price'] as num?)?.toDouble() ?? 0,
        currency: '${j['currency'] ?? 'RUB'}',
      );
}

class DeviceChannel {
  final int channel;
  final String name;
  final bool enabled;
  final String status;
  final DateTime? reservedUntil;
  final DateTime? occupiedUntil;
  final List<Tariff> tariffs;

  const DeviceChannel({required this.channel, required this.name, required this.enabled, required this.status, this.reservedUntil, this.occupiedUntil, required this.tariffs});
  bool get available => enabled && status == 'free';

  factory DeviceChannel.fromJson(Map<String, dynamic> j) => DeviceChannel(
        channel: (j['channel'] as num?)?.toInt() ?? 0,
        name: '${j['name'] ?? ''}',
        enabled: j['enabled'] == true,
        status: '${j['status'] ?? 'free'}',
        reservedUntil: DateTime.tryParse('${j['reserved_until'] ?? ''}'),
        occupiedUntil: DateTime.tryParse('${j['occupied_until'] ?? ''}'),
        tariffs: ((j['tariffs'] as List?) ?? const []).map((e) => Tariff.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
      );
}

class DeviceProfile {
  final String deviceId;
  final bool enabled;
  final String type;
  final String title;
  final String subtitle;
  final String description;
  final String location;
  final String imageUrl;
  final String accent;
  final String icon;
  final String layout;
  final String maintenanceMessage;
  final List<DeviceChannel> channels;

  const DeviceProfile({required this.deviceId, required this.enabled, required this.type, required this.title, required this.subtitle, required this.description, required this.location, required this.imageUrl, required this.accent, required this.icon, required this.layout, required this.maintenanceMessage, required this.channels});

  factory DeviceProfile.fromJson(Map<String, dynamic> j) {
    final theme = Map<String, dynamic>.from((j['theme'] as Map?) ?? const {});
    return DeviceProfile(
      deviceId: '${j['device_id'] ?? ''}',
      enabled: j['enabled'] == true,
      type: '${j['type'] ?? ''}',
      title: '${j['title'] ?? ''}',
      subtitle: '${j['subtitle'] ?? ''}',
      description: '${j['description'] ?? ''}',
      location: '${j['location'] ?? ''}',
      imageUrl: '${j['image_url'] ?? ''}',
      accent: '${theme['accent'] ?? '#0D6EFD'}',
      icon: '${theme['icon'] ?? ''}',
      layout: '${theme['layout'] ?? 'timer'}',
      maintenanceMessage: '${j['maintenance_message'] ?? ''}',
      channels: ((j['channels'] as List?) ?? const []).map((e) => DeviceChannel.fromJson(Map<String, dynamic>.from(e as Map))).toList(),
    );
  }
}

class PaymentStart {
  final String paymentId;
  final String status;
  final double amount;
  final String currency;
  final String provider;
  final String? testPayUrl;

  const PaymentStart({required this.paymentId, required this.status, required this.amount, required this.currency, required this.provider, this.testPayUrl});
  factory PaymentStart.fromJson(Map<String, dynamic> j) => PaymentStart(
        paymentId: '${j['payment_id']}', status: '${j['status']}', amount: (j['amount'] as num?)?.toDouble() ?? 0,
        currency: '${j['currency'] ?? 'RUB'}', provider: '${j['provider'] ?? ''}', testPayUrl: j['test_pay_url']?.toString());
}

class PaymentState {
  final String paymentId;
  final String status;
  final String? sessionId;
  const PaymentState({required this.paymentId, required this.status, this.sessionId});
  factory PaymentState.fromJson(Map<String, dynamic> j) => PaymentState(paymentId: '${j['payment_id']}', status: '${j['status']}', sessionId: j['session_id']?.toString());
}

class DeviceBleStatus {
  final Map<String, dynamic> json;
  const DeviceBleStatus(this.json);
  List<Map<String, dynamic>> get channels => ((json['channels'] as List?) ?? const []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
  String encode() => jsonEncode(json);
}
