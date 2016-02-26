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
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $) {
        'use strict';
        
        var customer = window.checkoutConfig.customerData;   
        var customer_address = customer.addresses[0];

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
            /**
             * Prepare and process payment information
             */
            preparePayment: function () {
                var self = this;                
                var $form = $('#' + this.getCode() + '-form');                
                                                
                if($form.validation() && $form.validation('isValid')){                    
                    this.messageContainer.clear();                                                                  
                                        
                    OpenPay.setId(window.checkoutConfig.payment.openpay_credentials.merchant_id);
                    OpenPay.setApiKey(window.checkoutConfig.payment.openpay_credentials.public_key);
                    OpenPay.setSandboxMode(true);

                    //antifraudes
                    OpenPay.deviceData.setup(this.getCode() + '-form', "device_session_id");

                    var year_full = $('#openpay_cards_expiration_yr').val();
                    var holder_name = customer.firstname+" "+customer.lastname;
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
                                        
                    if(this.validateAddress()){
                        data["address"] = {
                            city: customer_address.city,
                            country_code: customer_address.country_id,
                            postal_code: customer_address.postcode,
                            state: customer_address.region.region,
                            line1: customer_address.street[0],
                            line2: customer_address.street[1]
                        }
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
                    }
                };
            },            
            validate: function() {                
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },
            validateAddress: function() {                    
                var customer_address = window.checkoutConfig.customerData.addresses[0];
                
                if(typeof customer_address === 'undefined') {
                    console.log("customer_address is no defined yet");
                    return false;
                }
                
                if(typeof customer_address.city === 'undefined' && customer_address.city === null) {
                  return false;
                }
                
                if(typeof customer_address.country_id === 'undefined' && customer_address.country_id === null) {
                  return false;
                }
                
                if(typeof customer_address.postcode === 'undefined' && customer_address.postcode === null) {
                  return false;
                }
                
                if(typeof customer_address.street[0] === 'undefined' && customer_address.street[0] === null) {
                  return false;
                }
                
                if(typeof customer_address.region.region === 'undefined' && customer_address.region.region === null) {
                  return false;
                }
                
                return true;
            }
        });
    }
);
