# QROplate device protocol

## 1. QR purpose

The QR code is only for local access to one physical ESP32 device. It does not prove payment and it must never directly start the relay.

Example QR payload:

```text
qroplate://connect?device=DEV-000001&mac=AA:BB:CC:DD:EE:FF&code=LOCAL_ACCESS_CODE
```

Fields:

- `device` - permanent device identifier from the admin system.
- `mac` - BLE MAC address used by Flutter to find the correct ESP32.
- `code` - local BLE access code for this physical unit.

The QR must NOT contain the service title, description, tariff, image, button text or screen layout. These are controlled by the server so they can be changed without reflashing ESP32 or replacing the QR code.

## 2. Server-driven device profile

Every physical ESP32 is linked in the admin panel to a server-side device profile describing what the device controls and how the Flutter application should present it.

Example server response for `GET /api/devices/DEV-000001`:

```json
{
  "device_id": "DEV-000001",
  "enabled": true,
  "type": "washing_machine",
  "profile_id": "PROFILE-WASH-01",
  "title": "Стиральная машина №1",
  "subtitle": "Прачечная, 1 этаж",
  "description": "Выберите время работы и оплатите услугу",
  "image_url": "https://server.example/storage/devices/washing-machine.png",
  "theme": {
    "accent": "#1565C0",
    "icon": "washing_machine",
    "layout": "timer"
  },
  "controls": {
    "mode": "timed_relay",
    "show_duration": true,
    "show_price": true,
    "button_text": "Оплатить и запустить"
  },
  "tariffs": [
    {
      "id": "TARIFF-15",
      "title": "15 минут",
      "duration_sec": 900,
      "price": 100.00,
      "currency": "RUB"
    },
    {
      "id": "TARIFF-30",
      "title": "30 минут",
      "duration_sec": 1800,
      "price": 180.00,
      "currency": "RUB"
    }
  ]
}
```

This allows the same ESP32 firmware and the same Flutter application to be used for different equipment. The administrator changes the assignment on the server and the application automatically shows the correct screen after scanning the QR.

Typical device types may include:

- washing machine
- dryer
- shower
- sauna
- charging station
- massage chair
- locker
- water dispenser
- attraction/game machine
- parking barrier
- arbitrary timed relay equipment

The Flutter UI must primarily be driven by server fields rather than hardcoded per physical device.

## 3. Application flow after QR scan

1. Flutter scans the QR.
2. Flutter extracts `device_id`, BLE MAC and local BLE access code.
3. Flutter requests the device profile from the backend using `device_id`.
4. Backend returns the current device type, text, image, tariffs, controls and visual configuration.
5. Flutter builds the corresponding payment screen.
6. Flutter finds and connects to the ESP32 over BLE.
7. Flutter authenticates locally using the QR access code.
8. User selects a server-provided tariff or allowed duration.
9. Flutter creates a payment/order on the server.
10. After successful payment, the server creates a one-time signed session authorization.
11. Flutter forwards that session command to ESP32 over BLE.
12. ESP32 validates the command and starts the relay only when valid.

If the administrator changes the device assignment, title, price, image or available tariffs, the next QR scan immediately uses the new server configuration. The physical QR and ESP32 firmware do not need to change as long as the same `device_id` remains assigned.

## 4. BLE connection

1. Flutter finds the BLE device by MAC/device id.
2. Flutter connects to the ESP32.
3. Flutter writes the QR access code to AUTH.
4. ESP32 answers with `auth_ok` or `auth_failed`.
5. AUTH only opens access to the session command characteristic. It never starts the relay.

## 5. Payment flow

1. Flutter requests current tariff data from the web API using `device_id`.
2. User chooses the required operating time/tariff permitted by the device profile.
3. Flutter creates an order on the server.
4. Payment is completed through the selected payment provider.
5. Only the server confirms successful payment.
6. The server creates a one-time device session command.
7. Flutter forwards this server command to the ESP32 over BLE.
8. ESP32 validates the command.
9. Only after successful validation does ESP32 start the relay.

## 6. Paid session command

Logical JSON representation:

```json
{
  "version": 1,
  "device_id": "DEV-000001",
  "payment_id": "PAY-20260914-000123",
  "session_id": "SES-20260914-000123",
  "tariff_id": "TARIFF-15",
  "duration_sec": 900,
  "issued_at": 1789360000,
  "expires_at": 1789360120,
  "nonce": "7f21d4a98bc3412e",
  "token": "SERVER_GENERATED_AUTHENTICATOR"
}
```

Meaning:

- `version` - protocol version.
- `device_id` - device for which the command was issued.
- `payment_id` - confirmed payment identifier.
- `session_id` - unique operating session identifier.
- `tariff_id` - server tariff/profile option that was paid for.
- `duration_sec` - paid relay operating time.
- `issued_at` - server issue time in Unix seconds.
- `expires_at` - latest time at which ESP32 may accept the start command.
- `nonce` - unique one-time value preventing replay.
- `token` - server-generated authenticator covering all command fields.

The token must cover at least:

```text
device_id
payment_id
session_id
tariff_id
duration_sec
issued_at
expires_at
nonce
```

Changing any of these values must make validation fail.

## 7. ESP32 validation rules

Before enabling the relay ESP32 checks all of the following:

- BLE connection passed QR AUTH.
- `device_id` equals this physical unit.
- `duration_sec` is greater than zero and within the configured maximum.
- RTC is valid.
- current RTC time is within the allowed command window.
- `expires_at` has not passed.
- the server authenticator is valid for all fields.
- `nonce` has not already been consumed.
- `session_id` has not already been consumed.
- another session is not already active unless future policy explicitly permits extension.

Any failed check leaves the relay OFF.

ESP32 does not need to know the product title, image, price or UI layout. Those belong to the backend/Flutter layer. ESP32 only enforces device identity, authorization and timed relay operation.

## 8. Data stored by ESP32

For an accepted session ESP32 stores in NVS:

- `session_id`
- `payment_id`
- `tariff_id` when useful for diagnostics
- `nonce`
- `duration_sec`
- `started_at`
- `end_epoch`

This allows the paid session to survive ESP32 reboot. RTC is the time authority while the device is offline from the phone.

## 9. ESP32 -> Flutter status

STATUS characteristic should return/notify data equivalent to:

```json
{
  "device_id": "DEV-000001",
  "connected": true,
  "authenticated": true,
  "running": true,
  "relay": true,
  "rtc_ok": true,
  "payment_id": "PAY-20260914-000123",
  "session_id": "SES-20260914-000123",
  "duration_sec": 900,
  "remaining_sec": 742,
  "event": "session_started"
}
```

Expected events include:

- `connected`
- `auth_ok`
- `auth_failed`
- `command_received`
- `command_rejected`
- `session_started`
- `session_restored`
- `session_finished`
- `rtc_error`

## 10. Flutter -> server telemetry

Flutter should report device/session events back to the API whenever internet access is available:

- BLE connected.
- ESP authentication successful/failed.
- session command delivered.
- ESP accepted/rejected command.
- actual session start.
- current remaining time when useful.
- normal completion.
- unexpected disconnect.
- RTC/device error.

The server must treat ESP acknowledgement as device telemetry, while payment status always comes from the payment provider/server side.

## 11. Admin panel device assignment

The admin panel is the source of truth for what each physical unit represents.

For every device the administrator should be able to configure at least:

- device name
- device type/profile
- location
- title/subtitle/description
- image/icon
- enabled/disabled state
- tariff list
- fixed durations or user-selectable duration rules
- min/max duration when variable time is allowed
- button labels
- theme/accent/layout preset
- relay/session limits
- maintenance/offline message

A profile can be reused by many devices. For example, ten washing machines may use the same `washing_machine` profile while each has its own `device_id`, location and optional tariff override.

## 12. Important security separation

There are three separate credentials/concepts:

1. QR access code - only opens local BLE access to the physical ESP32.
2. Payment provider/server confirmation - proves the user actually paid.
3. One-time server device token - authorizes this specific ESP32 to run for this specific paid duration.

Knowing the QR access code must never be sufficient to start the relay.
