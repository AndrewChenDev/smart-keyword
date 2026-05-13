# Changelog

All notable changes to this project will be documented in this file.

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
