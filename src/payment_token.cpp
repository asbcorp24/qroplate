#include <Arduino.h>
#include <Preferences.h>
#include <mbedtls/md.h>

#include "config.h"
#include "payment_token.h"
#include "relay_sessions.h"
#include "session_request.h"

static String toHex(const unsigned char *data, size_t len) {
  static const char hex[] = "0123456789abcdef";
  String out;
  out.reserve(len * 2);
  for (size_t i = 0; i < len; ++i) {
    out += hex[(data[i] >> 4) & 0x0F];
    out += hex[data[i] & 0x0F];
  }
  return out;
}

static bool secureEquals(const String &a, const String &b) {
  if (a.length() != b.length()) return false;
  uint8_t diff = 0;
  for (size_t i = 0; i < a.length(); ++i) diff |= (uint8_t)(a[i] ^ b[i]);
  return diff == 0;
}

static String canonicalMessage(const SessionRequest &r) {
  String s = "QRP1|";
  s += r.deviceId + "|";
  s += r.operationId + "|";
  s += r.sessionId + "|";
  s += r.tariffId + "|";
  s += String(r.relayChannel) + "|";
  s += String(r.durationSec) + "|";
  s += String(r.issuedAt) + "|";
  s += String(r.expiresAt) + "|";
  s += r.nonce;
  return s;
}

static bool verifyHmac(const SessionRequest &r) {
  const char *key = DEVICE_TOKEN_KEY;
  if (!key || strlen(key) < 16 || String(key) == "REPLACE_DURING_PROVISIONING") return false;

  const String msg = canonicalMessage(r);
  const mbedtls_md_info_t *info = mbedtls_md_info_from_type(MBEDTLS_MD_SHA256);
  if (!info) return false;

  unsigned char digest[32];
  if (mbedtls_md_hmac(info,
                      reinterpret_cast<const unsigned char *>(key), strlen(key),
                      reinterpret_cast<const unsigned char *>(msg.c_str()), msg.length(),
                      digest) != 0) {
    return false;
  }

  String expected = toHex(digest, sizeof(digest));
  String supplied = r.proof;
  supplied.toLowerCase();
  return secureEquals(expected, supplied);
}

static bool replaySeen(const SessionRequest &r) {
  Preferences p;
  if (!p.begin("qrpauth", false)) return true;
  const String nonceKey = "n" + String(r.relayChannel);
  const String sessionKey = "s" + String(r.relayChannel);
  const String lastNonce = p.getString(nonceKey.c_str(), "");
  const String lastSession = p.getString(sessionKey.c_str(), "");
  p.end();
  return r.nonce == lastNonce || r.sessionId == lastSession;
}

static void rememberAccepted(const SessionRequest &r) {
  Preferences p;
  if (!p.begin("qrpauth", false)) return;
  const String nonceKey = "n" + String(r.relayChannel);
  const String sessionKey = "s" + String(r.relayChannel);
  p.putString(nonceKey.c_str(), r.nonce);
  p.putString(sessionKey.c_str(), r.sessionId);
  p.end();
}

bool paymentTokenAccept(const String &package, String &result) {
  SessionRequest r;
  if (!parseSessionRequest(package, r, result)) return false;

  if (r.deviceId != DEVICE_ID) {
    result = "wrong_device";
    return false;
  }

  if (r.durationSec == 0 || r.durationSec > MAX_SESSION_SECONDS) {
    result = "bad_duration";
    return false;
  }

  if (r.expiresAt <= r.issuedAt) {
    result = "bad_token_window";
    return false;
  }

  if (relaySessionsRtcOk()) {
    const uint32_t now = relaySessionsCurrentEpoch();
    if (r.issuedAt > now + TOKEN_CLOCK_SKEW_SECONDS) {
      result = "issued_in_future";
      return false;
    }
    if (r.expiresAt + TOKEN_CLOCK_SKEW_SECONDS < now) {
      result = "token_expired";
      return false;
    }
  } else {
#if ALLOW_NO_RTC_TEST_MODE
    Serial.println("WARN AUTH: no RTC, Unix token window check skipped");
#else
    result = "rtc_not_ready";
    return false;
#endif
  }

  if (relayChannelActive(r.relayChannel)) {
    result = "channel_busy";
    return false;
  }
  if (replaySeen(r)) {
    result = "replay_detected";
    return false;
  }
  if (!verifyHmac(r)) {
    result = "bad_token";
    return false;
  }

  if (!relayChannelStart(r.relayChannel, r.durationSec, r.nonce, r.sessionId, r.operationId)) {
    result = "start_failed";
    return false;
  }

  rememberAccepted(r);
  result = "ok";
  return true;
}
