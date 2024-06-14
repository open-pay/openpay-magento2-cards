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

        window.handleInstallmentsPE = function (installmentsToAdd) {
            let installmentsOptions = $('#installments option');
            let installementsSelect = $('#installments');
            installmentsOptions.each(function () { 
                $(this).remove();   
            });

            installmentsToAdd.forEach(function (element){
                installementsSelect.append($('<option>', {
                    value: element,
                    text: (element == 1) ? 'Una sola cuota' : element + ' cuotas'
                }));
            });
        }

        window.handleShowOrHideInstallments = function (data, country, months) {
            if (data.card_type.toUpperCase() === 'CREDIT') {
                if (country == 'MX' && months.length > 1) $("#openpay_cards_interest_free").show();
                if (country == 'CO') $("#openpay_installments").show();
                if (country == 'PE') {
                    data.installments = data.installments.map(Number);
                    if(data.installments.indexOf(1) < 0) data.installments.push(1);
                    data.installments.sort( function (a,b) { return a - b });
                    window.handleInstallmentsPE(data.installments);
                    if(data.installments.length > 1) {
                        $("#openpay_installments_title").text("Cuotas con Interés");
                        if (data.withInterest || data.withInterest === null ){
                            $("#openpay_installments_title").text("Cuotas con Interés");
                            $("#withInterest").val(true);
                        }else{
                            $("#openpay_installments_title").text("Cuotas sin Interés");
                            $("#withInterest").val(false);
                        }
                        $("#openpay_installments").show();
                    } else {
                        $("#openpay_installments").hide();
                        $('#openpay_installments option[value="1"]').attr("selected",true);
                    }
                }
            } else {
                if (country == 'MX') {
                    $("#openpay_cards_interest_free").hide();
                    $('#openpay_cards_interest_free option[value="1"]').attr("selected",true);
                    $("#total-monthly-payment").hide();
                } else {
                    $("#openpay_installments").hide();
                    $('#openpay_installments option[value="1"]').attr("selected",true);
                }
            }
        }

        var customerData = null;
        var total = window.checkoutConfig.payment.total;
        var card_old = null;
        $(document).on("keypress focusout", "#openpay_cards_cc_number", function() {
            var card = $(this).val();
            var urlStore = window.checkoutConfig.payment.url_store;
            var months = window.checkoutConfig.payment.months_interest_free;
            var country = window.checkoutConfig.payment.country;
            let isAvailableInstallmentsPE = window.checkoutConfig.payment.isAvailableInstallments;
            let lng = country == 'PE' ? 6 : 8;
            var bin = null;

            if (card.length >= lng) {
                if (country == 'MX' && months.length < 3) {
                    return;
                }
                if (country == 'PE' && isAvailableInstallmentsPE == "0" ){
                    console.log("Return without request bin PE");
                    return;
                }
                var bin = card.substring(0, lng);
                if(card_old  != bin){
                    card_old = bin;
                    $.ajax({
                        url : urlStore+'openpay/payment/getTypeCard',
                        type : 'POST',
                        showLoader: true,
                        data: {
                            card_bin: bin,
                            amount: total
                        },
                        dataType:'json',
                        success : function(data) {
                            if(data.status == 'success') {
                                window.handleShowOrHideInstallments(data, country, months);
                            } else {
                                $("#openpay_cards_interest_free").hide();
                                $('#openpay_cards_interest_free option[value="1"]').attr("selected",true);
                                $("#openpay_installments").hide();
                                $('#openpay_installments option[value="1"]').attr("selected",true);
                            }
                        },
                        error : function(request,error)
                        {
                            console.log("Error");
                            $("#openpay_cards_interest_free").hide();
                            $('#openpay_cards_interest_free option[value="1"]').attr("selected",true);
                            $("#openpay_installments").hide();
                            $('#openpay_installments option[value="1"]').attr("selected",true);
                            $("#total-monthly-payment").hide();
                        }
                    });
                }
            }
        });

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

        //$("body").append('<div class="modal fade" role="dialog" id="card-points-dialog"> <div class="modal-dialog modal-sm"> <div class="modal-content"> <div class="modal-header"> <h4 class="modal-title">Pagar con Puntos</h4> </div> <div class="modal-body"> <p>¿Desea usar los puntos de su tarjeta para realizar este pago?</p> </div> <div class="modal-footer"> <button type="button" class="btn btn-success" data-dismiss="modal" id="points-yes-button">Si</button> <button type="button" class="btn btn-default" data-dismiss="modal" id="points-no-button">No</button> </div> </div> </div></div>');

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

            getInstallments: function() {
                return window.checkoutConfig.payment.installments;
            },

            creditCardOption: function() {
                customerData = quote.billingAddress._latestValue;
                console.log('#openpay_cc', $('#openpay_cc').val());
                let ccOptionArray = $( "#openpay_cc option:selected" ).text().split(' ');
                let card = ccOptionArray[ccOptionArray.length - 1];

                if ($('#openpay_cc').val() !== "new" && window.checkoutConfig.payment.can_save_cc === '2') {
                    $('#save_cc').prop('checked', false);
                    $('#save_cc').prop('disabled', true);
                    var binValidate = Number(card.substring(0,6));
                    $('#openpay_cards_cc_number').val(binValidate).trigger('keypress');
                    $('#openpay_cards_cc_number').val(binValidate).change();
                    $("#openpay_cards_expiration").val("").change();
                    $("#openpay_cards_expiration_yr").val("").change();
                    $('#openpay_cards_cc_cid').val("");

                    $('#save_cc_fieldset').hide();
                    //$('#payment_form_openpay_cards > div').not($("#openpay_cards_cc_type_cvv_div"), $("#seleccionar_tarjeta"), $("#tarjetas_aceptadas")).hide();
                    $("#numero_de_tarjeta").hide();
                    $("#openpay_cards_cc_type_exp_div").hide();
                    $("#openpay_cards_cc_type_cvv_div").hide();

                } else if ($('#openpay_cc').val() !== "new" && window.checkoutConfig.payment.can_save_cc === '1'){
                    $('#save_cc').prop('checked', false);                
                    $('#save_cc').prop('disabled', true);                 
                    var binValidate = Number(card.substring(0,6));
                    $('#openpay_cards_cc_number').val(binValidate).trigger('keypress');
                    $('#openpay_cards_cc_number').val(binValidate).change();                                 
                    $("#openpay_cards_expiration").val("").change();
                    $("#openpay_cards_expiration_yr").val("").change();
                    $('#openpay_cards_cc_cid').val("");

                    $('#save_cc_fieldset').hide();
                    $("#numero_de_tarjeta").hide();
                    $("#openpay_cards_cc_type_exp_div").hide();

                } else if (window.checkoutConfig.payment.can_save_cc === '0'){
                    $('#save_cc_fieldset').hide();
                    $("#seleccionar_tarjeta").hide();
                    
                }else {
                    $('#openpay_cards_cc_number').val('').change();
                    $('#payment_form_openpay_cards > div').show();
                    $('#save_cc_fieldset').show();
                    $('#save_cc').prop('disabled', false);
                }
            },

            showMonthsInterestFree: function() {
                var months = window.checkoutConfig.payment.months_interest_free;
                var total = window.checkoutConfig.payment.total;
                total = parseInt(total);

                return months.length > 1 ? true : false;
            },

            showInstallments: function() {
                var installments = window.checkoutConfig.payment.installments;
                return installments.length > 1 ? true : false;
            },
            canSaveCC: function() {
                if ( window.checkoutConfig.payment.can_save_cc === '1') {
                    console.log("save_cc option", window.checkoutConfig.payment.can_save_cc)
                    return true
                } else if (window.checkoutConfig.payment.can_save_cc === '2') {
                    console.log("save_cc option", window.checkoutConfig.payment.can_save_cc)
                    return true
                } else {
                    console.log("save_cc option", window.checkoutConfig.payment.can_save_cc)
                    return false;
                }
            },
            existsOneCreditCard: function() {
                return window.checkoutConfig.payment.exists_one_credit_card;
            },

            isLoggedIn: function() {
                console.log('isLoggedIn()', window.checkoutConfig.payment.is_logged_in);
                return window.checkoutConfig.payment.is_logged_in;
            },

            notLogged: function(){
                return !window.checkoutConfig.payment.is_logged_in;
            },
            
            getCreditCardList: function() {
                console.log('getCreditCardList()', window.checkoutConfig.payment.cc_list);
                return window.checkoutConfig.payment.cc_list;
            },

            /**
             * Prepare and process payment information
             */
            preparePayment: function () {
                var self = this;
                var $form = $('#' + this.getCode() + '-form');

                var isSandbox = window.checkoutConfig.payment.openpay_credentials.is_sandbox;
                if (isSandbox || isSandbox === "1") {
                    isSandbox = true;
                } else {
                    isSandbox = false;
                }
                var useCardPoints = window.checkoutConfig.payment.use_card_points;
                if (useCardPoints || useCardPoints === "1") {
                    useCardPoints = true;
                } else {
                    useCardPoints = false;
                }
                OpenPay.setId(window.checkoutConfig.payment.openpay_credentials.merchant_id);
                OpenPay.setApiKey(window.checkoutConfig.payment.openpay_credentials.public_key);
                OpenPay.setSandboxMode(isSandbox);

                //antifraudes
                OpenPay.deviceData.setup(this.getCode() + '-form', "device_session_id");

                console.log('#openpay_cc', $('#openpay_cc').val());

                if ($('#openpay_cc').val() !== 'new') {
                    console.log('Pagar con token', $('#openpay_cc').val());
                    this.messageContainer.clear();
                    self.placeOrder();
                    return;
                }
                
                if ($form.validation() && $form.validation('isValid')) {
                    this.messageContainer.clear();

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

                    OpenPay.token.create(data, function(response) {
                            var token_id = response.data.id;
                            $("#openpay_token").val(token_id);

                            if (!response.data.card.points_card || !useCardPoints) {
                                console.log('NO useCardPoints');
                                self.placeOrder();
                                return;
                            }

                            var r = confirm("¿Desea usar los puntos de su tarjeta para realizar este pago?");
                            if (r === true) {
                                $('#use_card_points').val('true');
                            } else {
                                $('#use_card_points').val('false');
                            }
                            self.placeOrder();
                            //$("#card-points-dialog").modal("show");
                        },
                        function(response) {
                            console.log("token error");
                            console.log(response.data.description)
                            self.messageContainer.addErrorMessage({
                                message: "La petición no pudo ser procesada"
                            });
                        }
                    );
                } else {
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
                        'cc_number': this.creditCardNumber(),
                        'openpay_token': $("#openpay_token").val(),
                        'device_session_id': $('#device_session_id').val(),
                        'installments': $('#installments').val(),
                        'interest_free': $('#interest_free').val(),
                        'use_card_points': $('#use_card_points').val(),
                        'save_cc': $("#save_cc").is(':checked') ? '1' : '0',
                        'openpay_cc': $('#openpay_cc').val(),
                        'with_interest':$("#withInterest").val()
                    }
                };
            },
            validate: function() {
                customerData = quote.billingAddress._latestValue;
                var $form = $('#' + this.getCode() + '-form');
                if(typeof customerData.countryId === 'undefined' || customerData.countryId.length === 0) {
                    return false;
                }
                if($('#openpay_cc').val() !== 'new'){
                    $('.field.number').removeClass("required");
                    $("#openpay_cards_cc_type_exp_div").removeClass("required");
                } else {
                    $('.field.number').addClass("required");
                    $("#openpay_cards_cc_type_exp_div").addClass("required");
                }
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

                if(typeof customerData.postcode === 'undefined' || customerData.postcode === null || customerData.postcode.length === 0) {
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
