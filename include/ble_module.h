#pragma once

#include <Arduino.h>

void bleModuleBegin();
void bleModuleLoop();
String bleModuleMac();
String bleModuleQrPayload();
bool bleModuleConnected();
