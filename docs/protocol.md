# QROplate device protocol

## 1. Physical controller

One ESP32 controller has:

- one permanent `device_id`
- one QR code
- one BLE interface
- one DS3231 RTC
- one e-paper display
- four relay outputs

Relay channels are numbered `1..4`.

The QR identifies the physical controller only. It does not describe what any relay currently controls.

Example QR:

```text
qroplate://connect?device=DEV-000001&mac=AA:BB:CC:DD:EE:FF&code=LOCAL_ACCESS_CODE
```

## 2. Server is the source of truth

After scanning the QR, Flutter requests the current configuration for `device_id` from the backend.

The backend decides:

- what the controller represents
- which functions/services are visible
- title, description, images and UI layout
- which relay channel belongs to each function
- available tariffs and durations
- whether the function/device is enabled

Example:

```json
{
  "device_id": "DEV-000001",
  "title": "Пост самообслуживания №1",
  "functions": [
    {
      "id": "FUNC-WATER",
      "title": "Вода",
      "relay_channel": 1,
      "icon": "water_drop",
      "tariffs": [
        {"id":"T1","duration_sec":300,"price":50,"currency":"RUB"}
      ]
    },
    {
      "id": "FUNC-LIGHT",
      "title": "Освещение",
      "relay_channel": 2,
      "icon": "lightbulb",
      "tariffs": [
        {"id":"T2","duration_sec":900,"price":30,"currency":"RUB"}
      ]
    },
    {
      "id": "FUNC-MOTOR",
      "title": "Мотор",
      "relay_channel": 3,
      "icon": "settings",
      "tariffs": [
        {"id":"T3","duration_sec":600,"price":100,"currency":"RUB"}
      ]
    },
    {
      "id": "FUNC-AUX",
      "title": "Дополнительная функция",
      "relay_channel": 4,
      "icon": "bolt",
      "tariffs": [
        {"id":"T4","duration_sec":300,"price":40,"currency":"RUB"}
      ]
    }
  ]
}
```

Changing this mapping in the admin panel must not require changing the QR or reflashing ESP32.

## 3. BLE AUTH

1. Flutter scans QR.
2. Flutter connects to the BLE MAC from QR.
3. Flutter writes the QR local access code to AUTH.
4. ESP32 returns `auth_ok` or `auth_failed`.

QR AUTH only opens local access. It never starts a relay.

## 4. Payment flow

1. User chooses a server-provided function.
2. User chooses a tariff/duration.
3. Flutter creates the payment on the backend.
4. Payment provider/backend confirms payment.
5. Backend creates a one-time signed session command.
6. Flutter forwards it to ESP32 over BLE.
7. ESP32 validates it.
8. ESP32 activates only the authorized relay channel.

## 5. Paid session command

```json
{
  "version": 1,
  "device_id": "DEV-000001",
  "payment_id": "PAY-000123",
  "session_id": "SES-000123",
  "function_id": "FUNC-MOTOR",
  "tariff_id": "T3",
  "relay_channel": 3,
  "duration_sec": 600,
  "issued_at": 1789360000,
  "expires_at": 1789360120,
  "nonce": "7f21d4a98bc3412e",
  "token": "SERVER_GENERATED_AUTHENTICATOR"
}
```

`relay_channel` is security-sensitive. The server token must cover it together with at least:

```text
device_id
payment_id
session_id
function_id
tariff_id
relay_channel
duration_sec
issued_at
expires_at
nonce
```

If the phone changes relay `1` to `4`, validation must fail.

## 6. ESP32 validation

Before enabling any relay ESP32 checks:

- QR/BLE AUTH succeeded
- `device_id` matches this controller
- `relay_channel` is 1..4
- duration is valid
- RTC is valid
- token is inside its validity window
- server authenticator is valid
- nonce was not already consumed
- session id was not already consumed
- no conflicting session is active

Any failure keeps all relays OFF.

## 7. Relay safety behavior

Configured GPIOs:

```text
Relay 1 -> GPIO25
Relay 2 -> GPIO26
Relay 3 -> GPIO27
Relay 4 -> GPIO33
```

Current firmware policy is one active paid session at a time per controller.

When a session starts:

1. all four relays are forced OFF
2. selected relay channel is turned ON
3. DS3231 determines session end time
4. channel + end time are saved in NVS
5. on reboot the selected paid channel is restored only if the RTC says the session is still active
6. when time expires all relays are forced OFF

This first-version policy prevents accidental simultaneous activation of unrelated loads. Four independent simultaneous timers can be added later if the business case requires it.

## 8. BLE INFO / STATUS

INFO contains at least:

```json
{
  "device_id":"DEV-000001",
  "name":"QRPAY-000001",
  "mac":"AA:BB:CC:DD:EE:FF",
  "relay_count":4
}
```

STATUS contains the selected channel:

```json
{
  "device_id":"DEV-000001",
  "connected":true,
  "authenticated":true,
  "rtc_ok":true,
  "running":true,
  "relay_channel":3,
  "relay_count":4,
  "remaining_sec":412,
  "event":"session_started"
}
```

## 9. Admin panel model

A useful backend model is:

```text
Device
  -> DeviceProfile
  -> Functions (1..N)
       -> relay_channel (1..4)
       -> Tariffs
```

This lets one QR open a Flutter page with one or several payable functions, while each function activates the relay selected in the admin panel.

## 10. Security separation

There are three separate concepts:

1. QR access code: local access to this physical ESP32.
2. Payment confirmation: backend knows money was successfully paid.
3. Signed session command: authorizes a specific controller, relay channel and duration.

Knowing the QR code alone must never be enough to activate any relay.
