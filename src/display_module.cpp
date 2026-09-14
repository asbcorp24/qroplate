#include <Arduino.h>
#include <SPI.h>
#include <GxEPD2_BW.h>
#include <qrcode.h>

#include "config.h"
#include "display_module.h"
#include "session.h"

#if EPD_MODEL == 1
static GxEPD2_BW<GxEPD2_154_D67, GxEPD2_154_D67::HEIGHT> display(
    GxEPD2_154_D67(EPD_CS_PIN, EPD_DC_PIN, EPD_RST_PIN, EPD_BUSY_PIN));
#elif EPD_MODEL == 2
static GxEPD2_BW<GxEPD2_154, GxEPD2_154::HEIGHT> display(
    GxEPD2_154(EPD_CS_PIN, EPD_DC_PIN, EPD_RST_PIN, EPD_BUSY_PIN));
#else
#error Unsupported EPD_MODEL
#endif

static String currentQr;
static bool lastRunning = false;
static uint32_t lastRefreshMs = 0;

static void centered(const String &text, int16_t y) {
  int16_t x1, y1;
  uint16_t w, h;
  display.getTextBounds(text, 0, y, &x1, &y1, &w, &h);
  const int16_t x = max<int16_t>(0, (display.width() - (int16_t)w) / 2);
  display.setCursor(x, y);
  display.print(text);
}

static String formatTime(uint32_t seconds) {
  const uint32_t h = seconds / 3600;
  const uint32_t m = (seconds % 3600) / 60;
  const uint32_t s = seconds % 60;
  char out[16];
  snprintf(out, sizeof(out), "%02lu:%02lu:%02lu",
           (unsigned long)h, (unsigned long)m, (unsigned long)s);
  return String(out);
}

void displayModuleBegin() {
  SPI.begin(EPD_SCK_PIN, -1, EPD_MOSI_PIN, EPD_CS_PIN);
  display.init(115200, true, 20, false);
  display.setRotation(0);
  display.setTextColor(GxEPD_BLACK);
}

void displayModuleShowQr(const String &payload) {
  currentQr = payload;

  QRCode qr;
  constexpr uint8_t version = 6;
  uint8_t data[qrcode_getBufferSize(version)];
  qrcode_initText(&qr, data, version, ECC_LOW, payload.c_str());

  display.setFullWindow();
  display.firstPage();
  do {
    display.fillScreen(GxEPD_WHITE);
    display.setTextColor(GxEPD_BLACK);
    display.setTextSize(1);

    const int scale = 4;
    const int sizePx = qr.size * scale;
    const int x0 = (display.width() - sizePx) / 2;
    const int y0 = 2;

    for (uint8_t y = 0; y < qr.size; ++y) {
      for (uint8_t x = 0; x < qr.size; ++x) {
        if (qrcode_getModule(&qr, x, y)) {
          display.fillRect(x0 + x * scale, y0 + y * scale,
                           scale, scale, GxEPD_BLACK);
        }
      }
    }

    centered(String(DEVICE_ID), 195);
  } while (display.nextPage());

  lastRunning = false;
  lastRefreshMs = millis();
}

static void showRunning() {
  display.setFullWindow();
  display.firstPage();
  do {
    display.fillScreen(GxEPD_WHITE);
    display.setTextColor(GxEPD_BLACK);
    display.setTextSize(2);
    centered("PAID", 45);
    display.setTextSize(3);
    centered(formatTime(sessionRemainingSeconds()), 110);
    display.setTextSize(1);
    centered(String(DEVICE_ID), 165);
    centered("RELAY ON", 188);
  } while (display.nextPage());

  lastRunning = true;
  lastRefreshMs = millis();
}

void displayModuleLoop() {
  const bool running = sessionIsActive();

  if (running && (!lastRunning || millis() - lastRefreshMs >= DISPLAY_REFRESH_PERIOD_MS)) {
    showRunning();
    return;
  }

  if (!running && lastRunning && currentQr.length()) {
    displayModuleShowQr(currentQr);
  }
}
