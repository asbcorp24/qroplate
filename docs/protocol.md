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

## 2. BLE connection

1. Flutter scans the QR.
2. Flutter finds the BLE device by MAC/device id.
3. Flutter connects to the ESP32.
4. Flutter writes the QR access code to AUTH.
5. ESP32 answers with `auth_ok` or `auth_failed`.
6. AUTH only opens access to the session command characteristic. It never starts the relay.

## 3. Payment flow

1. Flutter requests current tariff data from the web API using `device_id`.
2. User chooses the required operating time.
3. Flutter creates an order on the server.
4. Payment is completed through the selected payment provider.
5. Only the server confirms successful payment.
6. The server creates a one-time device session command.
7. Flutter forwards this server command to the ESP32 over BLE.
8. ESP32 validates the command.
9. Only after successful validation does ESP32 start the relay.

## 4. Paid session command

Logical JSON representation:

```json
{
  "version": 1,
  "device_id": "DEV-000001",
  "payment_id": "PAY-20260914-000123",
  "session_id": "SES-20260914-000123",
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
- `duration_sec` - paid relay operating time.
- `issued_at` - server issue time in Unix seconds.
- `expires_at` - short validity window for delivering the command to ESP32.
- `nonce` - unique one-time value preventing replay.
- `token` - server-generated authenticator covering all command fields.

The token must cover at least:

```text
device_id
payment_id
session_id
duration_sec
issued_at
expires_at
nonce
```

Changing any of these values must make validation fail.

## 5. ESP32 validation rules

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

## 6. Data stored by ESP32

For an accepted session ESP32 stores in NVS:

- `session_id`
- `payment_id`
- `nonce`
- `duration_sec`
- `started_at`
- `end_epoch`

This allows the paid session to survive ESP32 reboot. RTC is the time authority while the device is offline from the phone.

## 7. ESP32 -> Flutter status

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

## 8. Flutter -> server telemetry

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

## 9. Important security separation

There are three separate credentials/concepts:

1. QR access code - only opens local BLE access to the physical ESP32.
2. Payment provider/server confirmation - proves the user actually paid.
3. One-time server device token - authorizes this specific ESP32 to run for this specific paid duration.

Knowing the QR access code must never be sufficient to start the relay.
