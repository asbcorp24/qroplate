#include <Arduino.h>
#include "ble_module.h"
#include "display_module.h"
#include "session.h"

void setup() {
  Serial.begin(115200);
  delay(250);
  Serial.println("QROplate ESP32 starting");

  sessionBegin();
  bleModuleBegin();
  displayModuleBegin();
  displayModuleShowQr(bleModuleQrPayload());

  Serial.println("QROplate ready");
}

void loop() {
  sessionLoop();
  bleModuleLoop();
  displayModuleLoop();
  delay(20);
}
