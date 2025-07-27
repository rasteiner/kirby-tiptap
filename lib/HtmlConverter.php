<?php

namespace Medienbaecker\Tiptap;

use Tiptap\Editor;
use Medienbaecker\Tiptap\Nodes\KirbyTagNode;
use Medienbaecker\Tiptap\Nodes\ConditionalTextNode;
use Medienbaecker\Tiptap\Extensions\CustomAttributes;

/**
 * Converts Tiptap JSON content to HTML
 * Main entry point for Tiptap to HTML conversion with KirbyTag processing
 */
class HtmlConverter
{
	protected static function snippet(array $content, ?array $parent = null): string {
		$str = '';
		$previous = null;

		for ($i = 0, $l = count($content); $i < $l; $i++) {
			$block = $content[$i];

			$children = self::snippet($block['content'] ?? [], $block);

			$str .= snippet("tiptap/{$block['type']}", [
				...$block,
				'content' => $children,
				'next' => $content[$i + 1] ?? null,
				'previous' => $previous,
				'parent' => $parent,
			], return: true);

			$previous = $block;
		}

		return $str;
	}

	/**
	 * Convert Tiptap JSON to HTML
	 * @param mixed $json Tiptap JSON content
	 * @param object $parent Parent page/model for KirbyTag context
	 * @param array $options Conversion options
	 * @return string Generated HTML
	 */
	public static function convert($json, $parent, array $options = [])
	{
		// Set default options
		$options = array_merge([
			'offsetHeadings' => 0,
			'allowHtml' => false,
			'customButtons' => []
		], $options);

		// Handle invalid input
		if ($json === null || $json === '') {
			return '';
		}

		// Parse JSON if needed
		if (is_string($json)) {
			$decoded = json_decode($json, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				return ''; // Invalid JSON
			}
			$json = $decoded;
		}

		// Validate JSON structure
		if (!ContentProcessor::validateJsonStructure($json)) {
			return '';
		}

		// Check if inline mode is active
		$isInline = $json['inline'] ?? false;

		// Clean list items to remove unnecessary paragraph wrappers
		$json = ContentProcessor::cleanListItemContent($json);

		// Handle inline mode by flattening paragraphs
		if ($isInline) {
			$json = ContentProcessor::processInlineMode($json);
		}

		// Apply heading offset
		$json['content'] = ContentProcessor::applyHeadingOffset(
			$json['content'],
			$options['offsetHeadings']
		);

		// Process nodes for KirbyTags and UUIDs
		foreach ($json['content'] as &$node) {
			if (!is_array($node)) {
				continue;
			}

			KirbyTagProcessor::processContent($node, $parent, $options['allowHtml']);
		}

		// Convert to HTML
		try {

			$dom = (new MarkProcessor)->processNode($json);

			/*
			// the dirtiest debugging ever:
			echo "<pre>" . html(json_encode($dom, JSON_PRETTY_PRINT)) . "</pre>";
			*/

			$html = self::snippet([$dom]);

			/*
			$extensions = [
				new \Tiptap\Extensions\StarterKit([
					'text' => false, // Disable default text node
				]),
				new ConditionalTextNode($options['allowHtml']), // Use our custom text handler
				new KirbyTagNode()
			];

			$extensions[] = new CustomAttributes([
				'customButtons' => $options['customButtons']
			]);

			$html = (new Editor([
				'extensions' => $extensions
			]))->setContent($json)->getHTML();

			*/
			// Handle Smartypants
			if (option('smartypants', false) !== false) {
				$html = smartypants($html);
			}

			return $html;
		} catch (\Exception $err) {
			/*
			// More debugging
			if (option('debug', false)) {
				return 'Tiptap conversion error: ' . $err->getMessage() . ' in ' . $err->getFile() . ':' . $err->getLine();
			}
			*/
			return '';
		}
	}
}
