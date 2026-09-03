## What this is

**Smart Keyword AI** is a WordPress plugin that adds an "Pick from AI tags" link inside the Tags metabox on the post editor. Clicking it calls an AI provider to generate SEO tag keywords from the post content and inserts them into WordPress's built-in tag input.

## Install via WP-CLI

Installs whatever the current latest GitHub release is — this command never needs to change between releases:

```sh
wp plugin install https://github.com/AndrewChenDev/smart-keyword/releases/latest/download/smart-keyword-ai.zip --activate
```

To upgrade to the newest release later:

```sh
wp plugin install https://github.com/AndrewChenDev/smart-keyword/releases/latest/download/smart-keyword-ai.zip --force --activate
```

## Development setup

This is a plain PHP WordPress plugin — no build step, no `package.json`, no Composer. Drop the folder into `wp-content/plugins/` of a local WordPress install and activate it. There is no test suite.

To work on it with a local WordPress:
- Use a tool like [LocalWP](https://localwp.com/), MAMP, or `wp-env` to run WordPress locally.
- Symlink or copy this folder into `wp-content/plugins/smart-keyword-ai/`.
- Activate the plugin in WP Admin → Plugins.
- Set an API key in WP Admin → Settings → Smart Keyword AI.

## Secure API key setup

Keep provider API keys out of the WordPress database by defining them in
`wp-config.php`. Add only the providers you use above the
`/* That's all, stop editing! */` line:

```php
define( 'SKAI_OPENAI_API_KEY', 'replace-with-your-new-project-key' );
define( 'SKAI_ANTHROPIC_API_KEY', 'replace-with-your-new-key' );
define( 'SKAI_GEMINI_API_KEY', 'replace-with-your-new-key' );
define( 'SKAI_DEEPSEEK_API_KEY', 'replace-with-your-new-key' );
```

Each non-empty constant takes precedence over that provider's settings-page
value. Existing OpenAI-only configurations continue working unchanged. After
adding a constant, open **Settings → Smart Keyword AI** and save once; this
removes any old database copy for that provider. The settings page never
displays saved API keys in its HTML.

Keep `wp-config.php` out of version control. Use project-scoped or restricted
keys with only the permissions this site needs, and configure budget alerts and
usage limits where available. If a key may have leaked, revoke it immediately,
review recent usage, create a replacement, and update the constant.

## Architecture

The request lifecycle for tag generation:

1. **Browser** — `assets/admin.js` reads post content from TinyMCE or the `#content` textarea, then POSTs to `admin-ajax.php` with action `skai_generate_tags`.
2. **`Skai_Ajax::handle()`** (`includes/class-skai-ajax.php`) — verifies nonce + capability, calls `Skai_Content::prepare()` to strip HTML/shortcodes/media and truncate, reads provider settings, instantiates the correct provider, calls `generate_tags()`, and returns JSON.
3. **`Skai_Provider`** (`includes/providers/class-skai-provider.php`) — abstract base class. `generate_tags()` builds the prompt, calls the abstract `request()` method, then parses the result via `parse_tags()` (tries strict JSON → embedded JSON array → newline/comma fallback). Deduplication and slicing to `$count` happen here.
4. **Provider subclasses** (`includes/providers/class-skai-*.php`) — each implements only `request()`, which makes the vendor-specific HTTP call via `wp_remote_post()` and returns the raw text content string (not the full JSON envelope).

## Key classes

| Class | File | Role |
|---|---|---|
| `Skai_Settings` | `includes/class-skai-settings.php` | WP options page; stores config under `skai_options` and resolves provider keys from `wp-config.php` when configured |
| `Skai_Content` | `includes/class-skai-content.php` | Static `prepare()` strips shortcodes, HTML, image URLs, then truncates to `max_content_chars` |
| `Skai_Provider` | `includes/providers/class-skai-provider.php` | Abstract base; owns prompt construction, response parsing, deduplication |
| `Skai_Ajax` | `includes/class-skai-ajax.php` | Single AJAX handler; instantiates the active provider via `make_provider()` |
| `Skai_Metabox` | `includes/class-skai-metabox.php` | Enqueues assets and injects the "Pick from AI tags" link into the Tags metabox via `admin_footer` |

## Adding a new AI provider

1. Create `includes/providers/class-skai-{slug}.php` extending `Skai_Provider`.
2. Implement `request( $prompt )` — return the plain-text string from the model, or a `WP_Error`.
3. `require_once` it in `smart-keyword-ai.php`.
4. Add the slug to `Skai_Settings::defaults()` (with `{slug}_key` and `{slug}_model`), the `$valid_p` array in `sanitize()`, the provider dropdown in `field_provider()`, and the `make_provider()` switch in `Skai_Ajax`.

## Internationalization

All user-facing strings use `__()` / `esc_html_e()` with the `smart-keyword-ai` text domain. Translation files are in `languages/`. The `.pot` template is `languages/smart-keyword-ai.pot`.
