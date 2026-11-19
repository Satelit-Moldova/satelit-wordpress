=== Satelit Moldova Data ===
Contributors: satelit
Tags: moldova, exchange rates, fuel prices, woocommerce, cinema
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

BNM exchange rates, a MDL converter, fuel prices, shop prices, news, cinema schedules, Monitorul Oficial and the joke of the day, from the Satelit API.

== Description ==

Satelit Moldova Data shows data about the Republic of Moldova on your site, from the [Satelit API](https://developer.satelit.md/en/docs/). Every feature is a shortcode, a block, and (where it fits a sidebar) a classic widget.

* `[satelit_rates codes="EUR,USD,RON,RUB,UAH"]`: official BNM rates with the 7-day change.
* `[satelit_converter]`: MDL currency converter (today's BNM rates are embedded in the page, nothing is fetched by the browser).
* `[satelit_fuel]`: fuel prices per network, the ANRE cap and the cheapest network.
* `[satelit_prices q="lapte" limit="5"]`: product price comparison in Moldovan shops (lowest and median price, number of shops).
* `[satelit_product id="217"]`: every shop's offer for one product.
* `[satelit_news limit="5" category=""]`: latest news cards with links to their outlets.
* `[satelit_cinema city="Chișinău"]`: what's on in cinemas today: posters and session times per cinema.
* `[satelit_movie id="m603"]`: a film card.
* `[satelit_law days="7"]`: Monitorul Oficial digest list.
* `[satelit_joke]`: the joke of the day.

**WooCommerce** (only when it is active): a "Prices in other shops" product tab fed by a search term or a Satelit item id that you set in a box on the product screen, and an optional line under the price with the product's price in EUR/USD (or other codes) at today's BNM rate.

Translations: Romanian and Russian are included.

= Service and data =

This plugin is a client of the Satelit API (https://api.satelit.md), a paid service. It sends requests from your server only (never from visitors' browsers) with your API key, the route (for example `/v1/rates`) and the parameters of the shortcode (search words, city, ids) plus the data language. No visitor data is sent. Terms and plans: https://developer.satelit.md/en/pricing/ · Documentation: https://developer.satelit.md/en/docs/

Film posters are loaded by the visitor's browser from `api.satelit.md`.

Data attribution is shown with the data as the API asks: for example BNM rates carry "Sursa: Banca Națională a Moldovei, bnm.md, CC BY 4.0", shop prices carry the shop name with each offer, and every block links to satelit.md.

== Installation ==

1. Upload `satelit-moldova-data.zip` in Plugins → Add New → Upload Plugin, then activate it.
2. Create an API key in the Satelit console: https://developer.satelit.md/en/console/
3. Go to Settings → Satelit, paste the key, save, and press "Test connection" (it shows your plan and the credits left).
4. Add a shortcode or a "Satelit" block to a page.

== Frequently Asked Questions ==

= How many credits does it use? =

Most routes cost 1-5 credits per answer. Cinema schedules and film cards cost 40. Every answer is cached (WordPress transients) for the minutes set in Settings → Satelit, and all visitors get the cached copy, so a page costs credits once per cache period. Defaults: rates 3 h, fuel 2 h, prices 3 h, news 15 min, cinema 2 h, film cards 24 h, Monitorul Oficial 6 h, joke 6 h.

= What happens when the API is down or the key is wrong? =

Pages show the last saved copy (kept up to a week) with a small "saved copy" mark, or nothing at all when there is none. Calls pause for 2 minutes after a network failure and for 5 minutes after a refused key, so a dead API never slows every page view. Administrators see a short line in place of the block and an admin notice. Visitors see no error.

= Is the key safe? =

It is stored as a WordPress option, never printed in a page, a form field or a script, and only sent to api.satelit.md.

== Screenshots ==

1. The shortcodes on a page (1280 px).
2. The same page on a phone (390 px).
3. The WooCommerce "Prices in other shops" tab.
4. Settings → Satelit.

== Changelog ==

= 1.0.0 =
* First release: 10 shortcodes and blocks, a classic widget, WooCommerce tab and price in other currencies, settings page with connection test and cache clearing, Romanian and Russian translations.
