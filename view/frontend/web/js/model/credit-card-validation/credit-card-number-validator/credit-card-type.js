define([
    'jquery',
    'mageUtils'
], function ($, utils) {
    'use strict';

    var types = [
        {
            title: 'Visa',
            type: 'VI',
            pattern: '^4\\d*$',
            gaps: [4, 8, 12],
            lengths: [16],
            code: {
                name: 'CVV',
                size: 3
            }
        },
        {
            title: 'MasterCard',
            type: 'MC',
            pattern: '^(?:5[1-5][0-9]{2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{2}|27[01][0-9]|2720)[0-9]{12}$',
            gaps: [4, 8, 12],
            lengths: [16],
            code: {
                name: 'CVC',
                size: 3
            }
        },
        {
            title: 'American Express',
            type: 'AE',
            pattern: '^3([47]\\d*)?$',
            isAmex: true,
            gaps: [4, 10],
            lengths: [15],
            code: {
                name: 'CID',
                size: 4
            }
        },
        {
            title: 'Diners',
            type: 'DN',
            pattern: '^(3(0[0-5]|095|6|[8-9]))\\d*$',
            gaps: [4, 10],
            lengths: [14, 16, 17, 18, 19],
            code: {
                name: 'CVV',
                size: 3
            }
        },
        {
            title: 'Carnet',
            type: 'CN',
            pattern: '^(286900|502275|506(199|2(0[1-6]|1[2-578]|2[289]|3[67]|4[579]|5[01345789]|6[1-79]|7[02-9]|8[0-7]|9[234679])|3(0[0-9]|1[1-479]|2[0239]|3[02-79]|4[0-49]|5[0-79]|6[014-79]|7[0-4679]|8[023467]|9[1234689])|4(0[0-8]|1[0-7]|2[0-46789]|3[0-9]|4[0-69]|5[0-79]|6[0-38]))|588772|604622|606333|627535|636(318|379)|639(388|484|559))',
            lengths: [16],
            code: {
                name: 'CVV',
                size: 3
            }
        }
    ];

    return {
        /**
         * @param {*} cardNumber
         * @return {Array}
         */
        getCardTypes: function (cardNumber) {

            var i, value,
                result = [];

            if (utils.isEmpty(cardNumber)) {
                return result;
            }

            if (cardNumber === '') {
                return $.extend(true, {}, types);
            }

            for (i = 0; i < types.length; i++) {
                value = types[i];

                if (new RegExp(value.pattern).test(cardNumber)) {
                    result.push($.extend(true, {}, value));
                }
            }
            return result;
        }
    };
});
