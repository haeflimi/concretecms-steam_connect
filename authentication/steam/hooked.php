<?php

defined('C5_EXECUTE') or die('Access Denied.');

$user = app(Concrete\Core\User\User::class);
$steamId = $this->controller->getBindingForUser($user);
$profile = $this->controller->getProfile($user);
$clubUrl = $this->controller->getClubUrl();
?>

<?php if ($clubUrl !== null && $profile !== null && $profile->isClubMember() === false) { ?>
    <div class="alert alert-info">
        <?= t('You are not a member of our Steam group yet.') ?>
        <a href="<?= h($clubUrl) ?>" target="_blank" rel="noopener" class="alert-link"><?= t('Join it on Steam') ?></a>
    </div>
<?php } ?>

<div class="form-group">
    <span><?= t('Detach your %s account', 'Steam') ?></span>
    <hr>
</div>
<div class="form-group">
    <a href="<?= URL::to('/ccm/system/authentication/oauth2/steam/attempt_detach') ?>" class="btn btn-steam">
        <i class="fab fa-steam"></i>
        <?= t('Detach your %s account', 'Steam') ?>
    </a>
    <?php if ($steamId) { ?>
        <div class="form-text">
            <?= t('Connected Steam account: %s', '<a href="https://steamcommunity.com/profiles/' . h($steamId) . '" target="_blank" rel="noopener">' . h($steamId) . '</a>') ?>
        </div>
    <?php } ?>
</div>

<style>
    .btn-steam,
    .ccm-ui .btn-steam {
        color: #fff;
        background-color: #171a21;
    }
    .btn-steam:hover,
    .ccm-ui .btn-steam:hover {
        color: #fff;
        background-color: #31343b;
    }
    .btn-steam .fa-steam {
        margin: 0 6px 0 3px;
    }
</style>
