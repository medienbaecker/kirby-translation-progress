<?php

class TranslationStatus
{
	protected static array $fieldCache = [];

	protected static function readRaw($model, string $langCode): array
	{
		try {
			return $model->version('latest')->read($langCode) ?? [];
		} catch (\Throwable $e) {
			return [];
		}
	}

	protected static function isEmpty(mixed $value): bool
	{
		return $value === '' || $value === '[]' || $value === null;
	}

	protected static function getCachedFields($page): array
	{
		$template = $page->intendedTemplate()->name();
		if (!isset(self::$fieldCache[$template])) {
			$form = \Kirby\Form\Form::for($page);
			$ignoreTypes = option('medienbaecker.translation-progress.ignoreFieldTypes', []);
			$fields = [];

			foreach ($form->fields() as $name => $field) {
				if (!$field->hasValue()) continue;

				$type = $field->type();
				if (in_array($type, $ignoreTypes)) continue;

				$info = [
					'translate' => $field->translate(),
					'type'      => $type,
				];

				if ($type === 'object' || $type === 'structure') {
					$props = $field->toArray();
					if (!empty($props['fields'])) {
						$info['subfields'] = self::getSubfields($props['fields']);
					}
				}

				$fields[$name] = $info;
			}

			// Title is not a blueprint field but always translatable
			$fields['title'] = ['translate' => true, 'type' => 'text'];

			self::$fieldCache[$template] = $fields;
		}
		return self::$fieldCache[$template];
	}

	protected static function isTranslatableField(string $key, array $fields): bool
	{
		if ($key === 'uuid') return false;
		if (!isset($fields[$key])) return false;
		return $fields[$key]['translate'];
	}

	protected static function secondaryLanguageCodes(): array
	{
		$codes = [];
		foreach (kirby()->languages() as $lang) {
			if (!$lang->isDefault()) {
				$codes[] = $lang->code();
			}
		}
		return $codes;
	}

	protected static function determineStatus(int $translated, int $total): string
	{
		if ($total === 0) return 'complete';
		if ($translated === 0) return 'untranslated';
		if ($translated >= $total) return 'complete';
		return 'partial';
	}

	protected static function isFieldTranslated(string $defaultVal, string $langVal, string $fieldType = 'text'): bool
	{
		if (self::isEmpty($langVal)) return false;

		$defaultText = FieldAdapters::extractText($fieldType, $defaultVal);
		$langText    = FieldAdapters::extractText($fieldType, $langVal);

		if ($defaultText !== $langText) return true;

		// Short identical values are likely loan words or proper nouns
		$minLength = option('medienbaecker.translation-progress.minValueLength', 50);
		return mb_strlen($langText) < $minLength;
	}

	protected static function getSubfields(array $fieldDefs): array
	{
		$subfields = [];
		foreach ($fieldDefs as $name => $def) {
			$type = $def['type'] ?? 'text';
			if (!($def['saveable'] ?? true)) continue;

			$info = [
				'type'      => $type,
				'translate' => $def['translate'] ?? true,
			];

			if (($type === 'object' || $type === 'structure') && !empty($def['fields'])) {
				$info['subfields'] = self::getSubfields($def['fields']);
			}

			$subfields[$name] = $info;
		}
		return $subfields;
	}

	/**
	 * Expand an object or structure field into individual comparison units.
	 * Each unit is ['type' => fieldType, 'path' => [...], 'defaultVal' => string].
	 */
	protected static function expandCompound(array $basePath, string $rawValue, string $type, array $subfields, array $ignoreTypes): array
	{
		try {
			$data = \Kirby\Data\Data::decode($rawValue, 'yaml');
		} catch (\Throwable) {
			return [];
		}
		if (!is_array($data)) return [];

		return $type === 'structure'
			? self::expandFields($basePath, $data, $subfields, $ignoreTypes, true)
			: self::expandFields($basePath, [$data], $subfields, $ignoreTypes, false);
	}

	/**
	 * Expand rows of sub-fields into individual comparison units.
	 * For objects, $entries is [$data] (one row, no index in path).
	 * For structures, $entries is the rows array (row index in path).
	 */
	protected static function expandFields(array $basePath, array $entries, array $subfields, array $ignoreTypes, bool $indexed): array
	{
		$units = [];
		foreach ($entries as $i => $entry) {
			if (!is_array($entry)) continue;
			$rowPath = $indexed ? array_merge($basePath, [$i]) : $basePath;

			foreach ($subfields as $name => $info) {
				if (in_array($info['type'], $ignoreTypes)) continue;
				if (!($info['translate'] ?? true)) continue;

				$value = $entry[$name] ?? null;
				if ($value === null || $value === '' || (is_array($value) && empty($value))) continue;

				$path = array_merge($rowPath, [$name]);

				if (!empty($info['subfields']) && is_array($value)) {
					$nested = $info['type'] === 'structure'
						? self::expandFields($path, $value, $info['subfields'], $ignoreTypes, true)
						: self::expandFields($path, [$value], $info['subfields'], $ignoreTypes, false);
					array_push($units, ...$nested);
				} else {
					$units[] = ['type' => $info['type'], 'path' => $path, 'defaultVal' => (string)$value];
				}
			}
		}
		return $units;
	}

	/**
	 * Resolve a sub-field value by traversing a path through content.
	 * First segment is the raw content key, remaining segments navigate decoded YAML.
	 */
	protected static function resolveValue(array $path, array $content): string
	{
		$raw = $content[$path[0]] ?? '';
		if (count($path) === 1) return is_string($raw) ? $raw : '';

		try {
			$current = \Kirby\Data\Data::decode((string)$raw, 'yaml');
		} catch (\Throwable) {
			return '';
		}

		for ($i = 1; $i < count($path); $i++) {
			if (!is_array($current) || !array_key_exists($path[$i], $current)) return '';
			$current = $current[$path[$i]];
		}

		return is_string($current) ? $current : '';
	}

	/**
	 * Returns null if the page has no translatable fields.
	 */
	protected static function pageStatus($page, string $defaultLang, array $secondaryLangs): ?array
	{
		$fields = self::getCachedFields($page);
		$defaultContent = self::readRaw($page, $defaultLang);
		$ignoreTypes = option('medienbaecker.translation-progress.ignoreFieldTypes', []);

		// Build comparison units — compound fields (object/structure) are
		// expanded into one unit per translatable sub-field (per row for structures).
		$units = [];
		foreach ($defaultContent as $key => $value) {
			if (!self::isTranslatableField($key, $fields)) continue;
			if (self::isEmpty($value)) continue;

			$fieldInfo = $fields[$key];
			$type = $fieldInfo['type'];

			if (($type === 'object' || $type === 'structure') && !empty($fieldInfo['subfields'])) {
				array_push($units, ...self::expandCompound([$key], $value, $type, $fieldInfo['subfields'], $ignoreTypes));
			} else {
				$units[] = ['type' => $type, 'path' => [$key], 'defaultVal' => $value];
			}
		}

		if (empty($units)) return null;

		$langs = [];
		foreach ($secondaryLangs as $langCode) {
			$total = count($units);

			if (!$page->translation($langCode)->exists()) {
				$langs[$langCode] = ['status' => 'missing', 'translated' => 0, 'total' => $total];
				continue;
			}

			$langContent = self::readRaw($page, $langCode);
			$translated = 0;
			foreach ($units as $unit) {
				$langVal = self::resolveValue($unit['path'], $langContent);
				if (self::isEmpty($langVal)) continue;
				if (self::isFieldTranslated($unit['defaultVal'], $langVal, $unit['type'])) {
					$translated++;
				}
			}

			$langs[$langCode] = [
				'status'     => self::determineStatus($translated, $total),
				'translated' => $translated,
				'total'      => $total,
			];
		}

		return $langs;
	}

	protected static function buildTree($parent, string $defaultLang, array $secondaryLangs, array &$lastModified = []): array
	{
		$nodes = [];

		$listed = $parent->children()->listed()->sortBy('num', 'asc');
		$rest = $parent->children()->unlisted()->add($parent->drafts());
		foreach ($listed->add($rest) as $page) {
			$langs = self::pageStatus($page, $defaultLang, $secondaryLangs);
			$children = self::buildTree($page, $defaultLang, $secondaryLangs, $lastModified);

			foreach ($secondaryLangs as $langCode) {
				if ($page->translation($langCode)->exists()) {
					try {
						$modified = $page->version('latest')->modified($langCode);
						if ($modified && (!isset($lastModified[$langCode]) || $modified > $lastModified[$langCode])) {
							$lastModified[$langCode] = $modified;
						}
					} catch (\Throwable $e) {}
				}
			}

			// Include node if it has translatable fields OR children with translatable fields
			if ($langs === null && empty($children)) {
				continue;
			}

			$node = [
				'id'     => $page->id(),
				'title'  => $page->content($defaultLang)->get('title')->value(),
				'link'   => $page->panel()->path(),
				'status' => $page->status(),
			];

			if ($langs !== null) {
				$node['langs'] = $langs;
			}

			if (!empty($children)) {
				$node['children'] = $children;
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	public static function overview(): array
	{
		$kirby = kirby();
		$defaultLang = $kirby->defaultLanguage()->code();
		$secondaryLangs = self::secondaryLanguageCodes();

		$languages = [];
		foreach ($kirby->languages() as $lang) {
			if (!$lang->isDefault()) {
				$languages[] = ['code' => $lang->code(), 'name' => $lang->name()];
			}
		}

		$lastModified = [];
		$tree = self::buildTree($kirby->site(), $defaultLang, $secondaryLangs, $lastModified);

		// Language variables node
		if (option('medienbaecker.translation-progress.languageVariables', true)) {
			$defaultTranslations = $kirby->defaultLanguage()->translations();
			$variablesNode = [
				'id'    => '_variables',
				'title' => t('translation-progress.variables', 'Language Variables'),
				'icon'  => 'translate',
				'link'  => 'languages/' . $defaultLang,
				'langs' => [],
			];

			foreach ($secondaryLangs as $langCode) {
				$langTranslations = $kirby->language($langCode)->translations();
				$total = count($defaultTranslations);
				$translated = 0;
				foreach ($defaultTranslations as $key => $defaultValue) {
					$langValue = $langTranslations[$key] ?? '';
					if ($langValue !== '' && $langValue !== $defaultValue) {
						$translated++;
					}
				}
				$variablesNode['langs'][$langCode] = [
					'status'     => self::determineStatus($translated, $total),
					'translated' => $translated,
					'total'      => $total,
				];
			}

			array_unshift($tree, $variablesNode);
		}

		// Totals (page tree only, excluding variables node)
		$totals = [];
		foreach ($secondaryLangs as $langCode) {
			$totals[$langCode] = ['translated' => 0, 'total' => 0];
		}
		$pageTree = array_filter($tree, fn($n) => ($n['id'] ?? '') !== '_variables');
		self::sumTree($pageTree, $totals);

		$lastModifiedFormatted = [];
		foreach ($secondaryLangs as $langCode) {
			$lastModifiedFormatted[$langCode] = isset($lastModified[$langCode])
				? date('Y-m-d', $lastModified[$langCode])
				: null;
		}

		return [
			'tree'            => $tree,
			'languages'       => $languages,
			'defaultLanguage' => $defaultLang,
			'totals'          => $totals,
			'lastModified'    => $lastModifiedFormatted,
		];
	}

	protected static function sumTree(array $nodes, array &$totals): void
	{
		foreach ($nodes as $node) {
			if (isset($node['langs'])) {
				foreach ($node['langs'] as $langCode => $info) {
					if (isset($totals[$langCode])) {
						$totals[$langCode]['translated'] += $info['translated'] ?? 0;
						$totals[$langCode]['total'] += $info['total'] ?? 0;
					}
				}
			}
			if (!empty($node['children'])) {
				self::sumTree($node['children'], $totals);
			}
		}
	}
}
