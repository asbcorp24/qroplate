import 'dart:async';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'api_service.dart';
import 'ble_service.dart';
import 'models.dart';

const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api',
);

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const QroplateApp());
}

class QroplateApp extends StatelessWidget {
  const QroplateApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'QROplate',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(useMaterial3: true, colorSchemeSeed: Colors.blue),
      home: const ScanPage(),
    );
  }
}

class ScanPage extends StatefulWidget {
  const ScanPage({super.key});
  @override
  State<ScanPage> createState() => _ScanPageState();
}

class _ScanPageState extends State<ScanPage> {
  final scanner = MobileScannerController(formats: const [BarcodeFormat.qrCode]);
  bool busy = false;
  String? error;

  Future<void> _accept(String raw) async {
    if (busy) return;
    setState(() { busy = true; error = null; });
    await scanner.stop();

    final ble = QroBleService();
    try {
      final qr = QrDevicePayload.parse(raw);
      final api = ApiService(baseUrl: apiBaseUrl);
      final profile = await api.getDevice(qr.deviceId);
      if (!profile.enabled) throw StateError(profile.maintenanceMessage.isNotEmpty ? profile.maintenanceMessage : 'Прибор отключён');
      await ble.connectAndAuthenticate(qr);
      if (!mounted) return;
      await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => DevicePage(qr: qr, initialProfile: profile, api: api, ble: ble),
      ));
    } catch (e) {
      await ble.dispose();
      if (mounted) setState(() => error = e.toString().replaceFirst('Bad state: ', ''));
    } finally {
      if (mounted) {
        setState(() => busy = false);
        await scanner.start();
      }
    }
  }

  @override
  void dispose() {
    scanner.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Stack(children: [
          MobileScanner(
            controller: scanner,
            onDetect: (capture) {
              final raw = capture.barcodes.firstOrNull?.rawValue;
              if (raw != null && raw.startsWith('qroplate://')) _accept(raw);
            },
          ),
          Center(child: Container(width: 260, height: 260, decoration: BoxDecoration(border: Border.all(color: Colors.white, width: 3), borderRadius: BorderRadius.circular(24)))),
          Positioned(left: 20, right: 20, top: 24, child: Column(children: [
            const Text('QROplate', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            Text(busy ? 'Подключение к прибору…' : 'Наведите камеру на QR-код', style: const TextStyle(color: Colors.white70)),
          ])),
          if (busy) const Center(child: CircularProgressIndicator()),
          if (error != null) Positioned(left: 16, right: 16, bottom: 24, child: Material(color: Colors.red.shade700, borderRadius: BorderRadius.circular(12), child: Padding(padding: const EdgeInsets.all(14), child: Text(error!, style: const TextStyle(color: Colors.white))))),
        ]),
      ),
    );
  }
}

class DevicePage extends StatefulWidget {
  const DevicePage({super.key, required this.qr, required this.initialProfile, required this.api, required this.ble});
  final QrDevicePayload qr;
  final DeviceProfile initialProfile;
  final ApiService api;
  final QroBleService ble;

  @override
  State<DevicePage> createState() => _DevicePageState();
}

class _DevicePageState extends State<DevicePage> {
  late DeviceProfile profile;
  StreamSubscription<DeviceBleStatus>? statusSub;
  DeviceBleStatus? bleStatus;
  String? working;
  String? message;
  DateTime lastTelemetry = DateTime.fromMillisecondsSinceEpoch(0);

  Color get accent {
    var hex = profile.accent.replaceAll('#', '');
    if (hex.length == 6) hex = 'FF$hex';
    return Color(int.tryParse(hex, radix: 16) ?? 0xFF0D6EFD);
  }

  @override
  void initState() {
    super.initState();
    profile = widget.initialProfile;
    statusSub = widget.ble.statusStream.listen((s) async {
      if (!mounted) return;
      setState(() => bleStatus = s);
      final event = '${s.json['event'] ?? ''}';
      final now = DateTime.now();
      if (event.isNotEmpty || now.difference(lastTelemetry).inSeconds >= 3) {
        lastTelemetry = now;
        try { await widget.api.sendTelemetry(profile.deviceId, s); } catch (_) {}
      }
      if (event == 'session_finished') await _reload();
    });
    _syncInitial();
  }

  Future<void> _syncInitial() async {
    try {
      final s = await widget.ble.readStatus();
      if (mounted) setState(() => bleStatus = s);
      await widget.api.sendTelemetry(profile.deviceId, s);
      await _reload();
    } catch (_) {}
  }

  Future<void> _reload() async {
    try {
      final p = await widget.api.getDevice(profile.deviceId);
      if (mounted) setState(() => profile = p);
    } catch (_) {}
  }

  Future<PaymentState> _waitPayment(String id) async {
    for (var i = 0; i < 60; i++) {
      final state = await widget.api.paymentStatus(id);
      if (state.status == 'paid' || state.status == 'failed' || state.status == 'cancelled') return state;
      await Future.delayed(const Duration(seconds: 2));
    }
    throw StateError('Истекло время ожидания оплаты');
  }

  Future<void> _buy(DeviceChannel channel, Tariff tariff) async {
    if (working != null) return;
    setState(() { working = 'Бронируем ${channel.name}…'; message = null; });
    try {
      final reservation = await widget.api.reserve(profile.deviceId, channel.channel, tariff.id);
      setState(() => working = 'Создаём оплату…');
      final payment = await widget.api.createPayment('${reservation['reservation_id']}', tariff.id);

      if (payment.provider == 'test' && payment.testPayUrl != null) {
        setState(() => working = 'Тестовая оплата…');
        await widget.api.testPayByUrl(payment.testPayUrl!);
      } else {
        setState(() => working = 'Ожидаем подтверждение оплаты…');
      }

      final paid = await _waitPayment(payment.paymentId);
      if (paid.status != 'paid' || paid.sessionId == null) throw StateError('Оплата не подтверждена: ${paid.status}');

      setState(() => working = 'Запускаем устройство…');
      final command = await widget.api.sessionCommand(paid.sessionId!);
      await widget.ble.sendSessionCommand(command);

      final accepted = await widget.ble.statusStream.firstWhere((s) {
        final event = '${s.json['event'] ?? ''}';
        if (event == 'session_rejected') return true;
        return s.channels.any((c) => c['channel'] == channel.channel && c['running'] == true);
      }).timeout(const Duration(seconds: 8));

      if ('${accepted.json['event'] ?? ''}' == 'session_rejected') throw StateError('ESP32 отклонил команду сессии');
      await widget.api.sendTelemetry(profile.deviceId, accepted);
      await _reload();
      setState(() => message = '${channel.name}: запуск выполнен');
    } catch (e) {
      setState(() => message = 'Ошибка: ${e.toString().replaceFirst('Bad state: ', '')}');
      await _reload();
    } finally {
      if (mounted) setState(() => working = null);
    }
  }

  String _statusText(DeviceChannel c) {
    switch (c.status) {
      case 'free': return 'Свободно';
      case 'reserved': return 'Оплата / резерв';
      case 'running': return 'Занято';
      case 'error': return 'Ошибка';
      case 'disabled': return 'Отключено';
      default: return c.status;
    }
  }

  Color _statusColor(DeviceChannel c) {
    switch (c.status) {
      case 'free': return Colors.green;
      case 'reserved': return Colors.orange;
      case 'running': return Colors.blue;
      case 'error': return Colors.red;
      default: return Colors.grey;
    }
  }

  @override
  void dispose() {
    statusSub?.cancel();
    widget.ble.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(profile.title), backgroundColor: accent, foregroundColor: Colors.white, actions: [IconButton(onPressed: _reload, icon: const Icon(Icons.refresh))]),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          if (profile.imageUrl.isNotEmpty) ClipRRect(borderRadius: BorderRadius.circular(20), child: Image.network(profile.imageUrl, height: 180, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox.shrink())),
          if (profile.imageUrl.isNotEmpty) const SizedBox(height: 16),
          Text(profile.title, style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
          if (profile.subtitle.isNotEmpty) Text(profile.subtitle, style: theme.textTheme.titleMedium),
          if (profile.location.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Row(children: [const Icon(Icons.place_outlined, size: 18), const SizedBox(width: 4), Expanded(child: Text(profile.location))])),
          if (profile.description.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 10), child: Text(profile.description)),
          if (message != null) Padding(padding: const EdgeInsets.only(top: 12), child: Material(color: message!.startsWith('Ошибка') ? Colors.red.shade50 : Colors.green.shade50, borderRadius: BorderRadius.circular(12), child: Padding(padding: const EdgeInsets.all(12), child: Text(message!)))),
          if (working != null) Padding(padding: const EdgeInsets.symmetric(vertical: 14), child: Row(children: [const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)), const SizedBox(width: 12), Expanded(child: Text(working!))])),
          const SizedBox(height: 18),
          ...profile.channels.map((c) => Card(
            margin: const EdgeInsets.only(bottom: 14),
            child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [CircleAvatar(backgroundColor: accent.withValues(alpha: .12), foregroundColor: accent, child: Text('R${c.channel}')), const SizedBox(width: 12), Expanded(child: Text(c.name, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600))), Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), decoration: BoxDecoration(color: _statusColor(c).withValues(alpha: .12), borderRadius: BorderRadius.circular(20)), child: Text(_statusText(c), style: TextStyle(color: _statusColor(c), fontWeight: FontWeight.w600)))]),
              if (c.status == 'running' && c.occupiedUntil != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text('Занято до ${TimeOfDay.fromDateTime(c.occupiedUntil!.toLocal()).format(context)}')),
              if (c.available) ...[
                const Divider(height: 28),
                if (c.tariffs.isEmpty) const Text('Тарифы не настроены', style: TextStyle(color: Colors.grey)),
                ...c.tariffs.map((t) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(t.title),
                  subtitle: Text('${(t.durationSec / 60).round()} мин'),
                  trailing: FilledButton(style: FilledButton.styleFrom(backgroundColor: accent), onPressed: working == null ? () => _buy(c, t) : null, child: Text('${t.price.toStringAsFixed(0)} ${t.currency == 'RUB' ? '₽' : t.currency}')),
                )),
              ],
            ])),
          )),
          if (bleStatus != null) Text('BLE подключён · активных каналов: ${bleStatus!.json['active_count'] ?? 0}', textAlign: TextAlign.center, style: theme.textTheme.bodySmall?.copyWith(color: Colors.grey)),
          const SizedBox(height: 24),
        ]),
      ),
    );
  }
}

extension _FirstOrNull<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
