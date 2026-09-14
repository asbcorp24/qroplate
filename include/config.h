#pragma once

#include <Arduino.h>

// Device identity. These demo values are replaced during provisioning.
#define DEVICE_ID       "DEV-000001"
#define BLE_DEVICE_NAME "QRPAY-000001"
#define DEVICE_ACCESS_CODE "CHANGE_ME"

// Per-device signing material used only to verify server-issued payment tokens.
// Replace during provisioning; never expose it through QR/BLE/application UI.
#define DEVICE_TOKEN_KEY "REPLACE_DURING_PROVISIONING"

// MH-ET LIVE ESP32 / generic ESP32 Dev Module
#define EPD_CS_PIN    5
#define EPD_DC_PIN    17
#define EPD_RST_PIN   16
#define EPD_BUSY_PIN  4
#define EPD_SCK_PIN   18
#define EPD_MOSI_PIN  23

#define RTC_SDA_PIN   21
#define RTC_SCL_PIN   22

#define RELAY_PIN     27
#define RELAY_ACTIVE_LEVEL HIGH
#define RELAY_IDLE_LEVEL   LOW

// 1 = GDEH0154D67 / SSD1681, 200x200
// 2 = older GDEP015OC1 compatible 1.54 inch B/W panel
#define EPD_MODEL 1

#define MAX_SESSION_SECONDS       (12UL * 60UL * 60UL)
#define TOKEN_CLOCK_SKEW_SECONDS  120UL
#define STATUS_NOTIFY_PERIOD_MS   1000UL
#define DISPLAY_REFRESH_PERIOD_MS 15000UL

#define BLE_SERVICE_UUID      "7a610000-71ce-4a7a-a9d8-6ad4bd36b000"
#define BLE_INFO_CHAR_UUID    "7a610001-71ce-4a7a-a9d8-6ad4bd36b000"
#define BLE_AUTH_CHAR_UUID    "7a610002-71ce-4a7a-a9d8-6ad4bd36b000"
#define BLE_PAYMENT_CHAR_UUID "7a610003-71ce-4a7a-a9d8-6ad4bd36b000"
#define BLE_STATUS_CHAR_UUID  "7a610004-71ce-4a7a-a9d8-6ad4bd36b000"
