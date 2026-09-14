#pragma once

#include <Arduino.h>

struct SessionRequest {
  int version = 0;
  String deviceId;
  String operationId;
  String sessionId;
  uint32_t durationSec = 0;
  uint32_t issuedAt = 0;
  uint32_t expiresAt = 0;
  String nonce;
  String proof;
};

bool parseSessionRequest(const String &json, SessionRequest &out, String &error);
