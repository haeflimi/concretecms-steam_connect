<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $heading
 * @var string $subtitle
 * @var array[] $rows rank, name, url, image, value, valueLabel, meta; game rows also appId, header (store capsule image), players (name, image, initials, label, hue), playerCount, roster (name, time; up to 30)
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
                        <?php if (!empty($row['players'])) { ?>
                            <span class="steam-leaderboard-meta steam-leaderboard-players">
                                <span class="steam-leaderboard-faces">
                                    <?php foreach ($row['players'] as $face) { ?>
                                        <?php if ($face['image']) { ?>
                                            <img class="steam-leaderboard-face" src="<?= h($face['image']) ?>" alt="<?= h($face['name']) ?>" title="<?= h($face['label']) ?>" width="24" height="24" loading="lazy">
                                        <?php } else { ?>
                                            <span class="steam-leaderboard-face steam-leaderboard-face-<?= (int) $face['hue'] ?>" role="img" aria-label="<?= h($face['name']) ?>" title="<?= h($face['label']) ?>"><?= h($face['initials']) ?></span>
                                        <?php } ?>
                                    <?php } ?>
                                </span>
                                <?= h($row['meta']) ?>
                            </span>
                        <?php } elseif ($row['meta'] !== '') { ?>
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
