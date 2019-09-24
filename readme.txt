=== WooCommerce CITADEL Merchant Gateway ===
Plugin Name: WooCommerce CITADEL Merchant Gateway
Plugin URI: https://github.com/phelephant/woo-citadelmerchant
Author: Phelephant
Author URI: https://github.com/phelephant
Stable tag: 1.2
Tags: Woocommerce Checkout, Digital Goods, CITADEL
Requires at least: 5.2
Tested up to: 5.2.3
Copyright: (c) 2019 Phelephant
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

This plugin adds CITADEL Merchant as a payment gateway for your WooCommerce store. To get it working, you will need a <a href="https://citadel.li/merchant">CITADEL Merchant</a> account, and a set of API keys.

== Description ==
CITADEL Merchant allows one to accept payments in various cryptocurrencies. This plugin provides a Payment Gateway for WooCommerce, so that your store can accept payments using CITADEL.

= Plugin Functionality: =

* Adds CITADEL Merchant as payment option.
* Allows you to enable/disable exposing Cart details to CITADEL.
* Allows you to select coins you wish to accept.

== Installation ==

= Minimum Requirements =

* WooCommerce 3.0 or higher

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of WooCommerce Checkout for Digital Goods, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "WooCommerce CITADEL Merchant Gateway" and click "Search Plugins". Once you've found this plugin you can install it by simply clicking "Install Now".

= Manual Installation =

1. Unzip the files and upload the folder into your plugins folder (/wp-content/plugins/) overwriting older versions if they exist.
2. Activate the plugin in your WordPress admin area.

== Screenshots ==

1. New payment option in the Admin panel.
2. Configurable.
3. Error reporting.
4. URL verification is handled elsewhere. :(

== Frequently Asked Questions ==

== Upgrade Notice ==

Automatic updates should work fine. As always, though, backup your existing site, prior to making any updates, just to be sure you can roll back, if anything goes wrong.

== Changelog ==
= 1.2 - 24-09-2019 =
* Display actual CITADEL API error message to the end-user.
* Do proper error logging via WooCommerce facilities.
* Improve coin precision / sub-total calculations.
= 1.1 - 22-09-2019 =
* Handle situations when WooCommerce is not yet installed.
= 1.0 - 24-06-2019 =
* Inital release.
