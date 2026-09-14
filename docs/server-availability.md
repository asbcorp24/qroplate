# Server channel availability model

One physical ESP32 exposes four independent paid relay channels. The backend tracks availability per channel, not only per device.

## Channel states

Recommended states:

- `free` - available for a new order.
- `reserved` - temporarily locked while a user is paying / delivering the session command.
- `running` - ESP32 accepted the paid session and the relay is active.
- `error` - channel needs operator attention.
- `disabled` - administratively unavailable.

`finished` is best stored as a session history state rather than a persistent channel availability state. After a normal finish the channel returns to `free`.

## Device channel record

Each device has four server-side channel records:

```text
device_id
channel              1..4
name                 user-facing function name
profile/function id
state
enabled
current_session_id
current_payment_id
reserved_until
occupied_until
last_device_report_at
last_error
```

The admin panel may map different services to channels, for example:

```text
DEV-000001 / channel 1 -> Shower water
DEV-000001 / channel 2 -> Hair dryer
DEV-000001 / channel 3 -> Massage chair
DEV-000001 / channel 4 -> Locker
```

The QR identifies `DEV-000001`. Flutter then asks the backend which of its four channels exist, how they should look, their tariffs, and their current availability.

## Availability response

Example response for `GET /api/devices/DEV-000001`:

```json
{
  "device_id": "DEV-000001",
  "title": "Service point #1",
  "channels": [
    {
      "channel": 1,
      "title": "Shower",
      "state": "running",
      "available": false,
      "occupied_until": 1789360900,
      "remaining_sec": 510,
      "tariffs": []
    },
    {
      "channel": 2,
      "title": "Hair dryer",
      "state": "free",
      "available": true,
      "occupied_until": null,
      "remaining_sec": 0,
      "tariffs": []
    }
  ]
}
```

## Reservation before payment

The server must never allow two users to pay for the same free channel at the same time.

When the user presses Pay:

1. Backend begins a database transaction.
2. Backend locks the selected `device_id + channel` row.
3. Backend verifies that the channel is still `free`.
4. Backend changes it to `reserved` and writes `reserved_until` (for example 2 minutes).
5. Backend creates the payment order.
6. Other users immediately see this channel as unavailable.

If payment is cancelled or the reservation expires, the channel returns to `free`.

## After payment

After confirmed payment the backend creates a signed device session containing at least:

```text
device_id
relay_channel
payment_id
session_id
tariff_id
duration_sec
issued_at
expires_at
nonce
```

`relay_channel` must be part of the signed data.

Flutter sends the session package to ESP32 over BLE.

## ESP acknowledgement

ESP32 STATUS contains all four channels. Example:

```json
{
  "device_id": "DEV-000001",
  "active_count": 2,
  "channels": [
    {
      "channel": 1,
      "state": "running",
      "running": true,
      "remaining_sec": 510,
      "end_epoch": 1789360900,
      "duration_sec": 900,
      "session_id": "SES-001",
      "payment_id": "PAY-001"
    },
    {
      "channel": 2,
      "state": "free",
      "running": false,
      "remaining_sec": 0,
      "end_epoch": 0,
      "duration_sec": 0,
      "session_id": "",
      "payment_id": ""
    }
  ]
}
```

Flutter forwards this to the backend, for example:

```text
POST /api/devices/{device_id}/telemetry
```

The backend reconciles each channel:

- ESP says `running` -> server stores `running`, session/payment ids and `occupied_until = end_epoch`.
- ESP says `free` and the known session has ended -> server returns the channel to `free`.
- mismatching session ids -> store an audit/error event; do not silently overwrite a paid running session.

## Phone disconnects

ESP32 does not depend on the phone after session start. DS3231 and NVS continue the four timers independently.

The backend also knows `occupied_until`, so if the phone disappears it can still expose the channel as busy until the expected end time.

On the next QR scan Flutter reads current BLE STATUS and sends it to the backend, correcting stale server state if necessary.

For stronger always-online telemetry in a future hardware revision, ESP32 may additionally report through Wi-Fi/LTE, but that is not required for the current BLE architecture.

## Server availability rule

A channel is offered for purchase only when all are true:

```text
enabled = true
state = free
no unexpired reservation
occupied_until is null or <= server current time
```

For stale `running` rows where `occupied_until` is already in the past, the API may treat the channel as provisionally free, while preserving the old session in history. The next BLE telemetry report reconciles the physical state.

## Suggested tables

### devices

Physical ESP32 controllers.

### device_channels

Four configurable functions of each controller.

Important fields:

```text
id
device_id
channel
profile_id
title
description
image
state
enabled
current_session_id
current_payment_id
reserved_until
occupied_until
last_device_report_at
last_error
```

Unique index:

```text
UNIQUE(device_id, channel)
```

### tariffs

Tariffs belong to a channel/profile and define paid duration and price.

### payments

Payment-provider data and payment state.

### sessions

Immutable history of paid relay runs including device, channel, tariff, start/end, payment and result.

### device_events

Optional audit log for BLE acknowledgements, errors, reconnects and state reconciliation.
