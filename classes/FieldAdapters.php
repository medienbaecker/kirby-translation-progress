<?php

class FieldAdapters
{
	protected static array $adapters = [];
	protected static bool $defaultsRegistered = false;

	public static function register(string $type, callable $adapter): void
	{
		static::$adapters[$type] = $adapter;
	}

	public static function extractText(string $type, string $value): string
	{
		if (isset(static::$adapters[$type])) {
			return (static::$adapters[$type])($value);
		}
		return $value;
	}

	public static function registerDefaults(): void
	{
		if (static::$defaultsRegistered) {
			return;
		}
		static::$defaultsRegistered = true;

		// Writer / List — stored as HTML
		static::register('writer', fn(string $v) => strip_tags($v));
		static::register('list', fn(string $v) => strip_tags($v));

		// Blocks — JSON: [{id, type, isHidden, content: {...}}]
		static::register('blocks', function (string $v): string {
			$blocks = json_decode($v, true);
			if (!is_array($blocks)) return $v;

			$text = '';
			foreach ($blocks as $block) {
				foreach (($block['content'] ?? []) as $key => $val) {
					if (is_string($val) && $key !== 'id') {
						$text .= strip_tags($val) . ' ';
					}
				}
			}
			return trim($text);
		});

		// Layout — JSON: [{id, attrs, columns: [{id, width, blocks: [...]}]}]
		static::register('layout', function (string $v): string {
			$layouts = json_decode($v, true);
			if (!is_array($layouts)) return $v;

			$text = '';
			foreach ($layouts as $layout) {
				foreach ($layout['columns'] ?? [] as $col) {
					foreach ($col['blocks'] ?? [] as $block) {
						foreach (($block['content'] ?? []) as $key => $val) {
							if (is_string($val) && $key !== 'id') {
								$text .= strip_tags($val) . ' ';
							}
						}
					}
				}
			}
			return trim($text);
		});

		// Structure — YAML: array of row objects
		static::register('structure', function (string $v): string {
			try {
				$rows = \Kirby\Data\Data::decode($v, 'yaml');
			} catch (\Throwable) {
				return $v;
			}
			if (!is_array($rows)) return $v;

			$text = '';
			foreach ($rows as $row) {
				if (!is_array($row)) continue;
				foreach ($row as $val) {
					if (is_string($val)) $text .= strip_tags($val) . ' ';
				}
			}
			return trim($text);
		});

		// Object — YAML: flat key/value map
		static::register('object', function (string $v): string {
			try {
				$data = \Kirby\Data\Data::decode($v, 'yaml');
			} catch (\Throwable) {
				return $v;
			}
			if (!is_array($data)) return $v;

			$text = '';
			foreach ($data as $val) {
				if (is_string($val)) $text .= strip_tags($val) . ' ';
			}
			return trim($text);
		});

		// Tiptap — ProseMirror JSON
		static::register('tiptap', function (string $v): string {
			$doc = json_decode($v, true);
			if (!is_array($doc)) return $v;
			return FieldAdapters::extractTiptapText($doc);
		});
	}

	public static function extractTiptapText(array $node): string
	{
		$text = '';
		if (isset($node['text']) && is_string($node['text'])) {
			$text .= $node['text'] . ' ';
		}
		foreach ($node['content'] ?? [] as $child) {
			$text .= static::extractTiptapText($child);
		}
		return $text;
	}

	/** @internal */
	public static function reset(): void
	{
		static::$adapters = [];
		static::$defaultsRegistered = false;
	}
}
