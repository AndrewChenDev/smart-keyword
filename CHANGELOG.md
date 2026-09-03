# Changelog

All notable changes to this project will be documented in this file.

## [1.1.3] - 2026-09-03

### Security
- API keys are no longer rendered into settings-page HTML; blank inputs preserve existing credentials and saved keys can be explicitly removed.
- Added `SKAI_OPENAI_API_KEY`, `SKAI_ANTHROPIC_API_KEY`, `SKAI_GEMINI_API_KEY`, and `SKAI_DEEPSEEK_API_KEY` support for keeping provider keys in `wp-config.php` and out of the WordPress database.
- Gemini authentication now uses the `x-goog-api-key` request header instead of putting credentials in request URLs.
- Model-cache credential fingerprints now use a WordPress-salted HMAC instead of an unsalted MD5 hash.

## [1.1.2] - 2026-07-30

### Fixed
- **Critical:** OpenAI tag generation was failing outright (`HTTP 400: 'temperature' does not support 0.3 with this model`) for every reasoning/GPT-5-family model (o1, o3, o4-mini, gpt-5, gpt-5.x, etc.), since those models reject any non-default `temperature`. `Skai_OpenAI::request()` now retries once without `temperature` when this specific error is returned, so generation works regardless of which OpenAI model is selected.

## [1.1.1] - 2026-07-30

### Fixed
- Model pricing now shows the real, non-promotional price everywhere, including the model dropdown after clicking **Refresh from API**. OpenRouter's bulk catalog silently returns whatever promotional discount is currently active (e.g. `gpt-5.6-luna` showed $0.60/M output instead of the real $1.20/M); `Skai_Pricing::get_for()` backs out the discount via OpenRouter's per-model endpoints resource. This correction previously applied only to the initial page load and the "Price:" line — the AJAX payload behind "Refresh from API" was left uncorrected for performance reasons and is now fixed to match.
- Claude pricing lookups failing outright due to a dash/dot id mismatch between the plugin's default model id and OpenRouter's catalog id.

### Changed
- **Max characters sent to AI** now accepts `0` to mean "no limit" (send the full article). Previously the value was always clamped to 500-20000, forcing truncation even when the user wanted the entire post sent.

## [1.1.0] - 2026-05-14

### Added
- **Custom instruction** textarea on the settings page. The text is injected into the AI prompt after the existing rules but before the JSON-output rule, so the output contract is preserved regardless of what the user writes.
- **Tabbed settings page** with three tabs: **General** (provider, custom instruction, tag count, max content chars), **API & Models** (API key + model selector per provider), and **Usage**.
- **Dynamic model dropdown** for every provider, populated from the vendor's list-models endpoint (OpenAI `/v1/models`, Anthropic `/v1/models`, Gemini `models.list`, DeepSeek `/models`) via a "Refresh from API" button. Results are cached as a 1-hour transient per API key. A **Custom…** option still allows typing any model ID the dropdown doesn't include.
- **Token usage tracking** per provider, bucketed by month (`YYYY-MM`). Automatic monthly reset (no cron — each new month gets a fresh bucket). Up to 12 months of history retained, viewable under "Previous months". **Reset current month** and **Reset all history** buttons available on the Usage tab.
- **Model pricing display** sourced from the OpenRouter catalog (cached 24h). Each model dropdown option shows `$X/M in | $Y/M out`; a "Price:" line below the dropdown reflects the currently selected model; the Usage tab gains an **Est. cost (USD)** column plus an **Estimated total** row computed from stored token counts × the currently configured model's price.

### Changed
- Settings page now enqueues its own dedicated JS/CSS on the plugin's settings screen only.
- Provider `request()` method may now return either a plain string (legacy) or `['text' => …, 'usage' => ['input' => …, 'output' => …]]`. The base class accepts both, so any custom providers continue to work unmodified.
- Model-list transients are invalidated when a provider's API key changes.
- Token usage is now recorded per **(provider, model)** pair, not just per provider. Each provider row on the Usage tab gains a chevron toggle that expands to a per-model breakdown showing only the models actually used that month. Providers with no recorded usage are hidden. Est. cost is now computed per-model (using the exact model each request hit), so switching models mid-month no longer skews the estimate.
- Updated Traditional Chinese (`zh_TW`) and Simplified Chinese (`zh_CN`) translations to cover all new strings (custom instruction, tab labels, model dropdown, Usage tab).

## [1.0.1] - 2026-05-13

### Fixed
- Generated tags no longer contain trailing punctuation such as `?`, `!`, `。`, `？`, `！`, emojis, or other symbols (e.g. `母亲?` → `母亲`). Leading/trailing whitespace, Unicode punctuation, and symbols are now stripped from every tag.

### Changed
- Prompt now explicitly instructs the AI to return plain noun phrases with no punctuation, hashtags, or rhetorical questions.

## [1.0.0]

### Added
- Initial release.
- "Pick from AI tags" link inside the post editor's Tags metabox.
- Support for OpenAI, Anthropic, Gemini, and DeepSeek providers.
- Settings page under **Settings → Smart Keyword AI** (provider, API key, model, tags-per-generation, max content length).
- Traditional Chinese (`zh_TW`) and Simplified Chinese (`zh_CN`) translations.
