=== Invelity MyGLS connect ===
Author: Invelity s.r.o.
Author URI: https://www.invelity.com
Tags: GLS, shipping, WooCommerce
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=38W6PN4WHLK32
Requires at least: 5.0.0
Tested up to: 6.5.3
Stable tag: 6.4
Requires PHP: 8.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Jednoduchý prenos objednávok do GLS cez API a tlač štítkov

== Description ==
Plugin Vám umožnuje jednoduchý prenos údajov o objednávkach z WordPress adminu priamo do systému GLS online bez exportovania/importovania akýchkolvek súborov pomocou API volaní. Po odoslaní objednávok do stystému MyGLS si priamo z WordPress stiahnete štítky na tlač vo formáte PDF.

== Installation ==

Táto sekcia popisuje inštaláciu pluginu.

1. Stiahnite plugin a nahrajte ho priamo cez FTP (`/wp-content/plugins/invelity-mygls-connect) alebo plugin stiahnite priamo z Wordpress repozitára.
2. Aktivujte plugin cez 'Plugins' obrazovku vo WordPress.
3. V hlavnom menu (ľavý sidebar) uvidíte položku "Invelity plugins" a jej pod-položku "Invelity MyGLS connect".
4. Vpíšte všetky potrebné údaje vrátane údajov ktoré ste dostali priamo od služby GLS.
5. Po správnom nastavení pluginu môžete pristúpiť k exportovaniu údajov o objednávkach do GLS.
6. Vo výpise objednávok zaškrtnite objednávky ktoré chcete exportovať do GLS. Z drop-down zvoľte možnosť "Export MyGLS connect"
7. Systém vám vygeneruje PDF ktoré môžete rovno tlačiť. Ak ste označili viac objednávok nájdete na jednej strane 4 štítky.
8. Možnosť nastavenia vlastného emailu pre tracking kód a vlastného poznámky k objednávke.

== Frequently Asked Questions ==

= Potrebujem ešte niečo pre správnu funkcionalitu tohto pluginu? =

Áno, potrebujete mať dohodnutú spoluprácu s GLS a pristupové údaje do MyGLS.

= Je tento plugin zdarma? =

Áno. Plugin ponúkame úplne zdarma v plnej verzii bez akýchkoľvek obmedzení, avšak bez akejkoľvek garancie podpory alebo funkcionality.
Podporu nad rámec hlavnej funkcionality pluginu ako jeho úpravy, nastavenia alebo inštálácie poskytujeme za poplatok po dohode.
V prípade záujmu nás kontaktujte na https://www.invelity.com/ alebo priamo na mike@invelity.com

== Screenshots ==

1. Konfigurácia pluginu
/assets/screenshot-1.png
2. Používanie pluginu
/assets/screenshot-2.png
3. Používanie GLS online

== Change log ==

= 1.0.0 =
* Plugin Release
= 1.0.1 =
fixed cod value
= 1.0.2 =
fixed cod floatval remove
= 1.0.3 =
fixed curl errors
= 1.0.4 =
added compatibility with Invelity GLS ParcelShop
= 1.0.5 =
updated compatibility , added custom note , order number ,tracking mail sending
= 1.0.6 =
added tracking email subject
= 1.0.7 =
bugfixes added library
= 1.0.8 =
download link fix
= 1.0.9 =
FDS,FSS compatibility
= 1.1.0 =
code cleanup, php compatibility,hpos preparation