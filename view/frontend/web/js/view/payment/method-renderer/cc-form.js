/**
 * Openpay_Cards Magento JS component
 *
 * @category    Openpay
 * @package     Openpay_Cards
 * @author      Federico Balderas
 * @copyright   Openpay (http://openpay.mx)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $, quote, customer) {
        'use strict';

        //console.log(window.checkoutConfig.customerData);
        //console.log(customer.customerData);
        //console.log(quote.billingAddress._latestValue);
        var customerData = null; 
        var total = window.checkoutConfig.payment.total;        
        
        $(document).on("change", "#interest_free", function() {        
            var monthly_payment = 0;
            var months = parseInt($(this).val());     

            if (months > 1) {
                $("#total-monthly-payment").css("display", "inline");
            } else {
                $("#total-monthly-payment").css("display", "none");
            }

            monthly_payment = total/months;
            monthly_payment = monthly_payment.toFixed(2);            
            
            $("#monthly-payment").text(monthly_payment);
        });

        return Component.extend({

            defaults: {
                template: 'Openpay_Cards/payment/openpay-form'
            },

            getCode: function() {
                return 'openpay_cards';
            },

            isActive: function() {
                return true;
            },
            
            getMonthsInterestFree: function() {
                return window.checkoutConfig.payment.months_interest_free;                
            },
            
            showMonthsInterestFree: function() {
                var months = window.checkoutConfig.payment.months_interest_free;         
                var minimum_amount = window.checkoutConfig.payment.minimum_amount;         
                var total = window.checkoutConfig.payment.total;
                total = parseInt(total);
                
                return (months.length > 1 && total >= minimum_amount) ? true : false;                
            },
            
            /**
             * Prepare and process payment information
             */
            preparePayment: function () {
                var self = this;
                var $form = $('#' + this.getCode() + '-form');

                if($form.validation() && $form.validation('isValid')){
                    this.messageContainer.clear();

                    var isSandbox = window.checkoutConfig.payment.openpay_credentials.is_sandbox === "0" ? false : true;
                    OpenPay.setId(window.checkoutConfig.payment.openpay_credentials.merchant_id);
                    OpenPay.setApiKey(window.checkoutConfig.payment.openpay_credentials.public_key);
                    OpenPay.setSandboxMode(isSandbox);                    

                    //antifraudes
                    OpenPay.deviceData.setup(this.getCode() + '-form', "device_session_id");

                    var year_full = $('#openpay_cards_expiration_yr').val();
                    var holder_name = this.getCustomerFullName();
                    var card = $('#openpay_cards_cc_number').val();
                    var cvc = $('#openpay_cards_cc_cid').val();
                    var year = year_full.toString().substring(2, 4);
                    var month = $('#openpay_cards_expiration').val();

                    var data = {
                        holder_name: holder_name,
                        card_number: card.replace(/ /g, ''),
                        expiration_month: month || 0,
                        expiration_year: year,
                        cvv2: cvc
                    };

                    if(this.validateAddress() !== false){
                        data["address"] = this.validateAddress();
                    }

                    OpenPay.token.create(
                        data,
                        function(response) {
                            var token_id = response.data.id;
                            $("#openpay_token").val(token_id);                            
                            //$form.append('<input type="hidden" id="openpay_token" name="openpay_token" value="' + token_id + '" />');
                            self.placeOrder();
                        },
                        function(response) {
                            console.log("token error");
                            this.messageContainer.addErrorMessage({
                                message: response.data.description
                            });
                        }
                    );
                }else{
                    return $form.validation() && $form.validation('isValid');
                }
            },
            /**
             * @override
             */
            getData: function () {
                return {
                    'method': "openpay_cards",
                    'additional_data': {
                        'cc_cid': this.creditCardVerificationNumber(),
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),
                        'cc_number': this.creditCardNumber(),
                        'openpay_token': $("#openpay_token").val(),
                        'device_session_id': $('#device_session_id').val(),
                        'interest_free': $('#interest_free').val()
                    }
                };
            },
            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },
            getCustomerFullName: function() {             
                customerData = quote.billingAddress._latestValue;  
                return customerData.firstname+' '+customerData.lastname;                
            },
            validateAddress: function() {
                customerData = quote.billingAddress._latestValue;  
                if(typeof customerData.city === 'undefined' || customerData.city.length === 0) {
                  return false;
                }

                if(typeof customerData.countryId === 'undefined' || customerData.countryId.length === 0) {
                  return false;
                }

                if(typeof customerData.postcode === 'undefined' || customerData.postcode.length === 0) {
                  return false;
                }

                if(typeof customerData.street === 'undefined' || customerData.street[0].length === 0) {
                  return false;
                }                

                if(typeof customerData.region === 'undefined' || customerData.region.length === 0) {
                  return false;
                }
                
                var address = {
                    city: customerData.city,
                    country_code: customerData.countryId,
                    postal_code: customerData.postcode,
                    state: customerData.region,
                    line1: customerData.street[0],
                    line2: customerData.street[1]
                }

                return address;
            }
        });
    }
);
