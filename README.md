# KonversAI Chat

Adds the KonversAI chat bubble widget to the site footer.

## Setup

1. Activate **KonversAI Chat** under Plugins.
2. Go to **Settings → KonversAI Chat**.
3. Enter the tenant key and save. The widget is only shown when a tenant key is set.

## Settings

- **Tenant key** – KonversAI tenant key (`data-tenant-key`).
- **Position** – bottom right (default) or bottom left.
- **Height** – distance of the closed bubble from the bottom: standard (20px), raised (about 60px, default) or high (about 100px). On phones the open chat is always full screen.

## API base URL

The script source is fixed in code (`https://app.prod.konversai.no`) and cannot be changed in the admin, so site admins cannot load arbitrary JavaScript. To change it, define `KONVERSAI_CHAT_API_BASE` in `wp-config.php`. Only https URLs are accepted; the widget is not shown if the value is invalid.

## Where the widget is hidden

- When no tenant key is set (always, cannot be overridden).
- On WooCommerce checkout, my account and endpoint pages, to keep third-party JavaScript off payment pages.
- For logged-in users who can `manage_options`, so a compromised widget script cannot act with an admin session.

## Filters

- `konversai_chat_enabled` – receives the default visibility and returns whether to show the widget. Return `true` to show it where it is hidden by default (e.g. for admins while testing), or `false` to hide it on specific pages. Has no effect without a tenant key.

## Documentation

KonversAI widget installation: https://docs.konvers.ai/start-here/install-the-widget/
