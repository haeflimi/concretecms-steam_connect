<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $heading
 * @var string $subtitle
 * @var array[] $rows rank, name, url, image, value, valueLabel, meta
 */

$max = $rows ? max(array_column($rows, 'value')) : 0;
?>
<div class="steam-leaderboard">
    <div class="steam-leaderboard-head">
        <?php if ($heading !== '') { ?>
            <h3 class="steam-leaderboard-title"><?= h($heading) ?></h3>
        <?php } ?>
        <?php if ($subtitle !== '') { ?>
            <p class="steam-leaderboard-subtitle"><?= h($subtitle) ?></p>
        <?php } ?>
    </div>

    <?php if (!$rows) { ?>
        <p class="steam-leaderboard-empty"><?= t('No data yet.') ?></p>
    <?php } else { ?>
        <ol class="steam-leaderboard-list">
            <?php foreach ($rows as $row) {
                $width = $max > 0 ? max(2, round($row['value'] / $max * 100, 1)) : 0;
                ?>
                <li class="steam-leaderboard-row<?= $row['rank'] <= 3 ? ' steam-leaderboard-top' : '' ?>" title="<?= h($row['name'] . ': ' . $row['valueLabel']) ?>">
                    <span class="steam-leaderboard-rank"><?= (int) $row['rank'] ?></span>
                    <?php if ($row['image']) { ?>
                        <img class="steam-leaderboard-image" src="<?= h($row['image']) ?>" alt="" width="40" height="40" loading="lazy">
                    <?php } else { ?>
                        <span class="steam-leaderboard-image steam-leaderboard-image-empty" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($row['name'], 0, 1))) ?></span>
                    <?php } ?>
                    <span class="steam-leaderboard-main">
                        <a class="steam-leaderboard-name" href="<?= h($row['url']) ?>" target="_blank" rel="noopener"><?= h($row['name']) ?></a>
                        <?php if ($row['meta'] !== '') { ?>
                            <span class="steam-leaderboard-meta"><?= h($row['meta']) ?></span>
                        <?php } ?>
                    </span>
                    <span class="steam-leaderboard-value"><?= h($row['valueLabel']) ?></span>
                    <span class="steam-leaderboard-bar" aria-hidden="true"><span style="width: <?= $width ?>%"></span></span>
                </li>
            <?php } ?>
        </ol>
    <?php } ?>
</div>
