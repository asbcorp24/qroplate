# QROplate ESP32 firmware

ESP32 4-channel controller for paid timed equipment access.

## Main idea

One physical ESP32 has one QR code and four independent relay outputs. The QR identifies the controller. After scanning it, Flutter asks the server what this controller currently represents and which paid actions/services are available.

The server decides which relay channel belongs to each action. ESP32 does not store service titles, prices, images or UI layouts.

Example server mapping:

```text
relay 1 -> water
relay 2 -> lighting
relay 3 -> motor
relay 4 -> auxiliary function
```

The same physical controller can be reassigned later from the admin panel without replacing the QR or reflashing ESP32.

## Security layers

The QR contains only:

- `device_id`
- BLE MAC address
- local BLE access code

The QR access code only opens local BLE access. It never authorizes paid operating time.

After successful payment the backend creates a one-time signed session authorization. The paid packet contains the selected `relay_channel`, and that field must be protected by the server token together with duration, payment id and session id.

## Paid session packet

Target logical payload:

```json
{
  "version": 1,
  "device_id": "DEV-000001",
  "payment_id": "PAY-000123",
  "session_id": "SES-000123",
  "relay_channel": 3,
  "duration_sec": 900,
  "issued_at": 1789360000,
  "expires_at": 1789360120,
  "nonce": "b28f41d7c10a4ea1",
  "token": "SERVER_SIGNED_TOKEN"
}
```

Changing `relay_channel` or `duration_sec` must make token validation fail.

## Current firmware state

The firmware currently provides:

1. QR generation on the 1.54 inch e-paper display.
2. BLE discovery and local AUTH by access code.
3. Device INFO and STATUS characteristics.
4. DS3231 based session timing.
5. Four relay outputs.
6. Selected relay channel stored in NVS with the active session end time.
7. Active paid session restored after ESP32 reboot.
8. Remaining-time display while a session is running.
9. Plain-duration BLE starts disabled.
10. PAYMENT/SESSION writes routed through a fail-closed token validator.
11. `SessionRequest` parser with mandatory `relay_channel` 1..4.

Until server signature verification is configured, new paid session commands are rejected intentionally.

## Hardware

- MH-ET LIVE / ESP32 Dev Module
- MH-ET LIVE 1.54 inch B/W e-paper, 200x200
- DS3231 RTC module
- 4-channel relay module or four separate relay modules

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
| Relay 1 | IN1 | 25 |
| Relay 2 | IN2 | 26 |
| Relay 3 | IN3 | 27 |
| Relay 4 | IN4 | 33 |

All grounds must be common. Verify whether the relay board is active HIGH or active LOW and configure `RELAY_ACTIVE_LEVEL` / `RELAY_IDLE_LEVEL` accordingly. Do not power a relay coil bank from ESP32 3.3 V unless the relay module is explicitly designed for it.

## Server-driven functions

A device profile may expose one or more functions. Each function should include at least:

```json
{
  "id": "FUNC-MOTOR",
  "title": "Мотор",
  "description": "Запуск двигателя",
  "relay_channel": 3,
  "image_url": "...",
  "button_text": "Оплатить и запустить",
  "tariffs": [
    {
      "id": "TARIFF-15",
      "duration_sec": 900,
      "price": 100.00,
      "currency": "RUB"
    }
  ]
}
```

Flutter renders the list/cards returned by the backend. The user chooses a function and tariff, pays, and receives a signed command authorizing exactly that relay channel for exactly the paid time.

## Session behavior

Current firmware permits one active paid session at a time per controller. The selected channel is restored after reboot and all non-selected relays are forced OFF.

This is intentional for the first version because it gives predictable fail-safe behavior. If later one ESP32 must serve four customers simultaneously, the session manager can be expanded to four independent timers without changing the server concept.

## BLE protocol

Service UUID:

`7a610000-71ce-4a7a-a9d8-6ad4bd36b000`

| Characteristic | UUID suffix | Operation | Purpose |
|---|---|---|---|
| INFO | `0001` | Read | device id, BLE name, MAC, `relay_count=4` |
| AUTH | `0002` | Write | local access code from QR |
| PAYMENT/SESSION | `0003` | Write | signed paid-session packet |
| STATUS | `0004` | Read/Notify | state, active relay channel and remaining seconds |

STATUS includes `relay_channel` and `relay_count`.

## E-paper revision

`include/config.h` contains `EPD_MODEL`.

- `1` = `GxEPD2_154_D67`, SSD1681, 200x200
- `2` = older `GxEPD2_154` / GDEP015OC1 compatible panel

## Device provisioning

Each physical controller must have its own:

```cpp
#define DEVICE_ID "DEV-000001"
#define BLE_DEVICE_NAME "QRPAY-000001"
#define DEVICE_ACCESS_CODE "CHANGE_ME"
```

Payment verification material is provisioned separately and is never included in QR/BLE INFO.

## Build with PlatformIO

```bash
pio run
pio run -t upload
pio device monitor -b 115200
```

The project targets `esp32dev` and uses GxEPD2, RTClib, QRCode and ArduinoJson.
