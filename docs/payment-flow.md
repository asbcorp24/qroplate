# QROplate payment flow

## Environment

Laravel server:

```env
PAYMENT_PROVIDER=test
RESERVATION_MINUTES=2
SESSION_COMMAND_TTL_SECONDS=120
DEVICE_TOKEN_SECRET=replace_with_long_random_secret
```

For a bench ESP32 the value of `DEVICE_TOKEN_SECRET` must match `DEVICE_TOKEN_KEY` provisioned in `include/config.h`.

`PAYMENT_PROVIDER` defaults to `disabled`. Set it to `test` only for development/testing. A production payment adapter (for example YooKassa/CloudPayments) must confirm payment server-side before `PaymentSessionService` is called.

## Full flow

### 1. Read device

```http
GET /api/devices/DEV-000001
```

Flutter receives server-driven UI, four channels, availability and tariffs.

### 2. Reserve one channel

```http
POST /api/devices/DEV-000001/channels/2/reserve
Content-Type: application/json

{
  "tariff_id": "R2-15MIN"
}
```

The server locks that `device_channels` row and returns a one-time `reservation_id`. Other users receive `409 channel_busy` while the reservation is active.

### 3. Create payment

```http
POST /api/payments
Content-Type: application/json

{
  "reservation_id": "RES-...",
  "tariff_id": "R2-15MIN"
}
```

The server creates `PAY-...` in state `pending`. Price and duration always come from the server tariff, never from Flutter.

### 4. Confirm payment

In production the payment provider webhook must verify the provider event and then call the same paid-session service used by the test provider.

For local development with `PAYMENT_PROVIDER=test`:

```http
POST /api/test-payments/PAY-.../pay
```

That changes the payment to `paid` and creates a unique `SES-...` with a one-time nonce.

### 5. Poll payment status

```http
GET /api/payments/PAY-...
```

After successful payment the response contains `session_id`.

### 6. Get signed command

```http
GET /api/sessions/SES-.../command
```

Example response:

```json
{
  "version": 1,
  "device_id": "DEV-000001",
  "payment_id": "PAY-...",
  "session_id": "SES-...",
  "tariff_id": "R2-15MIN",
  "relay_channel": 2,
  "duration_sec": 900,
  "issued_at": 1789360000,
  "expires_at": 1789360120,
  "nonce": "...",
  "token": "..."
}
```

Laravel signs canonical bytes:

```text
QRP1|device_id|payment_id|session_id|tariff_id|relay_channel|duration_sec|issued_at|expires_at|nonce
```

using HMAC-SHA256 and `DEVICE_TOKEN_SECRET`.

### 7. Flutter sends the exact JSON to ESP32 over BLE

ESP32 validates:

- protocol version;
- `device_id`;
- channel `1..4`;
- maximum duration;
- RTC;
- `issued_at` / `expires_at`;
- HMAC-SHA256 token;
- replay by `nonce` and `session_id`;
- target channel is not already active.

Only then does ESP32 call `relayChannelStart()` for the selected relay.

### 8. Flutter reports real ESP state

Flutter reads BLE STATUS and forwards it:

```http
POST /api/devices/DEV-000001/telemetry
Content-Type: application/json

{
  "channels": [
    {
      "channel": 2,
      "running": true,
      "remaining_sec": 899,
      "end_epoch": 1789360900,
      "session_id": "SES-...",
      "payment_id": "PAY-..."
    }
  ]
}
```

Server state becomes `running` and `occupied_until` is updated. When ESP later reports `running=false`, the session becomes `finished` and the channel becomes `free`.

## Important production rule

A browser/Flutter callback is never enough to mark a payment as paid. Only a verified server-side payment-provider webhook may transition a real payment to `paid` and create a device session.
