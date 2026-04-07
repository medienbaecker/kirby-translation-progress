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
			$ignoreTypes = option('medienbaecker.translation-status.ignoreFieldTypes', []);
			$fields = [];

			foreach ($form->fields() as $name => $field) {
				if (!$field->hasValue()) continue;

				$type = $field->type();
				if (in_array($type, $ignoreTypes)) continue;

				$fields[$name] = [
					'translate' => $field->translate(),
					'type'      => $type,
				];
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
		$minLength = option('medienbaecker.translation-status.minValueLength', 50);
		return mb_strlen($langText) < $minLength;
	}

	/**
	 * Returns null if the page has no translatable fields.
	 */
	protected static function pageStatus($page, string $defaultLang, array $secondaryLangs): ?array
	{
		$fields = self::getCachedFields($page);
		$defaultContent = self::readRaw($page, $defaultLang);

		$translatableKeys = [];
		foreach ($defaultContent as $key => $value) {
			if (self::isTranslatableField($key, $fields) && !self::isEmpty($value)) {
				$translatableKeys[] = $key;
			}
		}

		if (empty($translatableKeys)) {
			return null;
		}

		$langs = [];
		foreach ($secondaryLangs as $langCode) {
			$total = count($translatableKeys);

			if (!$page->translation($langCode)->exists()) {
				$langs[$langCode] = ['status' => 'missing', 'translated' => 0, 'total' => $total];
				continue;
			}

			$langContent = self::readRaw($page, $langCode);
			$translated = 0;
			foreach ($translatableKeys as $key) {
				$langVal = $langContent[$key] ?? '';
				if (self::isEmpty($langVal)) continue;

				$fieldType = $fields[$key]['type'] ?? 'text';
				if (self::isFieldTranslated($defaultContent[$key] ?? '', $langVal, $fieldType)) {
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
		if (option('medienbaecker.translation-status.languageVariables', true)) {
			$defaultTranslations = $kirby->defaultLanguage()->translations();
			$variablesNode = [
				'id'    => '_variables',
				'title' => t('translation-status.variables', 'Language Variables'),
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
