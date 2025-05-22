define([
    'jquery',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'https://sdk.mercadopago.com/js/v2' // Load MercadoPago SDK V2
], function ($, ccFormComponent, additionalValidators, quote, fullScreenLoader, alert, $t) {
    'use strict';

    return ccFormComponent.extend({
        defaults: {
            template: 'Interrapidisimo_MauricioEsguerra/payment/mercadopago/form/cc',
            code: 'mercadopago_custom',
            active: false,
            mpInstance: null,
            mpCardForm: null,
            publicKey: null,
            countryCode: null, // e.g., 'CO' for Colombia, 'AR' for Argentina
            selectedInstallments: 1,
            selectedIssuerId: null,
            selectedDocType: null,
            cardToken: null,
            paymentMethodId: null, // BIN will give this
            tracks: {
                active: true,
                selectedInstallments: true,
                selectedIssuerId: true,
                selectedDocType: true,
                cardToken: true,
                paymentMethodId: true
            },
            listens: {
                'active': 'onActiveChange'
            },
            imports: {
                onActiveChange: 'active'
            }
        },

        initObservable: function () {
            this._super()
                .observe([
                    'active',
                    'selectedInstallments',
                    'selectedIssuerId',
                    'selectedDocType',
                    'cardToken',
                    'paymentMethodId'
                ]);
            return this;
        },

        onActiveChange: function (isActive) {
            if (isActive && !this.mpInstance) {
                this.initializeMercadoPago();
            }
        },

        initializeMercadoPago: function () {
            var self = this;
            this.publicKey = window.checkoutConfig.payment[this.getCode()].publicKey;
            this.countryCode = window.checkoutConfig.payment[this.getCode()].countryCode; // Example: 'CO'

            if (!this.publicKey) {
                console.error('Mercado Pago Public Key not found.');
                $('#mercadopago-error-message').text($t('Configuration error. Please contact support.')).show();
                return;
            }

            self.mpInstance = new MercadoPago(this.publicKey, {
                locale: this.getLocaleForSdk() // e.g. 'es-CO', 'pt-BR'
            });

            // Delay mounting until DOM elements are surely available
            setTimeout(function() {
                self.mountCardFields();
                self.loadIdentificationTypes();
            }, 500);
        },
        
        getLocaleForSdk: function() {
            // Simplified locale mapping. Magento's locale (e.g., en_US, es_ES) might need more complex mapping to MP's (e.g., en-US, es-AR)
            // This is a basic example. A more robust solution would map Magento's locale.
            let lang = 'en';
            if (window.LOCALE && window.LOCALE.split('_')[0]) {
                lang = window.LOCALE.split('_')[0];
            }
            // MP uses format like 'es-CO'. Assume countryCode is 'CO', 'AR', 'BR' etc.
            return lang + '-' + this.countryCode;
        },

        mountCardFields: function () {
            var self = this;
            try {
                const cardNumberElement = document.getElementById('form-checkout__cardNumber-container');
                const expirationDateElement = document.getElementById('form-checkout__expirationDate-container');
                const securityCodeElement = document.getElementById('form-checkout__securityCode-container');

                if (!cardNumberElement || !expirationDateElement || !securityCodeElement) {
                    console.error('Mercado Pago form elements not found.');
                     $('#mercadopago-error-message').text($t('Payment form could not be loaded.')).show();
                    return;
                }
                
                // Reset containers to prevent multiple initializations if this function is called again
                cardNumberElement.innerHTML = '';
                expirationDateElement.innerHTML = '';
                securityCodeElement.innerHTML = '';

                self.mpCardNumber = self.mpInstance.fields.create('cardNumber', {
                    placeholder: $t("Card Number")
                }).mount('form-checkout__cardNumber-container');

                self.mpExpirationDate = self.mpInstance.fields.create('expirationDate', {
                    placeholder: $t("MM/YY")
                }).mount('form-checkout__expirationDate-container');
                
                self.mpSecurityCode = self.mpInstance.fields.create('securityCode', {
                    placeholder: $t("CVV")
                }).mount('form-checkout__securityCode-container');

                // Event listeners for card fields (e.g., to get BIN for installments)
                self.mpCardNumber.on('binChange', function(data) {
                    var bin = data.bin;
                    if (bin) {
                        self.paymentMethodId = data.paymentMethodId; // payment_method_id from MP
                        self.getInstallments(bin);
                    } else {
                         $('#mpInstallments').empty().append(new Option($t('---'), ''));
                         $('#mpIssuer').empty().append(new Option($t('---'), '')).parent().hide();
                    }
                });
                 self.mpCardNumber.on('error', function(errors) { self.displayMpError(errors);});
                 self.mpExpirationDate.on('error', function(errors) { self.displayMpError(errors);});
                 self.mpSecurityCode.on('error', function(errors) { self.displayMpError(errors);});

            } catch (e) {
                console.error('Error mounting Mercado Pago fields:', e);
                $('#mercadopago-error-message').text($t('Error initializing payment form.')).show();
            }
        },

        loadIdentificationTypes: async function () {
            var self = this;
            try {
                const identificationTypes = await self.mpInstance.getIdentificationTypes();
                var select = $('#mpDocType');
                select.empty();
                identificationTypes.forEach(function (idType) {
                    select.append(new Option(idType.name, idType.id));
                });
                if (identificationTypes.length > 0) {
                    self.selectedDocType(identificationTypes[0].id); // Pre-select first
                }
            } catch (e) {
                console.error('Error loading identification types:', e);
                // Optionally hide doc type fields or show error
            }
        },

        getInstallments: async function (bin) {
            var self = this;
            var amount = quote.totals().grand_total;
            if (!bin || !amount) return;

            try {
                const installmentsData = await self.mpInstance.getInstallments({
                    amount: String(amount),
                    bin: bin
                });
                
                var selectInstallments = $('#mpInstallments');
                var selectIssuer = $('#mpIssuer');
                selectInstallments.empty();
                selectIssuer.empty().parent().hide();

                if (installmentsData.length > 0 && installmentsData[0].payer_costs) {
                    installmentsData[0].payer_costs.forEach(function (pc) {
                        selectInstallments.append(new Option(pc.recommended_message, pc.installments));
                    });
                    self.selectedInstallments(installmentsData[0].payer_costs[0].installments); // Pre-select

                    if (installmentsData[0].issuer && installmentsData[0].issuer.id) {
                        // This part is more for co-branded cards or specific issuer promotions
                        // Often, issuer is not needed if not explicitly returned as an option to choose
                        // For now, let's assume direct installment selection is enough.
                        // If issuers are present in response and need selection:
                        // selectIssuer.append(new Option(installmentsData[0].issuer.name, installmentsData[0].issuer.id));
                        // self.selectedIssuerId(installmentsData[0].issuer.id);
                        // selectIssuer.parent().show();
                    }
                } else {
                     selectInstallments.append(new Option($t('No installments available'), '1'));
                     self.selectedInstallments(1);
                }
            } catch (e) {
                console.error('Error getting installments:', e);
                 $('#mpInstallments').empty().append(new Option($t('Could not load installments'), '1'));
                 self.selectedInstallments(1);
            }
        },

        getCode: function () {
            return this.code;
        },

        getTitle: function () {
             return window.checkoutConfig.payment[this.getCode()].title;
        },
        
        isActive: function () {
            var active = this.getCode() === this.isChecked();
            this.active(active);
            return active;
        },

        getPlaceOrderDeferredObject: function () {
            var self = this;
            var deferred = $.Deferred();

            if (!this.validate() || !additionalValidators.validate()) {
                deferred.reject();
                return deferred.promise();
            }
            
            fullScreenLoader.startLoader();

            this.createCardToken().then(function (token) {
                self.cardToken = token.id;
                self.placeOrder(self.getData(), self.messageContainer); // Call Magento's placeOrder
                deferred.resolve();
                fullScreenLoader.stopLoader();
            }).catch(function (error) {
                console.error('Token creation failed:', error);
                self.messageContainer.addErrorMessage({
                    message: $t('Could not process payment. Please try again or use a different card.')
                });
                if (error && error.message) {
                     $('#mercadopago-error-message').text(error.message).show();
                }
                deferred.reject();
                fullScreenLoader.stopLoader();
            });

            return deferred.promise();
        },
        
        // This method will be called by Magento's placeOrder binding
        // after getPlaceOrderDeferredObject resolves.
        // We override placeOrder from default.js to ensure our logic runs first.
        // However, cc-form's placeOrder is what we need to call if token is ready.
        // The structure of Magento_Payment/js/view/payment/cc-form placeOrder is:
        // placeOrder: function (data, event) {
        //    var self = this;
        //    if (event) { event.preventDefault(); }
        //    if (this.validate() && additionalValidators.validate()) {
        //        this.isPlaceOrderActionAllowed(false);
        //        this.getPlaceOrderDeferredObject() // This is what we defined above!
        //            .fail(function () { self.isPlaceOrderActionAllowed(true); })
        //            .done(function () { /* ... actual Magento place order ... */ });
        //        return true;
        //    }
        //    return false;
        // }
        // So, our getPlaceOrderDeferredObject will handle token creation, then resolve.
        // The `done` callback in the original cc-form's placeOrder should then proceed.

        createCardToken: async function () {
            var self = this;
            $('#mercadopago-error-message').hide().text(''); // Clear previous errors

            const cardholderName = $('#mpCardholderName').val();
            const docType = $('#mpDocType').val();
            const docNumber = $('#mpDocNumber').val();

            if (!cardholderName || !docType || !docNumber) {
                 const errorMessages = [];
                 if(!cardholderName) errorMessages.push($t('Cardholder name is required.'));
                 if(!docType) errorMessages.push($t('Document type is required.'));
                 if(!docNumber) errorMessages.push($t('Document number is required.'));
                 self.displayMpError(errorMessages.join(' '));
                 return Promise.reject({message: errorMessages.join(' ')});
            }
            
            try {
                const token = await self.mpInstance.fields.createCardToken({
                    cardholderName: cardholderName,
                    identificationType: docType,
                    identificationNumber: docNumber,
                    // The card number, expiration date and security code are handled by the Secure Fields (mpCardForm object)
                });
                return token;
            } catch (error) {
                 console.error('SDK token creation error:', error);
                 let friendlyMessage = $t('Payment processing error. Please review your card details.');
                 if (error.message) {
                     friendlyMessage = error.message; // Use MP's error if available
                 } else if (Array.isArray(error) && error.length > 0 && error[0].message) {
                     friendlyMessage = error[0].message;
                 }
                 self.displayMpError(friendlyMessage);
                 return Promise.reject({ message: friendlyMessage });
            }
        },

        getData: function () {
            var parentData = this._super(); // Gets data from Magento_Payment/js/view/payment/cc-form
            var additionalData = {
                'mercadopago_card_token': this.cardToken,
                'mercadopago_payment_method_id': this.paymentMethodId, // From BIN detection
                'mercadopago_installments': $('#mpInstallments').val(),
                'mercadopago_issuer_id': $('#mpIssuer').val() || null, // Can be null
                'mp_doc_type': $('#mpDocType').val(),
                'mp_doc_number': $('#mpDocNumber').val(),
                'mp_cardholder_name': $('#mpCardholderName').val()
            };
            
            // Merge our data into Magento's payment data structure
            return $.extend(true, parentData, {
                'additional_data': additionalData
            });
        },
        
        validate: function () {
            // Basic validation, can be expanded.
            // The Mercado Pago SDK will do most of the heavy lifting for card details.
            var $form = $('#' + this.getCode() + '-form');
            return $form.validation() && $form.validation('isValid');
        },

        displayMpError: function(errors) {
            let message = '';
            if (typeof errors === 'string') {
                message = errors;
            } else if (Array.isArray(errors) && errors.length > 0) {
                message = errors.map(err => err.message || err).join('\n');
            } else if (errors.message) {
                message = errors.message;
            } else {
                message = $t('An unexpected error occurred.');
            }
            $('#mercadopago-error-message').text(message).show();
        },

        // Event handlers for select changes, if needed for reactivity with Knockout
        onDocTypeChange: function(data, event) {
            this.selectedDocType(event.target.value);
        },
        onInstallmentsChange: function(data, event) {
            this.selectedInstallments(event.target.value);
        },
        onIssuerChange: function(data, event) {
            this.selectedIssuerId(event.target.value);
        }
    });
});
