<?php
namespace Medienbaecker\Tiptap;

/**
 * Helps converting marks into a hierarchical structure so that thy can be mapped into HTML directly.
 */
class MarkProcessor
{
	/**
	 * Recursively processes a single node in the document tree.
	 * If the node has a 'content' array, it delegates the transformation
	 * of that array to processContentArray.
	 *
	 * @param array $node The node to process.
	 * @return array The processed node.
	 */
	public function processNode(array $node): array
	{
		if (isset($node['content']) && is_array($node['content'])) {
			$node['content'] = $this->processContentArray($node['content']);
		}
		return $node;
	}

	/**
	 * This is the core logic. It transforms a flat array of content nodes
	 * into a hierarchical tree based on the 'marks' property of each node.
	 * It uses a stack-based approach to manage the opening and closing of mark nodes.
	 *
	 * @param array $content The flat content array to process.
	 * @return array The new, nested content array.
	 */
	private function processContentArray(array $content): array
	{
		$resultTree = [];
		$openMarks = []; // A stack of the currently open mark definitions (e.g., ['type' => 'bold']).

		// A stack of references to the 'content' arrays of the nested nodes in the result tree.
		// This lets us know where to insert the next node. It starts with a reference
		// to the root of our new tree ($resultTree).
		$nodesStack = [&$resultTree];

		foreach ($content as $child) {
			// If we encounter a "block" node (anything that isn't simple text or a line break),
			// we must close all currently open inline marks before processing it.
			if ($this->isBlockNode($child)) {
				$this->closeAllMarks($openMarks, $nodesStack);
				// Recursively process the block node and add it to the root of the tree.
				$resultTree[] = $this->processNode($child);
				continue;
			}

			$childMarks = $child['marks'] ?? [];

			// 1. Find how deep the marks of the current child match the already open marks.
			$commonDepth = $this->getCommonMarkDepth($openMarks, $childMarks);

			// 2. Close any marks that are no longer active by popping them from our stacks.
			$this->closeMarksToDepth($commonDepth, $openMarks, $nodesStack);

			// 3. Open any new marks required by the current child.
			$this->openNewMarks($commonDepth, $childMarks, $openMarks, $nodesStack);

			// 4. Add the actual content (e.g., text or a hardBreak) to the deepest open node.
			$this->addContentToTree($child, $nodesStack);
		}

		// After processing all children, close any remaining open marks.
		$this->closeAllMarks($openMarks, $nodesStack);

		return $resultTree;
	}

	/**
	 * Checks if a node is a block-level element which should not be contained
	 * within an inline mark.
	 */
	private function isBlockNode(array $node): bool
	{
		// Anything that isn't a text node or a hard break is considered a block node.
		return match ($node['type'] ?? '') {
			'text', 'hardBreak' => false,
			default => true,
		};
	}

	/**
	 * Compares the stack of open marks with a new set of marks to find
	 * how many levels they share from the top.
	 */
	private function getCommonMarkDepth(array $openMarks, array $childMarks): int
	{
		$depth = 0;
		while (
			isset($openMarks[$depth]) &&
			isset($childMarks[$depth]) &&
			$openMarks[$depth] == $childMarks[$depth] // Simple array comparison works here
		) {
			$depth++;
		}
		return $depth;
	}

	/**
	 * Closes all currently open marks.
	 */
	private function closeAllMarks(array &$openMarks, array &$nodesStack): void
	{
		$this->closeMarksToDepth(0, $openMarks, $nodesStack);
	}

	/**
	 * Closes marks until the $openMarks stack is at the target depth.
	 */
	private function closeMarksToDepth(int $targetDepth, array &$openMarks, array &$nodesStack): void
	{
		while (count($openMarks) > $targetDepth) {
			array_pop($openMarks);
			array_pop($nodesStack);
		}
	}

	/**
	 * Opens new mark nodes for all marks in $childMarks past the common depth.
	 */
	private function openNewMarks(int $commonDepth, array $childMarks, array &$openMarks, array &$nodesStack): void
	{
		for ($i = $commonDepth; $i < count($childMarks); $i++) {
			$mark = $childMarks[$i];
			$newNode = $mark; // The mark itself becomes the new node (e.g., {'type': 'bold'})
			$newNode['content'] = [];

			// Get a reference to the 'content' array at the top of the stack.
			$stackTopKey = array_key_last($nodesStack);
			$currentContent = &$nodesStack[$stackTopKey];

			// Add the new mark node.
			$currentContent[] = $newNode;

			// Update our stacks to go one level deeper into the new node.
			$openMarks[] = $mark;

			// Add a reference to the new node's 'content' array to the stack.
			$newNodeKey = array_key_last($currentContent);
			$nodesStack[] = &$currentContent[$newNodeKey]['content'];
		}
	}

	/**
	 * Adds the final content node (text or other inline element) into the tree
	 * at the current position defined by the $nodesStack.
	 * It also handles merging consecutive text nodes.
	 */
	private function addContentToTree(array $child, array &$nodesStack): void
	{
		// Get a reference to the deepest 'content' array using its key.
		$stackTopKey = array_key_last($nodesStack);
		$currentContent = &$nodesStack[$stackTopKey];

		if ($child['type'] === 'text') {
			// Attempt to merge with the previous node if it was also a text node.
			$lastNodeKey = array_key_last($currentContent);
			// Check if the last node exists and is a text node
			if ($lastNodeKey !== null && isset($currentContent[$lastNodeKey]['type']) && $currentContent[$lastNodeKey]['type'] === 'text') {
				// Modify the last node in place by appending the new text.
				$currentContent[$lastNodeKey]['text'] .= $child['text'];
			} else {
				// Otherwise, add a new text node.
				$currentContent[] = ['type' => 'text', 'text' => $child['text']];
			}
		} else { // Handle other inline nodes like 'hardBreak'.
			$nodeToAdd = $child;
			unset($nodeToAdd['marks']); // The hierarchy now represents the marks.
			$currentContent[] = $nodeToAdd;
		}
	}
}
