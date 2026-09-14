import 'dart:async';
import 'dart:convert';
import 'package:flutter_reactive_ble/flutter_reactive_ble.dart';
import 'models.dart';
import 'permissions.dart';

class QroBleService {
  final FlutterReactiveBle ble = FlutterReactiveBle();

  static final Uuid serviceUuid = Uuid.parse('7a610000-71ce-4a7a-a9d8-6ad4bd36b000');
  static final Uuid infoUuid = Uuid.parse('7a610001-71ce-4a7a-a9d8-6ad4bd36b000');
  static final Uuid authUuid = Uuid.parse('7a610002-71ce-4a7a-a9d8-6ad4bd36b000');
  static final Uuid sessionUuid = Uuid.parse('7a610003-71ce-4a7a-a9d8-6ad4bd36b000');
  static final Uuid statusUuid = Uuid.parse('7a610004-71ce-4a7a-a9d8-6ad4bd36b000');

  StreamSubscription<ConnectionStateUpdate>? _connection;
  StreamSubscription<List<int>>? _statusSub;
  String? _deviceBleId;
  bool _readingStatus = false;

  final _status = StreamController<DeviceBleStatus>.broadcast();
  Stream<DeviceBleStatus> get statusStream => _status.stream;
  String? get connectedBleId => _deviceBleId;

  QualifiedCharacteristic _char(Uuid id) {
    final deviceId = _deviceBleId;
    if (deviceId == null) throw StateError('BLE не подключён');
    return QualifiedCharacteristic(serviceId: serviceUuid, characteristicId: id, deviceId: deviceId);
  }

  Future<void> connectAndAuthenticate(QrDevicePayload qr, {Duration timeout = const Duration(seconds: 15)}) async {
    await disconnect();
    await ensureBlePermissions();
    await ble.statusStream.firstWhere((s) => s == BleStatus.ready).timeout(timeout);

    final expectedName = 'QRPAY-${qr.deviceId.replaceFirst('DEV-', '')}'.toUpperCase();
    final target = await ble
        .scanForDevices(withServices: [serviceUuid], scanMode: ScanMode.lowLatency)
        .firstWhere((d) {
          final id = d.id.toUpperCase();
          final name = d.name.toUpperCase();
          if (qr.mac.isNotEmpty && id == qr.mac) return true;
          return name == expectedName;
        })
        .timeout(timeout);

    final connected = Completer<void>();
    _connection = ble
        .connectToDevice(
          id: target.id,
          servicesWithCharacteristicsToDiscover: {
            serviceUuid: [infoUuid, authUuid, sessionUuid, statusUuid]
          },
          connectionTimeout: timeout,
        )
        .listen((update) {
          if (update.connectionState == DeviceConnectionState.connected && !connected.isCompleted) {
            _deviceBleId = target.id;
            connected.complete();
          }
          if (update.connectionState == DeviceConnectionState.disconnected) {
            _deviceBleId = null;
          }
        }, onError: (Object e, StackTrace st) {
          if (!connected.isCompleted) connected.completeError(e, st);
        });

    await connected.future.timeout(timeout);

    try {
      await ble.requestMtu(deviceId: target.id, mtu: 247);
    } catch (_) {}

    final infoBytes = await ble.readCharacteristic(_char(infoUuid));
    final info = Map<String, dynamic>.from(jsonDecode(utf8.decode(infoBytes)) as Map);
    if ('${info['device_id']}' != qr.deviceId) {
      await disconnect();
      throw StateError('Подключён другой прибор: ${info['device_id']}');
    }

    _statusSub = ble.subscribeToCharacteristic(_char(statusUuid)).listen((bytes) async {
      try {
        final notice = Map<String, dynamic>.from(jsonDecode(utf8.decode(bytes)) as Map);
        final event = notice['event']?.toString();
        final full = await _readFullStatus();
        if (event != null && event.isNotEmpty) full.json['event'] = event;
        _status.add(full);
      } catch (_) {}
    });

    await ble.writeCharacteristicWithResponse(_char(authUuid), value: utf8.encode(qr.accessCode));

    final auth = await statusStream.firstWhere((s) {
      final event = '${s.json['event'] ?? ''}';
      return event == 'auth_ok' || event == 'auth_failed';
    }).timeout(const Duration(seconds: 5));

    if ('${auth.json['event']}' != 'auth_ok') {
      await disconnect();
      throw StateError('ESP32 отклонил локальный код доступа');
    }
  }

  Future<void> sendSessionCommand(Map<String, dynamic> command) async {
    final json = jsonEncode(command);
    await ble.writeCharacteristicWithResponse(_char(sessionUuid), value: utf8.encode('!'));
    const chunkSize = 16;
    for (var offset = 0; offset < json.length; offset += chunkSize) {
      final end = (offset + chunkSize < json.length) ? offset + chunkSize : json.length;
      final chunk = json.substring(offset, end);
      await ble.writeCharacteristicWithResponse(_char(sessionUuid), value: utf8.encode('+$chunk'));
    }
    await ble.writeCharacteristicWithResponse(_char(sessionUuid), value: utf8.encode('.'));
  }

  Future<DeviceBleStatus> _readFullStatus() async {
    if (_readingStatus) {
      await Future.delayed(const Duration(milliseconds: 80));
    }
    _readingStatus = true;
    try {
      final bytes = await ble.readCharacteristic(_char(statusUuid));
      return DeviceBleStatus(Map<String, dynamic>.from(jsonDecode(utf8.decode(bytes)) as Map));
    } finally {
      _readingStatus = false;
    }
  }

  Future<DeviceBleStatus> readStatus() => _readFullStatus();

  Future<void> disconnect() async {
    await _statusSub?.cancel();
    _statusSub = null;
    await _connection?.cancel();
    _connection = null;
    _deviceBleId = null;
  }

  Future<void> dispose() async {
    await disconnect();
    await _status.close();
  }
}
