# Changelog

## 1.0.0 — 2026-11-19

- Ten shortcodes, each also a server-side rendered block: `satelit_rates`, `satelit_converter`, `satelit_fuel`, `satelit_prices`, `satelit_product`, `satelit_news`, `satelit_cinema`, `satelit_movie`, `satelit_law`, `satelit_joke`.
- Classic widget "Satelit — Moldova Data" (rates, converter, fuel, news, Monitorul Oficial, joke).
- WooCommerce: "Prices in other shops" product tab (search words or a Satelit item id per product) and an optional price line in other currencies at today's BNM rate.
- Settings → Satelit: API key, data language, cache minutes per product, "Test connection", "Clear cache".
- One API client: `wp_remote_get`, 8 s timeout, transients, a week-long saved copy served when the API fails, calls paused for 2 min after a network failure and 5 min after a refused key, 404 answers remembered for 10 min.
- Romanian and Russian translations.
