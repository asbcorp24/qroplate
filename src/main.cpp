#include <Arduino.h>
#include "ble_module.h"
#include "display_module.h"
#include "relay_sessions.h"

void setup() {
  Serial.begin(115200);
  delay(250);
  Serial.println("QROplate ESP32 starting");

  relaySessionsBegin();
  bleModuleBegin();
  displayModuleBegin();
  displayModuleShowQr(bleModuleQrPayload());

  Serial.println("QROplate ready");
}

void loop() {
  relaySessionsLoop();
  bleModuleLoop();
  displayModuleLoop();
  delay(20);
}
