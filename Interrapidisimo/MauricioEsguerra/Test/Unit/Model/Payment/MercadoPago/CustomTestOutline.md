# Conceptual Unit Tests for Interrapidisimo/MauricioEsguerra/Model/Payment/MercadoPago/Custom.php

This document outlines unit tests that should be implemented for the `Custom.php` payment model.
These tests would typically use PHPUnit and Magento's testing framework with appropriate mocks for SDK, Magento classes (Order, Payment, ScopeConfig, Logger, etc.).

## Test Suite: `CustomTest`

### Dependencies to Mock:
*   `Magento\Framework\App\Config\ScopeConfigInterface`
*   `Magento\Payment\Model\Method\Logger`
*   `MercadoPago\SDK` (or individual MP objects like `MercadoPago\Payment`, `MercadoPago\Payer`, `MercadoPago\Refund`)
*   `Magento\Sales\Model\Order`
*   `Magento\Sales\Model\Order\Payment`
*   `Magento\Sales\Model\Order\Address`
*   `Magento\Store\Model\Store`
*   `Magento\Framework\DataObject` (for payment additional information and method assignment)
*   `Magento\Framework\Model\Context`
*   `Magento\Framework\Registry`
*   `Magento\Framework\Api\ExtensionAttributesFactory`
*   `Magento\Framework\Api\AttributeValueFactory`
*   `Magento\Payment\Helper\Data`
*   `Magento\Framework\Module\ModuleListInterface`
*   `Magento\Framework\Stdlib\DateTime\TimezoneInterface`
*   Other Magento helpers/factories as needed.

### Test Cases for `isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)`:
*   `testIsAvailable_WhenActiveAndApiKeysAndCountrySet_ReturnsTrue()`
    *   Mock `ScopeConfigInterface` to return `1` for `active`, a valid public key, a valid access token, and a valid country code.
    *   Mock `parent::isAvailable()` to return `true`.
    *   Assert `isAvailable()` returns `true`.
*   `testIsAvailable_WhenInactive_ReturnsFalse()`
    *   Mock `ScopeConfigInterface` to return `0` for `active`.
    *   Assert `isAvailable()` returns `false`.
*   `testIsAvailable_WhenAccessTokenMissing_ReturnsFalse()`
    *   Mock `ScopeConfigInterface` to return `1` for `active`, a valid public key, but `null` or empty for access token.
    *   Assert `isAvailable()` returns `false`.
    *   Verify logger was called with debug message.
*   `testIsAvailable_WhenPublicKeyMissing_ReturnsFalse()`
    *   Mock `ScopeConfigInterface` to return `1` for `active`, a valid access token, but `null` or empty for public key.
    *   Assert `isAvailable()` returns `false`.
    *   Verify logger was called with debug message.
*   `testIsAvailable_WhenCountryCodeMissing_ReturnsFalse()`
    *   Mock `ScopeConfigInterface` to return `1` for `active`, valid keys, but `null` or empty for country code.
    *   Assert `isAvailable()` returns `false`.
    *   Verify logger was called with debug message.
*   `testIsAvailable_WhenParentReturnsFalse_ReturnsFalse()`
    *   Mock `ScopeConfigInterface` for active, keys, country code all valid.
    *   Mock `parent::isAvailable()` to return `false`.
    *   Assert `isAvailable()` returns `false`.

### Test Cases for `capture(\Magento\Payment\Model\InfoInterface $payment, $amount)`:
*   `testCapture_SuccessfulApprovedPayment_SetsTransactionIdAndClosesTransaction()`
    *   Mock MP SDK `Payment->save()` to succeed, with `$mpPayment->id` populated and `$mpPayment->status = 'approved'`.
    *   Mock `Order` and `BillingAddress` objects.
    *   Set required additional information on the payment object (token, payment_method_id, installments).
    *   Call `capture()`.
    *   Verify `setLastTransId` and `setTransactionId` called on payment object with MP payment ID.
    *   Verify `setIsTransactionClosed(true)` called.
    *   Verify no pending state `setIsTransactionPending(false)` or not called.
*   `testCapture_SuccessfulPendingPayment_SetsTransactionIdAndKeepsTransactionOpenAndPending()`
    *   Mock MP SDK `Payment->save()` to succeed, with `$mpPayment->id` populated and `$mpPayment->status = 'in_process'`.
    *   Call `capture()`.
    *   Verify `setLastTransId` and `setTransactionId`.
    *   Verify `setIsTransactionClosed(false)` and `setIsTransactionPending(true)` called.
*   `testCapture_SuccessfulAuthorizedPayment_SetsTransactionIdAndKeepsTransactionOpen()`
    *   Mock MP SDK `Payment->save()` to succeed, with `$mpPayment->id` populated and `$mpPayment->status = 'authorized'`. (Note: current code doesn't explicitly handle 'authorized' from capture, but treats it like 'pending')
    *   Call `capture()`.
    *   Verify `setIsTransactionClosed(false)` and `setIsTransactionPending(true)` (or similar pending state).
*   `testCapture_PaymentRejectedByMercadoPago_ThrowsLocalizedException()`
    *   Mock MP SDK `Payment->save()` to succeed, with `$mpPayment->id` populated and `$mpPayment->status = 'rejected'`, and a `status_detail`.
    *   Expect `LocalizedException` with a message containing the status detail.
    *   Verify error is logged.
*   `testCapture_ApiErrorDuringPaymentCreation_ThrowsLocalizedExceptionAndLogsError()`
    *   Mock MP SDK `Payment->save()` to throw an `MercadoPago\Exception\MPException` or return `null` for `$mpPayment->id` along with `last_error` or `error` properties on the MP payment object.
    *   Expect `LocalizedException`.
    *   Verify error is logged (critical for SDK exception, error for failed save).
*   `testCapture_MissingMercadoPagoToken_ThrowsLocalizedException()`
    *   Setup payment additional_information without 'mercadopago_card_token'.
    *   Expect `LocalizedException`.
    *   Verify error is logged.
*   `testCapture_MissingPaymentMethodId_ThrowsLocalizedException()`
    *   Setup payment additional_information without 'mercadopago_payment_method_id'.
    *   Expect `LocalizedException`.
    *   Verify error is logged.
*   `testCapture_MissingInstallments_ThrowsLocalizedException()`
    *   Setup payment additional_information with 'mercadopago_installments' as `null`.
    *   Expect `LocalizedException`.
    *   Verify error is logged.
*   `testCapture_CannotCaptureIfMethodDisabled_ThrowsLocalizedException()`
    *   Mock `canCapture()` to return `false`.
    *   Expect `LocalizedException`.

### Test Cases for `refund(\Magento\Payment\Model\InfoInterface $payment, $amount)`:
*   `testRefund_SuccessfulFullRefund_SetsTransactionDetailsAndClosesParent()`
    *   Mock MP SDK `Payment::find_by_id()` to return a valid MP Payment object (status 'approved').
    *   Mock MP SDK `SDK::post()` (for refund endpoint) to return a successful refund response (status 200/201, with refund ID).
    *   Setup payment with a parent transaction ID.
    *   Call `refund()`.
    *   Verify `setTransactionId` (with refund ID), `setParentTransactionId`, `setIsTransactionClosed(true)`, `setShouldCloseParentTransaction(true)`.
    *   Verify info log message.
*   `testRefund_ApiErrorDuringRefund_ThrowsLocalizedExceptionAndLogsError()`
    *   Mock MP SDK `Payment::find_by_id()` to return a valid MP Payment object.
    *   Mock MP SDK `SDK::post()` to return an error response or throw `MPException`.
    *   Expect `LocalizedException`.
    *   Verify critical/error log message.
*   `testRefund_MissingParentTransactionId_ThrowsLocalizedException()`
    *   Setup payment without a `last_trans_id` or `parent_transaction_id`.
    *   Expect `LocalizedException`.
*   `testRefund_PaymentNotFoundInMercadoPago_ThrowsLocalizedException()`
    *   Mock MP SDK `Payment::find_by_id()` to return `null`.
    *   Expect `LocalizedException`.
*   `testRefund_PaymentAlreadyRefundedInMercadoPago_ThrowsLocalizedException()`
    *   Mock MP SDK `Payment::find_by_id()` to return an MP Payment object with status 'refunded'.
    *   Expect `LocalizedException`.
*   `testRefund_CannotRefundIfMethodDisabled_ThrowsLocalizedException()`
    *   Mock `canRefund()` to return `false`.
    *   Expect `LocalizedException`.

### Test Cases for `void(\Magento\Payment\Model\InfoInterface $payment)`:
*   (Note: `void` in Magento often means cancelling an authorization. MercadoPago API might map this to cancelling a 'pending' or 'authorized' payment if capture hasn't occurred. The current code uses `Payment->update()` with status `cancelled`.)
*   `testVoid_SuccessfulCancellationOfPendingPayment_UpdatesTransaction()`
    *   Mock MP SDK `Payment::find_by_id()` to return a payment with status 'pending' or 'in_process'.
    *   Mock MP SDK `Payment->update()` to succeed and `$mpPayment->status` becomes 'cancelled'.
    *   Call `void()`.
    *   Verify `setTransactionId` (with void suffix), `setParentTransactionId`, `setIsTransactionClosed(true)`, `setShouldCloseParentTransaction(true)`.
    *   Verify info log message.
*   `testVoid_AttemptToVoidApprovedAndCapturedPayment_ThrowsLocalizedException()`
    *   Mock MP SDK `Payment::find_by_id()` to return an 'approved' payment.
    *   Expect `LocalizedException` (as per current code logic: "Payment cannot be voided. Status is approved.").
*   `testVoid_ApiErrorDuringCancellation_ThrowsLocalizedExceptionAndLogsError()`
    *   Mock MP SDK `Payment::find_by_id()` to return a 'pending' payment.
    *   Mock MP SDK `Payment->update()` to fail (e.g., `last_error` or error in response array).
    *   Expect `LocalizedException`.
    *   Verify critical/error log message.
*   `testVoid_MissingParentTransactionId_ThrowsLocalizedException()`
    *   Setup payment without a `last_trans_id` or `parent_transaction_id`.
    *   Expect `LocalizedException`.
*   `testVoid_CannotVoidIfMethodDisabled_ThrowsLocalizedException()`
    *   Mock `canVoid()` to return `false`.
    *   Expect `LocalizedException`.

### Test Cases for Helper Methods (`_getAccessToken`, `_getPublicKey`, `_getCountryCode`, `getTitle`, `isActive`, `_initMercadoPagoSDK`):
*   `testGetAccessToken_RetrievesValueFromScopeConfig()`
    *   Mock `ScopeConfigInterface->getValue()` for the access token path.
    *   Call `_getAccessToken()` (protected, so test via a public method that uses it or make it public for testing if necessary, or use reflection).
    *   Assert correct value is returned.
*   `testGetPublicKey_RetrievesValueFromScopeConfig()`
    *   Similar to `_getAccessToken()`.
*   `testGetCountryCode_RetrievesValueFromScopeConfig()`
    *   Similar to `_getAccessToken()`.
*   `testGetTitle_RetrievesValueFromScopeConfig()`
    *   Similar to `_getAccessToken()`.
*   `testIsActive_RetrievesValueFromScopeConfigAndCastsToBool()`
    *   Mock `ScopeConfigInterface->getValue()` for active path with `1` and `0`.
    *   Assert `isActive()` returns `true` and `false` respectively.
*   `testInitMercadoPagoSDK_SetsAccessTokenSuccessfully()`
    *   Mock `_getAccessToken()` to return a valid token.
    *   Mock static `MercadoPago\SDK::setAccessToken()`.
    *   Call `_initMercadoPagoSDK()`.
    *   Verify `SDK::setAccessToken()` was called with the correct token.
*   `testInitMercadoPagoSDK_ThrowsExceptionIfAccessTokenMissing()`
    *   Mock `_getAccessToken()` to return `null`.
    *   Expect `LocalizedException`.
    *   Verify error log.

### Test Cases for `authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)`:
*   `testAuthorize_SetsTransactionPendingAndReturnsThis()`
    *   Call `authorize()`.
    *   Verify `setTransactionId` is called.
    *   Verify `setIsTransactionClosed(false)` is called.
    *   Assert the method returns `$this`.
*   `testAuthorize_CannotAuthorizeIfMethodDisabled_ThrowsLocalizedException()`
    *   Mock `canAuthorize()` to return `false`.
    *   Expect `LocalizedException`.

---
*Further tests can be added for other public methods (e.g., `assignData`, `validate` if they have significant custom logic) or specific internal logic if necessary.*
*Consider edge cases for amounts (e.g., zero, negative - though Magento validation should catch some).*
*Test interactions with the logger for all error/debug scenarios.*
