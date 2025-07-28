<?php
$lvl = $attrs['level'] ?? 1;
$tag = 'h' . min(max($lvl, 1), 6); // Ensure level is between 1 and 6
?>
<<?= $tag ?>><?= $content ?></<?= $tag ?>>
