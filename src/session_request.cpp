#include <Arduino.h>
#include <ArduinoJson.h>

#include "session_request.h"

bool parseSessionRequest(const String &json, SessionRequest &out, String &error) {
  JsonDocument doc;
  DeserializationError e = deserializeJson(doc, json);
  if (e) {
    error = "bad_json";
    return false;
  }

  out.version = doc["version"] | 0;
  out.deviceId = String((const char *)(doc["device_id"] | ""));
  out.operationId = String((const char *)(doc["payment_id"] | ""));
  out.sessionId = String((const char *)(doc["session_id"] | ""));
  out.durationSec = doc["duration_sec"] | 0;
  out.issuedAt = doc["issued_at"] | 0;
  out.expiresAt = doc["expires_at"] | 0;
  out.nonce = String((const char *)(doc["nonce"] | ""));
  out.proof = String((const char *)(doc["token"] | ""));

  if (out.version != 1) {
    error = "bad_version";
    return false;
  }
  if (out.deviceId.length() == 0 ||
      out.operationId.length() == 0 ||
      out.sessionId.length() == 0 ||
      out.durationSec == 0 ||
      out.issuedAt == 0 ||
      out.expiresAt == 0 ||
      out.nonce.length() == 0 ||
      out.proof.length() == 0) {
    error = "missing_field";
    return false;
  }

  error = "ok";
  return true;
}
