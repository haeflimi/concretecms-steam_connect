<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Compact columns: the default markup with tighter rows, the list flowing into up to three columns (top to bottom,
 * then the next column) as the width allows. Meant for longer lists, e.g. the most owned games.
 *
 * @var Concrete\Core\Block\View\BlockView $this
 */

// a template folder brings only its own view.css: the default styles are added by hand
$this->addHeaderItem('<link rel="stylesheet" href="' . h(REL_DIR_PACKAGES . '/steam_connect/blocks/steam_leaderboard/view.css') . '">');
$variant = 'columns';
include dirname(__DIR__, 2) . '/view.php';
