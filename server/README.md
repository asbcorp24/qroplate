# QROplate Server (Laravel 9 + Bootstrap 5)

Backend/admin layer for QROplate.

## Roles and authentication

There are two roles.

### Super administrator

The super administrator is configured only through `.env` and is not stored in the database.

```env
SUPER_ADMIN_LOGIN=superadmin
SUPER_ADMIN_PASSWORD=change_this_password
```

The super administrator:

- signs in through `/login`
- sees all devices from all administrators
- can open the Administrators section
- can create tenant administrators
- can disable/enable tenant administrators
- can reset their passwords

### Administrator

Tenant administrators are stored in the `admins` table. They are created by the super administrator.

Each administrator:

- has their own login/password
- sees only devices with `owner_admin_id` equal to their admin id
- can add their own devices
- automatically becomes owner of a newly created device
- manages only their own channels and tariffs
- cannot open another administrator's device by direct URL

Passwords of tenant administrators are stored as Laravel password hashes, never as plain text.

## What is implemented

- Super-admin login from `.env`
- Database tenant administrators
- Per-admin device ownership
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

```bash
composer create-project laravel/laravel qroplate-server "9.*"
cd qroplate-server
```

Copy the contents of this repository `server/` directory over the Laravel project, then run:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure database and super administrator in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qroplate
DB_USERNAME=qroplate
DB_PASSWORD=change_me

SUPER_ADMIN_LOGIN=superadmin
SUPER_ADMIN_PASSWORD=change_this_password
```

Then:

```bash
php artisan migrate --seed
php artisan serve
```

Login:

```text
http://127.0.0.1:8000/login
```

Admin devices:

```text
http://127.0.0.1:8000/admin/devices
```

Super-admin administrators section:

```text
http://127.0.0.1:8000/admin/admins
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

### Synchronize actual ESP32 state

Flutter reads BLE STATUS from ESP32 and forwards it to:

```http
POST /api/devices/DEV-000001/telemetry
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
- Payments/sessions admin pages and reports
- Background cleanup for expired reservations
