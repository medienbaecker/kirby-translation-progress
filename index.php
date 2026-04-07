<?php

use Kirby\Cms\App as Kirby;

require_once __DIR__ . '/classes/FieldAdapters.php';
require_once __DIR__ . '/classes/TranslationStatus.php';

Kirby::plugin('medienbaecker/translation-status', [
	'options' => [
		'minValueLength'    => 50,
		'adapters'         => [],
		'ignoreFieldTypes' => [
			'files', 'pages', 'users',
		],
		'languageVariables' => true,
	],
	'translations' => [
		'en' => [
			'translation-status.title'        => 'Translation Status',
			'translation-status.complete'      => 'Complete',
			'translation-status.partial'       => 'Partial',
			'translation-status.untranslated'  => 'Untranslated',
			'translation-status.missing'       => 'Missing',
			'translation-status.variables'     => 'Language Variables',
			'translation-status.last-modified' => 'Last edit:',
		],
		'de' => [
			'translation-status.title'        => 'Übersetzungsstatus',
			'translation-status.complete'      => 'Vollständig',
			'translation-status.partial'       => 'Teilweise',
			'translation-status.untranslated'  => 'Nicht übersetzt',
			'translation-status.missing'       => 'Fehlt',
			'translation-status.variables'     => 'Sprachvariablen',
			'translation-status.last-modified' => 'Letzte Änderung:',
		],
	],
	'api' => [
		'routes' => [
			[
				'pattern' => 'translation-status/overview',
				'method'  => 'GET',
				'action'  => function () {
					FieldAdapters::registerDefaults();

					$custom = option('medienbaecker.translation-status.adapters', []);
					foreach ($custom as $type => $adapter) {
						FieldAdapters::register($type, $adapter);
					}

					return TranslationStatus::overview();
				}
			],
		]
	],
]);
