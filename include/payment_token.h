#pragma once

#include <Arduino.h>

// Expected format:
// P1|DEVICE_ID|duration_sec|issued_epoch|expires_epoch|nonce|signature_hex
bool paymentTokenAccept(const String &token, String &result);
