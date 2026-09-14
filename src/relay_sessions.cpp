#include <Arduino.h>
#include <Preferences.h>
#include <RTClib.h>
#include <Wire.h>

#include "config.h"
#include "relay_sessions.h"

struct ChannelState {
  uint32_t endEpoch = 0;
  uint32_t durationSec = 0;
  String nonce;
  String sessionId;
  String paymentId;
};

static ChannelState states[RELAY_CHANNEL_COUNT];
static Preferences prefs;
static RTC_DS3231 rtc;
static bool rtcReady = false;
static const uint8_t relayPins[RELAY_CHANNEL_COUNT] = {
  RELAY1_PIN, RELAY2_PIN, RELAY3_PIN, RELAY4_PIN
};

static bool validChannel(uint8_t channel) {
  return channel >= 1 && channel <= RELAY_CHANNEL_COUNT;
}

static uint8_t idx(uint8_t channel) {
  return channel - 1;
}

static void relayWrite(uint8_t channel, bool on) {
  if (!validChannel(channel)) return;
  digitalWrite(relayPins[idx(channel)], on ? RELAY_ACTIVE_LEVEL : RELAY_IDLE_LEVEL);
}

static String key(uint8_t channel, const char *suffix) {
  return "c" + String(channel) + "_" + String(suffix);
}

static bool rtcOk() {
  return rtcReady && !rtc.lostPower();
}

static uint32_t nowEpoch() {
  return rtcOk() ? rtc.now().unixtime() : 0;
}

static void saveChannel(uint8_t channel) {
  const ChannelState &s = states[idx(channel)];
  prefs.putUInt(key(channel, "end").c_str(), s.endEpoch);
  prefs.putUInt(key(channel, "dur").c_str(), s.durationSec);
  prefs.putString(key(channel, "nonce").c_str(), s.nonce);
  prefs.putString(key(channel, "sid").c_str(), s.sessionId);
  prefs.putString(key(channel, "pid").c_str(), s.paymentId);
}

static void clearChannel(uint8_t channel) {
  ChannelState &s = states[idx(channel)];
  s.endEpoch = 0;
  s.durationSec = 0;
  s.nonce = "";
  s.sessionId = "";
  s.paymentId = "";
  saveChannel(channel);
}

bool relaySessionsBegin() {
  for (uint8_t i = 0; i < RELAY_CHANNEL_COUNT; ++i) {
    pinMode(relayPins[i], OUTPUT);
    digitalWrite(relayPins[i], RELAY_IDLE_LEVEL);
  }

  Wire.begin(RTC_SDA_PIN, RTC_SCL_PIN);
  rtcReady = rtc.begin();
  prefs.begin("qroplate4", false);

  if (!rtcReady) {
    Serial.println("ERR RTC: DS3231 not found");
    return false;
  }

  if (rtc.lostPower()) {
    Serial.println("RTC lost power; setting build time");
    rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
  }

  const uint32_t now = nowEpoch();
  for (uint8_t channel = 1; channel <= RELAY_CHANNEL_COUNT; ++channel) {
    ChannelState &s = states[idx(channel)];
    s.endEpoch = prefs.getUInt(key(channel, "end").c_str(), 0);
    s.durationSec = prefs.getUInt(key(channel, "dur").c_str(), 0);
    s.nonce = prefs.getString(key(channel, "nonce").c_str(), "");
    s.sessionId = prefs.getString(key(channel, "sid").c_str(), "");
    s.paymentId = prefs.getString(key(channel, "pid").c_str(), "");

    if (s.endEpoch > now) {
      relayWrite(channel, true);
      Serial.printf("Relay %u restored, remaining=%lu sec\n",
                    channel, (unsigned long)(s.endEpoch - now));
    } else {
      clearChannel(channel);
      relayWrite(channel, false);
    }
  }
  return true;
}

bool relayChannelActive(uint8_t channel) {
  if (!validChannel(channel) || !rtcOk()) return false;
  const ChannelState &s = states[idx(channel)];
  return s.endEpoch != 0 && nowEpoch() < s.endEpoch;
}

uint32_t relayChannelRemaining(uint8_t channel) {
  if (!relayChannelActive(channel)) return 0;
  const uint32_t now = nowEpoch();
  const uint32_t end = states[idx(channel)].endEpoch;
  return end > now ? end - now : 0;
}

uint32_t relayChannelEndEpoch(uint8_t channel) {
  return validChannel(channel) ? states[idx(channel)].endEpoch : 0;
}

String relayChannelSessionId(uint8_t channel) {
  return validChannel(channel) ? states[idx(channel)].sessionId : String();
}

String relayChannelPaymentId(uint8_t channel) {
  return validChannel(channel) ? states[idx(channel)].paymentId : String();
}

String relayChannelNonce(uint8_t channel) {
  return validChannel(channel) ? states[idx(channel)].nonce : String();
}

uint32_t relayChannelDuration(uint8_t channel) {
  return validChannel(channel) ? states[idx(channel)].durationSec : 0;
}

bool relayChannelStart(uint8_t channel,
                       uint32_t durationSec,
                       const String &nonce,
                       const String &sessionId,
                       const String &paymentId) {
  if (!validChannel(channel) || !rtcOk()) return false;
  if (durationSec == 0 || durationSec > MAX_SESSION_SECONDS) return false;
  if (relayChannelActive(channel)) return false;
  if (nonce.length() < 8 || sessionId.length() == 0 || paymentId.length() == 0) return false;

  ChannelState &s = states[idx(channel)];
  if (nonce == s.nonce || sessionId == s.sessionId) return false;

  s.durationSec = durationSec;
  s.nonce = nonce;
  s.sessionId = sessionId;
  s.paymentId = paymentId;
  s.endEpoch = nowEpoch() + durationSec;
  saveChannel(channel);
  relayWrite(channel, true);
  return true;
}

void relayChannelStop(uint8_t channel, const char *reason) {
  if (!validChannel(channel)) return;
  relayWrite(channel, false);
  clearChannel(channel);
  Serial.printf("Relay %u stopped: %s\n", channel, reason ? reason : "unknown");
}

void relayChannelsStopAll(const char *reason) {
  for (uint8_t channel = 1; channel <= RELAY_CHANNEL_COUNT; ++channel) {
    relayChannelStop(channel, reason);
  }
}

uint8_t relayActiveCount() {
  uint8_t count = 0;
  for (uint8_t channel = 1; channel <= RELAY_CHANNEL_COUNT; ++channel) {
    if (relayChannelActive(channel)) ++count;
  }
  return count;
}

void relaySessionsLoop() {
  for (uint8_t channel = 1; channel <= RELAY_CHANNEL_COUNT; ++channel) {
    if (states[idx(channel)].endEpoch != 0 && !relayChannelActive(channel)) {
      relayChannelStop(channel, "TIME_EXPIRED");
    } else {
      relayWrite(channel, relayChannelActive(channel));
    }
  }
}
