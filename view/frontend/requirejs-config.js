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
            creditCardType: 'Magento_Payment/js/cc-type',
            'Magento_Payment/cc-type': 'Magento_Payment/js/cc-type'
        }
    }
};