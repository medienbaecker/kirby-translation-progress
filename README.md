# Kirby Translation Progress

Adds a translation overview to the Panel's Languages view: a percentage per language and a page tree showing the translation progress for every page.

![The Languages view with a Translation Progress section showing per-language percentages and a page tree with translation progress per page and language](.github/screenshot.png)

## Installation

### Composer

```bash
composer require medienbaecker/kirby-translation-progress
```

### Manual

Download and extract to `site/plugins/kirby-translation-progress`.

## Requirements

- Kirby 5+
- PHP 8.2+
- Multi-language setup

## How it works

The plugin reads Kirby's content files, compares them field by field against the default language, and reports a percentage.

A field is considered **translated** when its content differs from the default language. A field that's **empty** in the translation counts as untranslated. A field that's **identical** to the default language is where it gets tricky:

### Identical content

When a field has the same value in both languages, is it translated or not? "API" in English is "API" in German and that's fine. But a full paragraph that's identical in both languages probably hasn't been translated yet.

The plugin uses a length heuristic: identical values shorter than `minValueLength` (default: 50 characters) are assumed to be loan words or proper nouns. Longer identical values are flagged as untranslated.

### Field types

The plugin reads the blueprint to find translatable fields. Fields with `translate: false` are excluded, and so are non-text types (`files`, `pages`, `users`, `link`, `color`, `date`, `time`) that don't contain translatable content.

For complex fields, the plugin extracts text before comparing:

| Field type         | What gets compared                    |
| ------------------ | ------------------------------------- |
| `text`, `textarea` | The raw value                         |
| `writer`, `list`   | HTML with tags stripped               |
| `blocks`           | Text from each block's content fields |
| `layout`           | Text from blocks inside each column   |
| `structure`        | Each sub-field per row, individually  |
| `object`           | Each sub-field individually           |
| `tiptap`           | Text nodes from ProseMirror JSON      |

### Language variables

The `translations` array from your language files is also compared, shown as a separate row. Disable it with `languageVariables: false`.

## Options

```php
'medienbaecker.translation-progress' => [
    'minValueLength'    => 50,
    'languageVariables' => true,
    'ignoreFieldTypes'  => ['files', 'pages', 'users', 'link', 'color', 'date', 'time'],
    'adapters'          => [],
],
```

Important: use Kirby's built-in `translate: false` option in your blueprints to exclude specific fields:

```yaml
fields:
  category:
    type: select
    translate: false
```

## Custom adapters

For third-party field types that store text in a custom format, register an adapter that returns plain text:

```php
'medienbaecker.translation-progress' => [
    'adapters' => [
        'my-field' => function (string $value): string {
            $data = json_decode($value, true);
            return strip_tags($data['html'] ?? '');
        },
    ],
],
```

Built-in adapters cover `writer`, `list`, `blocks`, `layout`, `structure`, `object`, and my `tiptap` plugin. A custom adapter with the same name overrides the built-in one.

## Limitations

- The plugin can't know if identical content was intentional. The `minValueLength` threshold is a best guess.
- Blocks and layouts count as one field. The plugin doesn't track individual blocks across languages since they can be reordered, added, or removed independently.
- Object and structure fields are expanded into their sub-fields using the blueprint. Non-translatable sub-field types (like `link`) are skipped, and each translatable sub-field counts individually. Nested compounds (e.g. a structure inside an object) are expanded recursively.

## License

MIT
