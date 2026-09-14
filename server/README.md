# QROplate Server (Laravel 9 + Bootstrap 5)

Backend/admin layer for QROplate.

## What is implemented

- Devices
- 4 independent channels per ESP32
- Channel state: `free`, `reserved`, `running`, `error`, `disabled`
- Per-channel tariffs
- Payments table
- Sessions table
- API for Flutter device card
- Transactional channel reservation with `lockForUpdate()`
- BLE telemetry synchronization from Flutter
- Bootstrap admin pages for device/channel/tariff management

## Install

This `server/` directory contains the QROplate application layer. Create a clean Laravel 9 application and place/copy these files into it, or use this directory as the project root after installing the standard Laravel 9 skeleton.

Example:

```bash
composer create-project laravel/laravel qroplate-server "9.*"
cd qroplate-server
```

Copy the contents of this repository `server/` directory over the new Laravel project, then run:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database in `.env`, for example MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qroplate
DB_USERNAME=qroplate
DB_PASSWORD=change_me
```

Then:

```bash
php artisan migrate --seed
php artisan serve
```

Admin:

```text
http://127.0.0.1:8000/admin/devices
```

Initial seeded device:

```text
DEV-000001
```

## Main API

### Get device UI/profile and channel availability

```http
GET /api/devices/DEV-000001
```

Returns application text/theme plus all four channels and their tariffs/status.

### Reserve channel before payment

```http
POST /api/devices/DEV-000001/channels/2/reserve
Content-Type: application/json

{
  "tariff_id": "R2-15MIN"
}
```

The row is locked in a database transaction. If the channel is not free the server returns HTTP 409 with `channel_busy`.

A reservation currently expires after 2 minutes.

### Synchronize actual ESP32 state

Flutter reads BLE STATUS from ESP32 and forwards it:

```http
POST /api/devices/DEV-000001/telemetry
Content-Type: application/json

{
  "channels": [
    {
      "channel": 1,
      "running": true,
      "remaining_sec": 522,
      "end_epoch": 1789360900,
      "session_id": "SES-001",
      "payment_id": "PAY-001"
    },
    {
      "channel": 2,
      "running": false,
      "remaining_sec": 0,
      "end_epoch": 0,
      "session_id": "",
      "payment_id": ""
    }
  ]
}
```

## Availability model

`device_channels` is the current server-side projection of each relay.

- `free` - can be selected and reserved
- `reserved` - checkout/payment is in progress
- `running` - ESP32 has confirmed an active paid session
- `error` - manually/device marked fault
- `disabled` - disabled in admin

Important fields:

- `reserved_until`
- `occupied_until`
- `current_session_id`
- `current_payment_id`
- `last_device_report_at`

Because ESP32 has BLE only, Flutter acts as the transport for real device telemetry. The server still knows the expected busy period through `occupied_until` after the phone disconnects.

## Next server work

- Payment-provider integration/webhooks
- Endpoint that converts a paid reservation into a signed ESP32 session command
- API authentication/rate limiting
- Admin authentication
- Payments/sessions admin pages and reports
- Background cleanup for expired reservations
