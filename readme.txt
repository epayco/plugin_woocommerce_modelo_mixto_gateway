=== Davipay plugin for WooCommerce ===
Contributors: Davipay Team
Donate link: https://www.davipay.com/
Tags: e-commerce, commerce, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, configurable, davipay
Requires at least: 7.4
Tested up to: 6.8.3
Stable tag: 9.0.1
Requires PHP: 7.4
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Receive payments, accept credit cards, deposits and transfers.

== Description ==


Davipay helps you grow your business with rates tailored to your needs.
Receive payments, accept credit cards, deposits and transfers.

== Installation ==

1. Download the plugin
2. Enter the administrator of your WordPress.
3. Enter Plugins / Add-New / Upload-Plugin.
4. Find the plugin downloaded on your computer and upload it like any other file.
5. After installing the .zip you can see it in the list of installed plugins, you can activate or deactivate it.
6. To configure the plugin you must go to: WooCommerce / Settings / Payments and Locate the tab Davipay.
7. Configure the plugin by entering P_CUST_ID_CLIENTE, P_KEY, PUBLIC_KEY and PRIVATE_KEY provided by Davipay
8. Save Changes, if you have not done the configuration correctly you will be shown a warning, pay attention to this.


== Frequently Asked Questions ==

= What types of payment methods do they receive? =

accept credit cards, debit cars, cash,  deposits and transfers.

= Are you required to use a ssl certificate ? =

Yes. But it is advisable that you consider using it since it is revealing for browsers

== Screenshots ==

1. The front-end view

== Changelog ==

= 9.0.1 =
* SDK-1397: un reintento de pago que pasa a Aceptado después de rechazos descuenta el stock una sola vez (reducción nativa de WooCommerce, idempotente).
* Un rechazo posterior restaura el stock solo si estaba descontado; los rechazos sucesivos no alteran el inventario.
* Un webhook tardío de rechazo ya no cancela un pedido ya pagado.
* El filtro de stock lee la opción de reducir en pendiente igual que la gateway.
* SDK-1420: la confirmación de pago se verifica contra apiflow con token Bearer; el estado, monto, moneda y referencia se toman de ePayco y no de la petición.
* La orden debe coincidir con la factura de ePayco y cada ref_payco solo puede aplicarse a una orden.
* El token de apify se reutiliza desde un caché de WordPress (10 min) en lugar de cookies del navegador.
* La sincronización de pendientes procesa lotes de 20 órdenes, de la más antigua a la más reciente.

= 6.7.0 =
* Initial stable release

= 8.1.0 =
* upload release

= 8.1.1 =
* upload release

= 8.2.1 =
* upload release

= 8.2.2 =
* upload release

== Additional Info ==
**Contribute** [repository on github](https://github.com/epayco/plugin_woocommerce_modelo_mixto_gateway)