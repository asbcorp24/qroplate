# QROplate Flutter app

Клиентское приложение QROplate для Android/iOS.

## Реализовано

- сканирование `qroplate://` QR через камеру;
- загрузка карточки прибора и оформления из Laravel API;
- отображение 4 независимых каналов и их `free/reserved/running/error/disabled` состояния;
- отображение тарифов каждого свободного канала;
- BLE поиск ESP32 по QROplate Service UUID;
- проверка `device_id` через INFO characteristic;
- BLE AUTH локальным кодом из QR;
- подписка на STATUS;
- резервирование канала на сервере;
- создание платежа;
- тестовый payment flow при `PAYMENT_PROVIDER=test`;
- получение server-signed session command;
- передача команды ESP32 по BLE;
- ожидание фактического запуска канала;
- отправка BLE telemetry обратно в Laravel.

## Пакеты

- `mobile_scanner` — QR;
- `flutter_reactive_ble` — BLE;
- `http` — Laravel API.

## Создание platform skeleton

В каталоге `flutter_app` выполните:

```bash
flutter create --platforms=android,ios --org ru.qroplate .
flutter pub get
```

Если Flutter предложит заменить `lib/main.dart` или `pubspec.yaml`, оставьте версии из репозитория QROplate.

## Android permissions

В `android/app/src/main/AndroidManifest.xml` перед `<application>` должны быть:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-feature android:name="android.hardware.bluetooth_le" android:required="true" />

<uses-permission android:name="android.permission.BLUETOOTH" android:maxSdkVersion="30" />
<uses-permission android:name="android.permission.BLUETOOTH_ADMIN" android:maxSdkVersion="30" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" android:maxSdkVersion="30" />
<uses-permission android:name="android.permission.BLUETOOTH_SCAN" android:usesPermissionFlags="neverForLocation" />
<uses-permission android:name="android.permission.BLUETOOTH_CONNECT" />
```

Для локального HTTP сервера на Android во время разработки в `<application>` можно временно добавить:

```xml
android:usesCleartextTraffic="true"
```

В production используйте HTTPS и уберите cleartext.

## iOS permissions

В `ios/Runner/Info.plist` добавьте:

```xml
<key>NSCameraUsageDescription</key>
<string>Камера используется для сканирования QR-кода прибора.</string>
<key>NSBluetoothAlwaysUsageDescription</key>
<string>Bluetooth используется для подключения к контроллеру QROplate.</string>
<key>NSBluetoothPeripheralUsageDescription</key>
<string>Bluetooth используется для подключения к контроллеру QROplate.</string>
```

## API URL

Приложение использует `API_BASE_URL` через `--dart-define`.

Android emulator:

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

Физический телефон в локальной сети:

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.100/api
```

Production:

```bash
flutter build appbundle --dart-define=API_BASE_URL=https://pay.example.ru/api
```

На Laravel `APP_URL` тоже должен быть адресом, доступным телефону, иначе `test_pay_url` будет содержать localhost.

## Тестовый сценарий

Laravel `.env`:

```env
PAYMENT_PROVIDER=test
DEVICE_TOKEN_SECRET=YOUR_LONG_SECRET
APP_URL=http://192.168.1.100
```

ESP32 `include/config.h`:

```cpp
#define DEVICE_TOKEN_KEY "YOUR_LONG_SECRET"
```

Последовательность:

1. Запустить Laravel.
2. Прошить ESP32 и открыть Serial Monitor.
3. Запустить Flutter на телефоне.
4. Отсканировать QR с e-paper.
5. Flutter загрузит server-driven карточку прибора.
6. Приложение найдёт ESP32, проверит `device_id` и выполнит AUTH.
7. Выбрать свободный канал и тариф.
8. В test provider Flutter автоматически подтверждает тестовую оплату.
9. Laravel создаёт session и подписывает команду HMAC-SHA256.
10. Flutter передаёт session JSON в ESP32.
11. ESP32 проверяет подпись/RTC/replay/channel и включает нужное реле.
12. STATUS отправляется в Laravel, канал становится `running`.
13. По окончании таймера ESP выключает реле, а следующая telemetry переводит канал в `free`.

## Важное про 4 пользователей

Каждый пользователь сканирует один и тот же QR конкретного ESP32. Сервер возвращает четыре независимых канала. Если R1 занят, другой пользователь может оплатить R2/R3/R4. Серверная транзакционная бронь не позволяет двум пользователям одновременно оплатить один канал.

## Production payment

Текущий Flutter flow полностью работает с `PAYMENT_PROVIDER=test`. Для реального провайдера сервер должен возвращать checkout/confirmation URL или SDK parameters. После реального webhook приложение уже умеет ждать `payment.status == paid`, получать `session_id`, server command и запускать ESP32 — эту часть менять не потребуется.
