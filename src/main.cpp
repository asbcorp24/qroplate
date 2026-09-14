#include <Arduino.h>
#include "config.h"

void setup() {
  Serial.begin(115200);
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, RELAY_IDLE_LEVEL);
  Serial.println("QROplate ESP32 firmware starting");
}

void loop() {
  delay(1000);
}
