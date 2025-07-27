<?php

@include_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/Field.php';
require_once __DIR__ . '/lib/Validations.php';
require_once __DIR__ . '/lib/Api.php';

use Kirby\Cms\App as Kirby;
use Kirby\Toolkit\A;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Data\Json;
use Medienbaecker\Tiptap\Field;
use Medienbaecker\Tiptap\Validations;
use Medienbaecker\Tiptap\Api;

Kirby::plugin('medienbaecker/tiptap', [
	'options' => [
		'highlights' => [],
		'buttons' => []
	],
	'blueprints' => [
		'blocks/tiptap' => __DIR__ . '/blueprints/blocks/tiptap.yml',
	],
	'snippets' => [
		'blocks/tiptap' => __DIR__ . '/snippets/blocks/tiptap.php',
		// Nodes
		'tiptap/doc' => __DIR__ . '/snippets/tiptap/doc.php',
		'tiptap/heading' => __DIR__ . '/snippets/tiptap/heading.php',
		'tiptap/paragraph' => __DIR__ . '/snippets/tiptap/paragraph.php',
		'tiptap/bulletList' => __DIR__ . '/snippets/tiptap/bulletList.php',
		'tiptap/orderedList' => __DIR__ . '/snippets/tiptap/orderedList.php',
		'tiptap/listItem' => __DIR__ . '/snippets/tiptap/listItem.php',
		'tiptap/kirbyTag' => __DIR__ . '/snippets/tiptap/kirbyTag.php',
		'tiptap/text' => __DIR__ . '/snippets/tiptap/text.php',
		'tiptap/hardBreak' => __DIR__ . '/snippets/tiptap/hardBreak.php',
		// Marks
		'tiptap/bold' => __DIR__ . '/snippets/tiptap/bold.php',
		'tiptap/italic' => __DIR__ . '/snippets/tiptap/italic.php',
	],
	'fields' => [
		'tiptap' => [
			'mixins' => [
				'filepicker',
			],
			'props' => Field::props(),
			'validations' => Validations::rules(),
			'api' => function () {
				return Api::endpoints();
			}
		]
	],
	'fieldMethods' => [
		'tiptapText' => function ($field, array $options = []) {
			// Add custom buttons from plugin configuration
			if (!isset($options['customButtons'])) {
				$options['customButtons'] = option('medienbaecker.tiptap.buttons', []);
			}

			return convertTiptapToHtml(
				$field->value,
				$field->parent(),
				$options
			);
		}
	],
	'translations' => A::keyBy(
		A::map(
			Dir::files(__DIR__ . '/translations'),
			function ($file) {
				$translations = [];
				foreach (Json::read(__DIR__ . '/translations/' . $file) as $key => $value) {
					$translations["tiptap.{$key}"] = $value;
				}

				return A::merge(
					['lang' => F::name($file)],
					$translations
				);
			}
		),
		'lang'
	)
]);
