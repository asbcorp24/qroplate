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
static uint8_t activeRelayChannel = 0;

static const uint8_t relayPins[RELAY_CHANNEL_COUNT] = {
  RELAY1_PIN, RELAY2_PIN, RELAY3_PIN, RELAY4_PIN
};

static void allRelaysOff() {
  for (uint8_t i = 0; i < RELAY_CHANNEL_COUNT; ++i) {
    digitalWrite(relayPins[i], RELAY_IDLE_LEVEL);
  }
}

static void selectedRelaySet(bool on) {
  allRelaysOff();
  if (!on) return;
  if (activeRelayChannel < 1 || activeRelayChannel > RELAY_CHANNEL_COUNT) return;
  digitalWrite(relayPins[activeRelayChannel - 1], RELAY_ACTIVE_LEVEL);
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

uint8_t sessionRelayChannel() {
  return activeRelayChannel;
}

String sessionLastNonce() {
  return lastNonce;
}

bool sessionIsActive() {
  if (!sessionRtcOk() || endEpoch == 0) return false;
  if (activeRelayChannel < 1 || activeRelayChannel > RELAY_CHANNEL_COUNT) return false;
  return rtc.now().unixtime() < endEpoch;
}

uint32_t sessionRemainingSeconds() {
  if (!sessionIsActive()) return 0;
  const uint32_t now = rtc.now().unixtime();
  return endEpoch > now ? endEpoch - now : 0;
}

bool sessionBegin() {
  for (uint8_t i = 0; i < RELAY_CHANNEL_COUNT; ++i) {
    pinMode(relayPins[i], OUTPUT);
  }
  allRelaysOff();

  Wire.begin(RTC_SDA_PIN, RTC_SCL_PIN);
  rtcPresent = rtc.begin();
  prefs.begin("qroplate", false);

  endEpoch = prefs.getUInt("end_epoch", 0);
  lastNonce = prefs.getString("last_nonce", "");
  activeRelayChannel = prefs.getUChar("relay_ch", 0);

  if (!rtcPresent) {
    Serial.println("ERR RTC: DS3231 not found");
    return false;
  }

  if (rtc.lostPower()) {
    Serial.println("RTC lost power; setting RTC to firmware build time");
    rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
  }

  if (sessionIsActive()) {
    selectedRelaySet(true);
    Serial.printf("Session restored: relay=%u remaining=%lu sec\n",
                  activeRelayChannel,
                  (unsigned long)sessionRemainingSeconds());
  } else {
    endEpoch = 0;
    activeRelayChannel = 0;
    prefs.putUInt("end_epoch", 0);
    prefs.putUChar("relay_ch", 0);
    allRelaysOff();
  }
  return true;
}

bool sessionStart(uint8_t relayChannel, uint32_t durationSeconds, const String &nonce) {
  if (!sessionRtcOk()) return false;
  if (relayChannel < 1 || relayChannel > RELAY_CHANNEL_COUNT) return false;
  if (durationSeconds == 0 || durationSeconds > MAX_SESSION_SECONDS) return false;
  if (sessionIsActive()) return false;
  if (nonce.length() < 8 || nonce == lastNonce) return false;

  lastNonce = nonce;
  activeRelayChannel = relayChannel;
  endEpoch = rtc.now().unixtime() + durationSeconds;

  prefs.putString("last_nonce", lastNonce);
  prefs.putUChar("relay_ch", activeRelayChannel);
  prefs.putUInt("end_epoch", endEpoch);

  selectedRelaySet(true);
  return true;
}

void sessionStop(const char *reason) {
  allRelaysOff();
  endEpoch = 0;
  activeRelayChannel = 0;
  prefs.putUInt("end_epoch", 0);
  prefs.putUChar("relay_ch", 0);
  Serial.printf("Session stopped: %s\n", reason ? reason : "unknown");
}

void sessionLoop() {
  if (endEpoch != 0 && sessionRtcOk() && !sessionIsActive()) {
    sessionStop("TIME_EXPIRED");
  }
  selectedRelaySet(sessionIsActive());
}
