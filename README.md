Openpay-Magento2
======================

Openpay payment gateway Magento2 extension


Install
=======

1. Go to Magento2 root folder

2. Enter following commands to install module:

    ```bash
    composer config repositories.openpaymagento git https://github.com/fedebalderas/openpay-magento2.git
    composer require openpay/magento2
    ```
   Wait while dependencies are updated.

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable Openpay_Cards --clear-static-content
    php bin/magento setup:upgrade
    ```

4. Enable and configure Openpay in Magento Admin under Stores/Configuration/Payment Methods/Openpay


