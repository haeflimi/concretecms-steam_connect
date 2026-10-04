<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string|null $error
 * @var string|null $message
 * @var bool|null $show_email
 * @var string|null $username
 * @var Concrete\Core\Validation\CSRF\Token|null $token
 */

if (!empty($error)) {
    ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
    <?php
}
if (!empty($message)) {
    ?>
    <div class="alert alert-success"><?= h($message) ?></div>
    <?php
}

if (!empty($show_email)) {
    ?>
    <form method="post" action="<?= URL::to('/login/callback/steam/handle_register') ?>">
        <p><?= t('Register an account for "%s"', h($username)) ?></p>
        <div class="input-group">
            <input type="email" name="uEmail" placeholder="<?= t('Email Address') ?>" class="form-control" required />
            <button class="btn btn-primary"><?= t('Register') ?></button>
        </div>
        <?= $token->output('steam_register') ?>
    </form>
    <?php
} else {
    ?>
    <div class="form-group external-auth-option">
        <div class="d-grid">
            <a href="<?= URL::to('/ccm/system/authentication/oauth2/steam/attempt_auth') ?>" class="btn btn-steam">
                <i class="fab fa-steam"></i>
                <?= t('Log in with %s', 'Steam') ?>
            </a>
        </div>
    </div>
    <?php
}
?>
<style>
    .btn-steam,
    .ccm-ui .btn-steam {
        color: #fff;
        background-color: #171a21;
    }
    .btn-steam:focus,
    .btn-steam:hover,
    .ccm-ui .btn-steam:focus,
    .ccm-ui .btn-steam:hover {
        color: #fff;
        background-color: #31343b;
    }
    .btn-steam .fa-steam {
        margin: 0 6px 0 3px;
    }
</style>
