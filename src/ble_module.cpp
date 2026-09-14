#include <Arduino.h>
#include <BLEDevice.h>
#include <BLEServer.h>
#include <BLEUtils.h>
#include <BLE2902.h>

#include "config.h"
#include "ble_module.h"
#include "session.h"
#include "payment_token.h"

static BLEServer *serverPtr = nullptr;
static BLECharacteristic *statusChar = nullptr;
static bool connected = false;
static bool authenticated = false;
static String macAddress;
static String qrPayload;
static uint32_t lastNotifyMs = 0;

static String makeStatus(const char *eventName = nullptr) {
  String s = "{\"device_id\":\"" + String(DEVICE_ID) + "\"";
  s += ",\"connected\":" + String(connected ? "true" : "false");
  s += ",\"authenticated\":" + String(authenticated ? "true" : "false");
  s += ",\"rtc_ok\":" + String(sessionRtcOk() ? "true" : "false");
  s += ",\"running\":" + String(sessionIsActive() ? "true" : "false");
  s += ",\"relay_channel\":" + String(sessionRelayChannel());
  s += ",\"relay_count\":" + String(RELAY_CHANNEL_COUNT);
  s += ",\"remaining_sec\":" + String(sessionRemainingSeconds());
  if (eventName) s += ",\"event\":\"" + String(eventName) + "\"";
  s += "}";
  return s;
}

static void publishStatus(const char *eventName = nullptr) {
  if (!statusChar) return;
  const String s = makeStatus(eventName);
  statusChar->setValue(s.c_str());
  if (connected) statusChar->notify();
  Serial.println(s);
}

class ServerEvents : public BLEServerCallbacks {
  void onConnect(BLEServer *) override {
    connected = true;
    authenticated = false;
    publishStatus("connected");
  }

  void onDisconnect(BLEServer *server) override {
    connected = false;
    authenticated = false;
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

    String package = String(characteristic->getValue().c_str());
    package.trim();

    String validationResult;
    if (!paymentTokenAccept(package, validationResult)) {
      Serial.println("Session package rejected: " + validationResult);
      publishStatus("session_rejected");
      return;
    }

    publishStatus("session_started");
  }
};

void bleModuleBegin() {
  BLEDevice::init(BLE_DEVICE_NAME);
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
                    "\",\"relay_count\":" + String(RELAY_CHANNEL_COUNT) + "}";
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
