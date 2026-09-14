# QROplate ESP32 firmware

ESP32 controller for paid timed equipment access.

## System architecture

The QR password and the payment authorization are two different security layers.

### 1. QR / BLE access

ESP32 shows a QR code on the 1.54 inch e-paper display.

The QR contains only data required to find and locally authorize access to this physical ESP32:

- `device_id`
- BLE MAC address
- local BLE access code

The access code is **not a payment token** and does **not** grant paid operating time. Its only purpose is to allow the Flutter application that scanned the QR to authenticate to this ESP32 over BLE.

Example QR:

```text
qroplate://connect?device=DEV-000001&mac=AA:BB:CC:DD:EE:FF&code=LOCAL_ACCESS_CODE
```

### 2. Payment

After BLE authentication the Flutter app obtains the tariff for `device_id` from the server and performs payment.

Only the backend may confirm that a payment is successful.

After successful payment the backend creates a one-time signed session authorization token.

### 3. Session packet sent from Flutter to ESP32

Flutter sends the paid session data to ESP32 over BLE.

Target logical payload:

```json
{
  "version": 1,
  "device_id": "DEV-000001",
  "payment_id": "pay_123456",
  "session_id": "sess_123456",
  "duration_sec": 900,
  "issued_at": 1789360000,
  "expires_at": 1789360120,
  "nonce": "b28f41d7c10a4ea1",
  "token": "SERVER_SIGNED_TOKEN"
}
```

Meaning:

- `device_id` - device for which payment was made
- `payment_id` - payment identifier on the backend
- `session_id` - unique operating session identifier
- `duration_sec` - paid operating time
- `issued_at` - token issue time
- `expires_at` - latest time at which ESP32 may accept the start command
- `nonce` - one-time value preventing replay
- `token` - server-generated cryptographic authorization/signature

ESP32 must not trust `duration_sec` by itself. The duration is accepted only if it is covered by a valid server token/signature.

## Production start sequence

```text
User
  |
  | scans QR
  v
Flutter
  |
  | device_id + MAC + local access code
  v
ESP32 BLE
  |
  | AUTH local access code
  v
Flutter <-----> Backend
  |               |
  | tariff        |
  | payment       |
  |               |
  |<-- signed paid session token
  |
  | BLE SESSION packet
  v
ESP32
  |
  | verify:
  | - local BLE authentication already completed
  | - device_id matches this ESP32
  | - token/signature is valid
  | - token has not expired
  | - nonce/session has not already been used
  | - duration is within allowed limits
  v
Relay ON
  |
  | DS3231 countdown
  v
Relay OFF
```

This means knowing the QR access code is not enough to start the equipment for free.

## Current firmware state

The firmware already provides:

1. QR generation on the e-paper display.
2. BLE discovery and local AUTH by access code.
3. Device INFO and STATUS characteristics.
4. DS3231 based session timing.
5. Relay control.
6. Session end time stored in NVS so an active session survives ESP32 reboot.
7. Remaining-time display while a session is running.
8. Plain-duration BLE starts are disabled.
9. PAYMENT/SESSION writes are routed through a dedicated token validator.
10. The validator currently fails closed until cryptographic verification is configured.
11. `SessionRequest` JSON model/parser is present for the paid-session packet.

Therefore the current firmware cannot start a new paid session from an unverified BLE packet. This is intentional.

## Hardware assumed

- MH-ET LIVE / ESP32 Dev Module
- MH-ET LIVE 1.54 inch B/W e-paper, 200x200
- DS3231 RTC module
- relay module

### Wiring

| Module | Signal | ESP32 GPIO |
|---|---|---:|
| E-paper | DIN/MOSI | 23 |
| E-paper | CLK/SCK | 18 |
| E-paper | CS | 5 |
| E-paper | DC | 17 |
| E-paper | RST | 16 |
| E-paper | BUSY | 4 |
| RTC DS3231 | SDA | 21 |
| RTC DS3231 | SCL | 22 |
| Relay | IN | 27 |

Power and ground must be common. Check the relay module input voltage and whether it is active HIGH or active LOW before connecting a real load.

## E-paper revision

`include/config.h` contains `EPD_MODEL`.

- `1` = `GxEPD2_154_D67`, SSD1681, 200x200
- `2` = older `GxEPD2_154` / GDEP015OC1 compatible panel

MH-ET LIVE sold more than one 1.54 inch revision. If the display stays white or shows garbage, switch `EPD_MODEL` and rebuild.

## Device provisioning

Edit/provision for every physical device:

```cpp
#define DEVICE_ID "DEV-000001"
#define BLE_DEVICE_NAME "QRPAY-000001"
#define DEVICE_ACCESS_CODE "CHANGE_ME"
```

For production:

- every device gets its own unique `DEVICE_ID`
- every device gets its own local BLE access code
- payment verification material is provisioned separately
- payment verification material is never included in the QR code

## BLE protocol

Service UUID:

`7a610000-71ce-4a7a-a9d8-6ad4bd36b000`

Characteristics:

| Characteristic | UUID suffix | Operation | Production purpose |
|---|---|---|---|
| INFO | `0001` | Read | device id, BLE name and MAC |
| AUTH | `0002` | Write | local access code from QR |
| PAYMENT/SESSION | `0003` | Write | signed paid-session packet |
| STATUS | `0004` | Read/Notify | state, session and remaining seconds |

Characteristic `0003` no longer accepts a decimal duration as a start command. A server-approved session packet is required.

## SessionRequest parser

`include/session_request.h` and `src/session_request.cpp` define and parse:

- protocol version
- device id
- payment/operation id
- session id
- duration
- issue time
- expiration time
- nonce
- server proof/token

Malformed or incomplete JSON is rejected before authorization is attempted.

## Session data stored by ESP32

For production the ESP32 should keep at least:

- active `session_id`
- `payment_id`
- accepted `nonce`
- session start epoch
- session end epoch
- paid duration
- current relay state

The last accepted identifiers/nonces must be persisted sufficiently to prevent replay after reboot.

## Status sent back to Flutter

ESP32 reports operation data back to the phone through the STATUS characteristic. Target status includes:

```json
{
  "device_id": "DEV-000001",
  "connected": true,
  "authenticated": true,
  "rtc_ok": true,
  "running": true,
  "session_id": "sess_123456",
  "payment_id": "pay_123456",
  "duration_sec": 900,
  "remaining_sec": 742,
  "relay": true,
  "event": "session_running"
}
```

Flutter can forward session start/stop/result information to the backend for history and the admin panel.

## Build with PlatformIO

Open the repository in VS Code + PlatformIO and run:

```bash
pio run
pio run -t upload
pio device monitor -b 115200
```

The project currently targets `esp32dev`. If the exact MH-ET LIVE ESP32 board needs a different PlatformIO board id, change only the `board =` line in `platformio.ini`.

Dependencies include GxEPD2, RTClib, QRCode and ArduinoJson.

## RTC behavior

On first boot after RTC battery loss, the firmware initializes DS3231 from firmware build time. Flutter/admin synchronization with trusted server time will be added for production.

## Planned implementation order

1. Connect `SessionRequest` parsing to the BLE PAYMENT/SESSION handler.
2. Add cryptographic verification of the server-approved session package.
3. Store `session_id`, `payment_id` and anti-replay nonce information in NVS.
4. Extend BLE STATUS with complete session operation data.
5. Flutter app: QR scanner, BLE authorization, backend API, payment and session UI.
6. Laravel API/admin: devices, tariffs, locations, payments, sessions and provisioning.
7. Device diagnostics and OTA update.
