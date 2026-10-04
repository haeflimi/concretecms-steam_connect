<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Widget\GroupSelector $groupSelector
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $apikey
 * @var bool $registrationEnabled
 * @var int|null $registrationGroup
 * @var string $clubUrl
 * @var int|null $clubGroup
 */
?>

<div class="alert alert-info">
    <?= t('Steam login works without any keys. The Steam Web API key is needed by the "Sync Steam Data" task and to suggest the Steam display name as username on registration. <a href="%s" target="_blank" rel="noopener">Click here</a> to obtain one.', 'https://steamcommunity.com/dev/apikey') ?>
</div>

<div class="form-group">
    <?= $form->label('apikey', t('Steam Web API Key')) ?>
    <?= $form->password('apikey', $apikey, ['autocomplete' => 'off', 'class' => 'font-monospace', 'spellcheck' => 'false']) ?>
</div>

<div class="form-group">
    <?= $form->label('club_url', t('Steam Group')) ?>
    <?= $form->text('club_url', $clubUrl, ['placeholder' => 'https://steamcommunity.com/groups/...']) ?>
    <div class="form-text"><?= t('Optional. The "Sync Steam Data" task checks which linked users are members of this group, and users who are not get a link to join it in their profile.') ?></div>
</div>
<div class="form-group">
    <?= $form->label('club_group', t('Group for Steam Group members')) ?>
    <?= $groupSelector->selectGroup('club_group', $clubGroup, tc('Group', 'None')) ?>
    <div class="form-text"><?= t('Optional. Linked users who are members of the Steam group are added to this group, everybody else is removed from it.') ?></div>
</div>

<div class="form-group">
    <?= $form->label('', t('Registration')) ?>
    <div class="form-check">
        <?= $form->checkbox('registration_enabled', '1', $registrationEnabled) ?>
        <label class="form-check-label" for="registration_enabled"><?= t('Allow automatic registration') ?></label>
    </div>
</div>
<div class="form-group registration-group">
    <?= $form->label('registration_group', t('Group to enter on registration')) ?>
    <?= $groupSelector->selectGroup('registration_group', $registrationGroup, tc('Group', 'None')) ?>
</div>

<script>
$(function() {
    $('input[name="registration_enabled"]')
        .on('change', function () {
            $('div.registration-group').toggle($(this).is(':checked'));
        })
        .trigger('change');
});
</script>
