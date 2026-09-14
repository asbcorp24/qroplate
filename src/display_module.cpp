#include <Arduino.h>
#include <SPI.h>
#include <GxEPD2_BW.h>
#include <qrcode.h>

#include "config.h"
#include "display_module.h"
#include "relay_sessions.h"

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
static uint32_t lastRefreshMs = 0;

static String compactTime(uint32_t seconds) {
  const uint32_t h = seconds / 3600;
  const uint32_t m = (seconds % 3600) / 60;
  const uint32_t s = seconds % 60;
  char out[12];
  if (h > 0) {
    snprintf(out, sizeof(out), "%luh%02lum",
             (unsigned long)h, (unsigned long)m);
  } else {
    snprintf(out, sizeof(out), "%02lu:%02lu",
             (unsigned long)m, (unsigned long)s);
  }
  return String(out);
}

void displayModuleBegin() {
  SPI.begin(EPD_SCK_PIN, -1, EPD_MOSI_PIN, EPD_CS_PIN);
  display.init(115200, true, 20, false);
  display.setRotation(0);
  display.setTextColor(GxEPD_BLACK);
}

static void renderDashboard() {
  if (currentQr.length() == 0) return;

  QRCode qr;
  constexpr uint8_t version = 6;
  uint8_t data[qrcode_getBufferSize(version)];
  qrcode_initText(&qr, data, version, ECC_LOW, currentQr.c_str());

  display.setFullWindow();
  display.firstPage();
  do {
    display.fillScreen(GxEPD_WHITE);
    display.setTextColor(GxEPD_BLACK);
    display.setTextSize(1);

    // Keep the QR permanently visible so another user can scan the same
    // controller while other relay channels are already running.
    const int scale = 3;
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

    const int16_t statusTop = y0 + sizePx + 5;
    for (uint8_t ch = 1; ch <= RELAY_CHANNEL_COUNT; ++ch) {
      const int16_t y = statusTop + (ch - 1) * 16;
      display.setCursor(10, y);
      display.print("R");
      display.print(ch);
      display.print(": ");
      if (relayChannelActive(ch)) {
        display.print(compactTime(relayChannelRemaining(ch)));
      } else {
        display.print("FREE");
      }
    }
  } while (display.nextPage());

  lastRefreshMs = millis();
}

void displayModuleShowQr(const String &payload) {
  currentQr = payload;
  renderDashboard();
}

void displayModuleLoop() {
  if (currentQr.length() == 0) return;

  // Refresh while channels run so remaining time stays useful. When all
  // channels are free the e-paper needs no periodic refresh.
  if (relayActiveCount() > 0 &&
      millis() - lastRefreshMs >= DISPLAY_REFRESH_PERIOD_MS) {
    renderDashboard();
  }
}
