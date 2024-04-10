# Openpay-Magento2-Cards

Extensión de pagos con tarjeta de crédito de Openpay para Magento2 (v2.4.2)


## Instalación

Ir a la carpeta raíz del proyecto de Magento y seguir los siguiente pasos:

```bash    
composer require openpay/magento2-cards:3.5.*
php bin/magento module:enable Openpay_Cards --clear-static-content
php bin/magento setup:upgrade
php bin/magento cache:clean
```


## Actualización
En caso de ya contar con el módulo instalado y sea necesario actualizar, seguir los siguientes pasos:

```bash
composer clear-cache
composer update openpay/magento2-cards
bin/magento setup:upgrade
php bin/magento cache:clean
```

## Screenshots


### 1. Administración. Configuración del módulo

Para configurar el módulo desde el panel de administración de la tienda diríjase a: Stores > Configuration > Sales > Payment Methods > Openpay

#### Características

- Procesar cargos de forma **Directa**, a través de **3D Secure** y con **Autenticación Selectiva**.
- Configuración de **cargo inmediatos** o si serán únicamente **pre-autorizaciones**.
- Pago con puntos (solo si la tarjeta cuenta con esta característica).
- Permitir a los clientes guardar su tarjeta para futuras transacciones (se requiere que el cliente tenga una cuenta en la tienda).
- Ofrecer meses sin intereses ([revisar condiciones y comisiones](https://mage2.pro/t/755)).
- Limitar las tarjetas soportadas.

![](https://s3.amazonaws.com/openpay-plugins-screenshots/magento2/configuracion_tarjetas.png)

### 2. Tienda. Pago utilizando una nueva tarjeta

![](https://s3.amazonaws.com/openpay-plugins-screenshots/magento2/checkout_sencillo.png)

### 3. Tienda. Pago utilizando una tarjeta guardada

![](https://s3.amazonaws.com/openpay-plugins-screenshots/magento2/checkout_tarjeta_guardada.png)

### 4. Administración. Pre-autorizaciones

Cuando se tiene habilitada la opción de pre-autorizaciones, el monto de la transacción le será congelado al cliente en su tarjeta en espera de que el administrador de la tienda haga efectivo el cargo.

El detalle de una compra que haya sido únicamente pre-autorizada se mostrará con adeudo y un monto  por pagar del total de la cuenta, tal como se muestra en la imagen siguiente:

![](https://s3.amazonaws.com/openpay-plugins-screenshots/magento2/preautorizacion_1.png)

Para hacer efectivo el cargo a la tarjeta el administrador deberá "facturar" la compra:
- Ingresar al detalle de la orden de compra
- Ir a la sección Factura (Invoice)
- Al final de la pantalla capturar el pago

![](https://s3.amazonaws.com/openpay-plugins-screenshots/magento2/preautorizacion_2.png)

Una vez realizado esto, el cobro será realizado a la tarjeta relacionada a la compra. 

**NOTA: Importante considerar los siguientes puntos:**
- Si el cargo no se ha hecho efectivo en los 7 días subsecuentes de la compra, el monto congelado en la tarjeta será liberado y no será posible aplicar el cobro.
- Cuando se genere la factura, es necesario seleccionar **"Capture Online"**.

### 4. Administración. Reembolsos

Para efectuar un reembolso será necesario:
- Ingresar al detalle de la orden de compra
- Ir a la sección de **Facturas** (Invoices) e ingresar a su detalle
- Generar una **Nota de Crédito** (Credit Memo)
- Ingresar el monto a reemnolsar y ejecutar la acción

![](https://s3.amazonaws.com/openpay-plugins-screenshots/magento2/reembolso.png)

