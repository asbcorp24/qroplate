#pragma once

#include <Arduino.h>

bool relaySessionsBegin();
void relaySessionsLoop();
bool relaySessionsRtcOk();
uint32_t relaySessionsCurrentEpoch();

bool relayChannelActive(uint8_t channel);
uint32_t relayChannelRemaining(uint8_t channel);
uint32_t relayChannelEndEpoch(uint8_t channel);
String relayChannelSessionId(uint8_t channel);
String relayChannelPaymentId(uint8_t channel);
String relayChannelNonce(uint8_t channel);
uint32_t relayChannelDuration(uint8_t channel);

bool relayChannelStart(uint8_t channel,
                       uint32_t durationSec,
                       const String &nonce,
                       const String &sessionId,
                       const String &paymentId);
void relayChannelStop(uint8_t channel, const char *reason);
void relayChannelsStopAll(const char *reason);
uint8_t relayActiveCount();
