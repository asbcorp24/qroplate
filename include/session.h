#pragma once

#include <Arduino.h>

bool sessionBegin();
bool sessionRtcOk();
bool sessionIsActive();
uint32_t sessionCurrentEpoch();
uint32_t sessionRemainingSeconds();
uint32_t sessionEndEpoch();
uint8_t sessionRelayChannel();
bool sessionStart(uint8_t relayChannel, uint32_t durationSeconds, const String &nonce);
void sessionStop(const char *reason);
void sessionLoop();
String sessionLastNonce();
