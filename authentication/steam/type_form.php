<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Widget\GroupSelector $groupSelector
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $apikey
 * @var bool $registrationEnabled
 * @var int|null $registrationGroup
 */
?>

<div class="alert alert-info">
    <?= t('Steam login works without any keys. A Steam Web API key is optional: it is used to suggest the Steam display name as username on registration. <a href="%s" target="_blank" rel="noopener">Click here</a> to obtain one.', 'https://steamcommunity.com/dev/apikey') ?>
</div>

<div class="form-group">
    <?= $form->label('apikey', t('Steam Web API Key')) ?>
    <?= $form->password('apikey', $apikey, ['autocomplete' => 'off', 'class' => 'font-monospace', 'spellcheck' => 'false']) ?>
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
