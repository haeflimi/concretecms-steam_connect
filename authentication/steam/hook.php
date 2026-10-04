<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<div class="form-group">
    <span><?= t('Attach a %s account', 'Steam') ?></span>
    <hr>
</div>
<div class="form-group">
    <a href="<?= URL::to('/ccm/system/authentication/oauth2/steam/attempt_attach') ?>" class="btn btn-steam">
        <i class="fab fa-steam"></i>
        <?= t('Attach a %s account', 'Steam') ?>
    </a>
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
