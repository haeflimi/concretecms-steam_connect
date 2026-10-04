<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var string|null $title
 * @var string $leaderboard
 * @var string $period
 * @var string|null $sinceDate
 * @var int $appId
 * @var int $maxItems
 * @var array $leaderboardOptions
 * @var array $periodOptions
 * @var array $gameOptions
 */
?>
<div class="form-group">
    <?= $form->label('leaderboard', t('Leaderboard')) ?>
    <?= $form->select('leaderboard', $leaderboardOptions, $leaderboard) ?>
</div>
<div class="form-group">
    <?= $form->label('title', t('Title')) ?>
    <?= $form->text('title', (string) $title, ['placeholder' => t('Name of the leaderboard')]) ?>
</div>
<div class="form-group steam-leaderboard-game">
    <?= $form->label('appId', t('Game')) ?>
    <?= $form->select('appId', $gameOptions, (int) $appId) ?>
    <div class="form-text"><?= t('Only games in which somebody unlocked an achievement are listed.') ?></div>
</div>
<div class="form-group steam-leaderboard-period">
    <?= $form->label('period', t('Period')) ?>
    <?= $form->select('period', $periodOptions, $period) ?>
    <div class="form-text steam-leaderboard-period-help"><?= t('Playtime of other periods than "All time" and "Last 2 weeks" is calculated from the data collected by the Sync Steam Data task, so it only covers the time since its first run.') ?></div>
</div>
<div class="form-group steam-leaderboard-since">
    <?= $form->label('sinceDate', t('Start date')) ?>
    <input type="date" id="sinceDate" name="sinceDate" class="form-control" value="<?= h((string) $sinceDate) ?>">
    <div class="form-text"><?= t('E.g. the first day of a LAN party.') ?></div>
</div>
<div class="form-group">
    <?= $form->label('maxItems', t('Number of entries')) ?>
    <?= $form->number('maxItems', (int) $maxItems, ['min' => 1, 'max' => 100]) ?>
</div>

<script>
$(function () {
    var $leaderboard = $('#leaderboard'), $period = $('#period');
    function update() {
        var type = $leaderboard.val();
        $('.steam-leaderboard-game').toggle(type === 'achievements');
        $('.steam-leaderboard-period').toggle(type === 'achievements' || type === 'most_played');
        $('.steam-leaderboard-period-help').toggle(type === 'most_played');
        $('.steam-leaderboard-since').toggle($('.steam-leaderboard-period').is(':visible') && $period.val() === 'since');
    }
    $leaderboard.add($period).on('change', update);
    update();
});
</script>
