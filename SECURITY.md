# Security

Please report security issues privately to **contact@satelit.md** (not in a public issue). Include the plugin version, WordPress and PHP versions, and steps to reproduce. We answer as soon as we can and credit reporters who want it.

How the plugin handles your key:

- The API key is stored as the WordPress option `satelit_md_api_key` (not autoloaded). It is never printed in HTML, form fields, scripts or logs, and is sent only to `api.satelit.md` in the `Authorization` header from your server.
- Admin actions (saving settings, "Test connection", "Clear cache") check the `manage_options` capability and a nonce.
- Every value from the API is escaped before output.
