#include <Arduino.h>
#include <BLEDevice.h>
#include <BLEServer.h>
#include <BLEUtils.h>
#include <BLE2902.h>

#include "config.h"
#include "ble_module.h"
#include "relay_sessions.h"
#include "payment_token.h"

static BLEServer *serverPtr = nullptr;
static BLECharacteristic *statusChar = nullptr;
static bool connected = false;
static bool authenticated = false;
static String macAddress;
static String qrPayload;
static uint32_t lastNotifyMs = 0;
static String sessionRxBuffer;
static uint32_t sessionRxStartedMs = 0;
static const size_t SESSION_RX_MAX = 1024;

static String makeStatus(const char *eventName = nullptr) {
  String s = "{\"device_id\":\"" + String(DEVICE_ID) + "\"";
  s += ",\"connected\":" + String(connected ? "true" : "false");
  s += ",\"authenticated\":" + String(authenticated ? "true" : "false");
  s += ",\"active_count\":" + String(relayActiveCount());
  s += ",\"channels\":[";

  for (uint8_t ch = 1; ch <= RELAY_CHANNEL_COUNT; ++ch) {
    if (ch > 1) s += ",";
    const bool active = relayChannelActive(ch);
    s += "{\"channel\":" + String(ch);
    s += ",\"state\":\"" + String(active ? "running" : "free") + "\"";
    s += ",\"running\":" + String(active ? "true" : "false");
    s += ",\"remaining_sec\":" + String(relayChannelRemaining(ch));
    s += ",\"end_epoch\":" + String(relayChannelEndEpoch(ch));
    s += ",\"duration_sec\":" + String(relayChannelDuration(ch));
    s += ",\"session_id\":\"" + relayChannelSessionId(ch) + "\"";
    s += ",\"payment_id\":\"" + relayChannelPaymentId(ch) + "\"";
    s += "}";
  }

  s += "]";
  if (eventName) s += ",\"event\":\"" + String(eventName) + "\"";
  s += "}";
  return s;
}

static void publishStatus(const char *eventName = nullptr) {
  if (!statusChar) return;

  const String full = makeStatus(eventName);
  statusChar->setValue(full.c_str());

  if (connected) {
    String notice = "{\"event\":\"" + String(eventName ? eventName : "status_changed") + "\"}";
    statusChar->setValue(notice.c_str());
    statusChar->notify();
    statusChar->setValue(full.c_str());
  }

  Serial.println(full);
}

static void clearSessionRx() {
  sessionRxBuffer = "";
  sessionRxStartedMs = 0;
}

static void processSessionPackage(const String &package) {
  String validationResult;
  if (!paymentTokenAccept(package, validationResult)) {
    Serial.println("Session package rejected: " + validationResult);
    publishStatus("session_rejected");
    return;
  }
  publishStatus("session_started");
}

class ServerEvents : public BLEServerCallbacks {
  void onConnect(BLEServer *) override {
    connected = true;
    authenticated = false;
    clearSessionRx();
    publishStatus("connected");
  }

  void onDisconnect(BLEServer *server) override {
    connected = false;
    authenticated = false;
    clearSessionRx();
    server->startAdvertising();
    Serial.println("BLE disconnected, advertising restarted");
  }
};

class AuthEvents : public BLECharacteristicCallbacks {
  void onWrite(BLECharacteristic *characteristic) override {
    String value = String(characteristic->getValue().c_str());
    value.trim();
    authenticated = (value == DEVICE_ACCESS_CODE);
    publishStatus(authenticated ? "auth_ok" : "auth_failed");
  }
};

class PaymentEvents : public BLECharacteristicCallbacks {
  void onWrite(BLECharacteristic *characteristic) override {
    if (!authenticated) {
      publishStatus("not_authenticated");
      return;
    }

    String value = String(characteristic->getValue().c_str());

    // MTU-safe framing:
    // !          begin/reset package
    // +<data>    append a small chunk
    // .          validate complete package
    // { ... }    direct package is also accepted for bench clients
    if (value == "!") {
      clearSessionRx();
      sessionRxStartedMs = millis();
      return;
    }

    if (value.startsWith("+")) {
      if (sessionRxStartedMs == 0 || millis() - sessionRxStartedMs > 10000) {
        clearSessionRx();
        publishStatus("session_rejected");
        return;
      }
      if (sessionRxBuffer.length() + value.length() - 1 > SESSION_RX_MAX) {
        clearSessionRx();
        publishStatus("session_rejected");
        return;
      }
      sessionRxBuffer += value.substring(1);
      return;
    }

    if (value == ".") {
      if (sessionRxBuffer.length() == 0) {
        publishStatus("session_rejected");
        return;
      }
      const String package = sessionRxBuffer;
      clearSessionRx();
      processSessionPackage(package);
      return;
    }

    value.trim();
    if (value.startsWith("{")) {
      processSessionPackage(value);
      return;
    }

    publishStatus("session_rejected");
  }
};

void bleModuleBegin() {
  BLEDevice::init(BLE_DEVICE_NAME);
  BLEDevice::setMTU(517);
  macAddress = String(BLEDevice::getAddress().toString().c_str());
  macAddress.toUpperCase();

  serverPtr = BLEDevice::createServer();
  serverPtr->setCallbacks(new ServerEvents());

  BLEService *service = serverPtr->createService(BLE_SERVICE_UUID);

  BLECharacteristic *info = service->createCharacteristic(
      BLE_INFO_CHAR_UUID, BLECharacteristic::PROPERTY_READ);
  String infoJson = "{\"device_id\":\"" + String(DEVICE_ID) +
                    "\",\"name\":\"" + String(BLE_DEVICE_NAME) +
                    "\",\"mac\":\"" + macAddress +
                    "\",\"relay_channels\":" + String(RELAY_CHANNEL_COUNT) + "}";
  info->setValue(infoJson.c_str());

  BLECharacteristic *auth = service->createCharacteristic(
      BLE_AUTH_CHAR_UUID, BLECharacteristic::PROPERTY_WRITE);
  auth->setCallbacks(new AuthEvents());

  BLECharacteristic *payment = service->createCharacteristic(
      BLE_PAYMENT_CHAR_UUID, BLECharacteristic::PROPERTY_WRITE);
  payment->setCallbacks(new PaymentEvents());

  statusChar = service->createCharacteristic(
      BLE_STATUS_CHAR_UUID,
      BLECharacteristic::PROPERTY_READ | BLECharacteristic::PROPERTY_NOTIFY);
  statusChar->addDescriptor(new BLE2902());
  statusChar->setValue(makeStatus().c_str());

  service->start();
  BLEAdvertising *advertising = BLEDevice::getAdvertising();
  advertising->addServiceUUID(BLE_SERVICE_UUID);
  advertising->setScanResponse(true);
  BLEDevice::startAdvertising();

  qrPayload = "qroplate://connect?device=" + String(DEVICE_ID) +
              "&mac=" + macAddress + "&code=" + String(DEVICE_ACCESS_CODE);

  Serial.println("BLE MAC: " + macAddress);
  Serial.println("QR payload: " + qrPayload);
}

void bleModuleLoop() {
  const uint32_t now = millis();
  if (sessionRxStartedMs != 0 && now - sessionRxStartedMs > 10000) {
    clearSessionRx();
  }
  if (now - lastNotifyMs >= STATUS_NOTIFY_PERIOD_MS) {
    lastNotifyMs = now;
    publishStatus();
  }
}

String bleModuleMac() {
  return macAddress;
}

String bleModuleQrPayload() {
  return qrPayload;
}

bool bleModuleConnected() {
  return connected;
}
