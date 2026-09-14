#include <Arduino.h>
#include <Preferences.h>
#include <RTClib.h>
#include <Wire.h>

#include "config.h"
#include "session.h"

static RTC_DS3231 rtc;
static Preferences prefs;
static uint32_t endEpoch = 0;
static String lastNonce;
static bool rtcPresent = false;

static void relaySet(bool on) {
  digitalWrite(RELAY_PIN, on ? RELAY_ACTIVE_LEVEL : RELAY_IDLE_LEVEL);
}

bool sessionRtcOk() {
  return rtcPresent && !rtc.lostPower();
}

uint32_t sessionCurrentEpoch() {
  if (!sessionRtcOk()) return 0;
  return rtc.now().unixtime();
}

uint32_t sessionEndEpoch() {
  return endEpoch;
}

String sessionLastNonce() {
  return lastNonce;
}

bool sessionIsActive() {
  if (!sessionRtcOk() || endEpoch == 0) return false;
  return rtc.now().unixtime() < endEpoch;
}

uint32_t sessionRemainingSeconds() {
  if (!sessionIsActive()) return 0;
  const uint32_t now = rtc.now().unixtime();
  return endEpoch > now ? endEpoch - now : 0;
}

bool sessionBegin() {
  pinMode(RELAY_PIN, OUTPUT);
  relaySet(false);

  Wire.begin(RTC_SDA_PIN, RTC_SCL_PIN);
  rtcPresent = rtc.begin();
  prefs.begin("qroplate", false);

  endEpoch = prefs.getUInt("end_epoch", 0);
  lastNonce = prefs.getString("last_nonce", "");

  if (!rtcPresent) {
    Serial.println("ERR RTC: DS3231 not found");
    return false;
  }
  if (rtc.lostPower()) {
    Serial.println("ERR RTC: lost power; paid sessions disabled until RTC is set");
    return false;
  }

  if (sessionIsActive()) {
    relaySet(true);
    Serial.printf("Session restored, remaining=%lu sec\n",
                  (unsigned long)sessionRemainingSeconds());
  } else {
    endEpoch = 0;
    prefs.putUInt("end_epoch", 0);
    relaySet(false);
  }
  return true;
}

bool sessionStart(uint32_t durationSeconds, const String &nonce) {
  if (!sessionRtcOk()) return false;
  if (durationSeconds == 0 || durationSeconds > MAX_SESSION_SECONDS) return false;
  if (sessionIsActive()) return false;
  if (nonce.length() < 8 || nonce == lastNonce) return false;

  lastNonce = nonce;
  endEpoch = rtc.now().unixtime() + durationSeconds;
  prefs.putString("last_nonce", lastNonce);
  prefs.putUInt("end_epoch", endEpoch);
  relaySet(true);
  return true;
}

void sessionStop(const char *reason) {
  relaySet(false);
  endEpoch = 0;
  prefs.putUInt("end_epoch", 0);
  Serial.printf("Session stopped: %s\n", reason ? reason : "unknown");
}

void sessionLoop() {
  if (endEpoch != 0 && sessionRtcOk() && !sessionIsActive()) {
    sessionStop("TIME_EXPIRED");
  }
  relaySet(sessionIsActive());
}
