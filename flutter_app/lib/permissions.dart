import 'dart:io';
import 'package:permission_handler/permission_handler.dart';

Future<void> ensureBlePermissions() async {
  if (!Platform.isAndroid) return;

  final result = await [
    Permission.bluetoothScan,
    Permission.bluetoothConnect,
    Permission.locationWhenInUse,
  ].request();

  final scan = result[Permission.bluetoothScan];
  final connect = result[Permission.bluetoothConnect];

  if (scan?.isPermanentlyDenied == true || connect?.isPermanentlyDenied == true) {
    throw StateError('Разрешение Bluetooth запрещено. Разрешите его в настройках приложения.');
  }

  if ((scan != null && scan.isDenied) || (connect != null && connect.isDenied)) {
    throw StateError('Для подключения к прибору нужно разрешение Bluetooth.');
  }
}
