var config = {
    'paths': {
        'bootstrap': 'Openpay_Cards/js/bootstrap'
    },
    'shim': {
        'bootstrap': {            
            'deps': ['jquery']
        }
    },
    map: {
        '*': {
            'Magento_Payment/js/model/credit-card-validation/credit-card-number-validator/credit-card-type':'Openpay_Cards/js/model/credit-card-validation/credit-card-number-validator/credit-card-type'       
        }
    }
};