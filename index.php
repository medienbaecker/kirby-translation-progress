<?php

use Kirby\Cms\App as Kirby;

require_once __DIR__ . '/classes/FieldAdapters.php';
require_once __DIR__ . '/classes/TranslationStatus.php';

Kirby::plugin('medienbaecker/translation-progress', [
	'options' => [
		'minValueLength'    => 50,
		'adapters'         => [],
		'ignoreFieldTypes' => [
			'files', 'pages', 'users',
			'link', 'color', 'date', 'time',
		],
		'languageVariables' => true,
	],
	'translations' => [
		'en' => [
			'translation-progress.title'        => 'Progress',
			'translation-progress.complete'      => 'Complete',
			'translation-progress.partial'       => 'Partial',
			'translation-progress.untranslated'  => 'Untranslated',
			'translation-progress.missing'       => 'Missing',
			'translation-progress.variables'     => 'Language Variables',
			'translation-progress.last-modified' => 'Last edit:',
		],
		'de' => [
			'translation-progress.title'        => 'Fortschritt',
			'translation-progress.complete'      => 'Vollständig',
			'translation-progress.partial'       => 'Teilweise',
			'translation-progress.untranslated'  => 'Nicht übersetzt',
			'translation-progress.missing'       => 'Fehlt',
			'translation-progress.variables'     => 'Sprachvariablen',
			'translation-progress.last-modified' => 'Letzte Änderung:',
		],
	],
	'api' => [
		'routes' => [
			[
				'pattern' => 'translation-progress/overview',
				'method'  => 'GET',
				'action'  => function () {
					FieldAdapters::registerDefaults();

					$custom = option('medienbaecker.translation-progress.adapters', []);
					foreach ($custom as $type => $adapter) {
						FieldAdapters::register($type, $adapter);
					}

					return TranslationStatus::overview();
				}
			],
		]
	],
]);
