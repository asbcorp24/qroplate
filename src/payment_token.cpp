#include <Arduino.h>
#include "payment_token.h"

bool paymentTokenAccept(const String &token, String &result) {
  if (token.length() == 0) {
    result = "empty_token";
    return false;
  }

  // Fail closed until server signature verification is enabled.
  // The relay must never be started by an unverified package.
  result = "signature_verifier_not_configured";
  return false;
}
