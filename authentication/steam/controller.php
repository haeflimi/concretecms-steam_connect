<?php

namespace Concrete\Package\SteamConnect\Authentication\Steam;

use Concrete\Core\Attribute\Key\UserKey;
use Concrete\Core\Authentication\Type\OAuth\GenericOauthTypeController;
use Concrete\Core\Form\Service\Widget\GroupSelector;
use Concrete\Core\Routing\RedirectResponse;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\User\Group\GroupRepository;
use Concrete\Core\User\User;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Illuminate\Support\Str;
use LogicException;
use SteamConnect\OpenId\SteamOpenId;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Steam does not speak OAuth, it is an OpenID 2.0 provider. We still extend the generic OAuth controller
 * because that gives us the core routes (/ccm/system/authentication/oauth2/steam/...), the user binding
 * storage (OauthUserMap) and the attach/detach integration in the user profile for free.
 */
class Controller extends GenericOauthTypeController
{
    protected const SESSION_STATE = 'steam_connect.state.';
    protected const SESSION_PENDING = 'steam_connect.pending_registration';

    public function getHandle()
    {
        return 'steam';
    }

    public function getAuthenticationTypeIconHTML()
    {
        return '<i class="fab fa-steam"></i>';
    }

    public function view()
    {
    }

    public function supportsRegistration()
    {
        return (bool) $this->app->make('config')->get('auth.steam.registration.enabled', false);
    }

    public function registrationGroupID()
    {
        return (int) $this->app->make('config')->get('auth.steam.registration.group');
    }

    /**
     * There is no OAuth service for Steam, every method of the parent class that relies on it is overridden.
     */
    public function getService()
    {
        throw new LogicException('Steam authentication uses OpenID 2.0 and has no OAuth service.');
    }

    public function getUniqueId()
    {
        return $this->getBindingForUser($this->app->make(User::class));
    }

    public function deauthenticate(User $u)
    {
    }

    public function isAuthenticated(User $u)
    {
        return $u->isRegistered();
    }

    public function handle_authentication_attempt()
    {
        if ($this->app->make(User::class)->isRegistered()) {
            return $this->errorResponse(t('You are already logged in.'));
        }

        return $this->redirectToSteam('callback');
    }

    public function handle_authentication_callback()
    {
        if ($this->app->make(User::class)->isRegistered()) {
            return $this->errorResponse(t('You are already logged in.'));
        }

        $steamId = $this->validateCallback('callback');
        if ($steamId === null) {
            return $this->errorResponse(t('Steam authentication failed. Please try again.'));
        }

        $userID = $this->getBoundUserID($steamId);
        if ($userID) {
            $userInfo = $this->app->make(UserInfoRepository::class)->getByID($userID);
            if (!$userInfo) {
                return $this->errorResponse(t('Failed to complete authentication.'));
            }
            // Accounts registered through Steam stay inactive until the email address has been confirmed.
            if (!$userInfo->isActive() && $userInfo->isValidated()) {
                return $this->errorResponse(t($this->app->make('config')->get('concrete.user.deactivation.message')));
            }
            if (!$userInfo->isActive() || ($this->app->make('config')->get('concrete.user.registration.validate_email') && !$userInfo->isValidated())) {
                return $this->errorResponse(t('This account has not yet been validated. Please check the email associated with this account and follow the link it contains.'));
            }

            $user = User::loginByUserID($userID);
            if (!$user || $user->isError()) {
                return $this->errorResponse(t('Failed to complete authentication.'));
            }
            $this->app->make('session')->migrate();

            return $this->completeAuthentication($user);
        }

        if ($this->supportsRegistration()) {
            // Steam doesn't share the e-mail address, so we have to ask the visitor for it before creating the account.
            $this->app->make('session')->set(self::SESSION_PENDING, [
                'steamId' => $steamId,
                'personaName' => $this->fetchPersonaName($steamId),
            ]);
            $token = $this->app->make('token')->generate('steam_register');

            return $this->redirectResponse('/login/callback/steam/handle_register/' . $token);
        }

        return $this->errorResponse(t('No local user account is associated with this Steam account. Please log in with a local account and connect your Steam account from your user profile.'));
    }

    public function handle_register($token = null)
    {
        $session = $this->app->make('session');
        $pending = $session->get(self::SESSION_PENDING);
        $tokenValidator = $this->app->make('token');
        if (!$this->supportsRegistration() || !is_array($pending) || empty($pending['steamId'])
            || (!$tokenValidator->validate('steam_register', $token) && !$tokenValidator->validate('steam_register'))
        ) {
            $this->set('error', t('Your registration session has expired. Please sign in with Steam again.'));

            return;
        }

        $this->set('show_email', true);
        $this->set('username', $pending['personaName'] ?: $pending['steamId']);
        $this->set('token', $tokenValidator);

        $email = $this->request->request->get('uEmail');
        if (!is_string($email) || $email === '') {
            return;
        }
        $email = trim($email);
        if (!$this->app->make('helper/validation/strings')->email($email)) {
            $this->set('error', t('Please enter a valid email address.'));

            return;
        }
        if ($this->app->make(UserInfoRepository::class)->getByEmail($email)) {
            $this->set('error', t('A user account already exists for this email, please log in and attach your Steam account from your account page.'));

            return;
        }

        try {
            $this->registerUser($pending['steamId'], $email, (string) $pending['personaName']);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $this->set('error', t('Unable to create new account.'));

            return;
        }
        $session->remove(self::SESSION_PENDING);

        $this->set('show_email', false);
        $this->set('message', t('Your account has been created. We sent an email to %s: click on the link it contains to confirm your email address, then you can log in with Steam.', $email));
    }

    public function handle_attach_attempt()
    {
        if (!$this->app->make(User::class)->isRegistered()) {
            return $this->errorResponse(t('A user must be logged in to attach a Steam account.'));
        }

        return $this->redirectToSteam('attach_callback');
    }

    public function handle_attach_callback()
    {
        $user = $this->app->make(User::class);
        if (!$user->isRegistered()) {
            return $this->redirectResponse('/login');
        }

        $steamId = $this->validateCallback('attach_callback');
        if ($steamId === null) {
            return $this->errorResponse(t('Steam authentication failed. Please try again.'));
        }

        try {
            $this->bindUser($user, $steamId);
        } catch (Throwable $e) {
            return $this->errorResponse(t('Unable to attach user.'));
        }

        return $this->successResponse(t('Successfully attached.'));
    }

    public function handle_detach_attempt()
    {
        $user = $this->app->make(User::class);
        if (!$user->isRegistered()) {
            return $this->redirectResponse('/login');
        }

        $binding = $this->getBindingForUser($user);
        if ($binding !== null) {
            try {
                $this->getBindingService()->clearBinding($user->getUserID(), $binding, $this->getHandle(), true);
            } catch (Throwable $e) {
                return $this->errorResponse(t('Unable to detach account.'));
            }
        }

        return $this->successResponse(t('Successfully detached.'));
    }

    public function saveAuthenticationType($args)
    {
        $config = $this->app->make('config');
        $config->save('auth.steam.apikey', trim((string) ($args['apikey'] ?? '')));
        $config->save('auth.steam.registration.enabled', !empty($args['registration_enabled']));
        $config->save('auth.steam.registration.group', (int) ($args['registration_group'] ?? 0));
    }

    public function edit()
    {
        $config = $this->app->make('config');
        $this->set('form', $this->app->make('helper/form'));
        $this->set('groupSelector', $this->app->make(GroupSelector::class));
        $this->set('apikey', (string) $config->get('auth.steam.apikey', ''));
        $this->set('registrationEnabled', (bool) $config->get('auth.steam.registration.enabled'));
        $registrationGroupID = (int) $config->get('auth.steam.registration.group');
        $registrationGroup = $registrationGroupID === 0 ? null : $this->app->make(GroupRepository::class)->getGroupById($registrationGroupID);
        $this->set('registrationGroup', $registrationGroup === null ? null : (int) $registrationGroup->getGroupID());
    }

    protected function redirectToSteam(string $action): RedirectResponse
    {
        $state = bin2hex(random_bytes(16));
        $this->app->make('session')->set(self::SESSION_STATE . $action, $state);

        $returnTo = $this->getReturnUrl($action, $state);

        return new RedirectResponse($this->getOpenId()->getAuthUrl($returnTo, $this->getRealm($returnTo)));
    }

    /**
     * @return string|null the verified SteamID64
     */
    protected function validateCallback(string $action): ?string
    {
        // The state ties the Steam response to the visitor's session, so nobody can make a victim
        // log in as (or attach) a Steam account they control.
        $session = $this->app->make('session');
        $state = $session->get(self::SESSION_STATE . $action);
        $session->remove(self::SESSION_STATE . $action);
        if (!is_string($state) || $state === '' || !hash_equals($state, (string) $this->request->query->get('state'))) {
            return null;
        }

        return $this->getOpenId()->validate(
            (string) $this->request->server->get('QUERY_STRING', ''),
            $this->getReturnUrl($action, $state)
        );
    }

    protected function getReturnUrl(string $action, string $state): string
    {
        $url = (string) $this->app->make(ResolverManagerInterface::class)->resolve(['/ccm/system/authentication/oauth2/steam/' . $action]);

        return $url . (strpos($url, '?') === false ? '?' : '&') . 'state=' . $state;
    }

    protected function getRealm(string $url): string
    {
        $parts = parse_url($url);

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . '/';
    }

    protected function getOpenId(): SteamOpenId
    {
        return new SteamOpenId($this->app->make('http/client'));
    }

    /**
     * Fetch the Steam display name, used as suggestion for the username. Requires a Steam Web API key.
     */
    protected function fetchPersonaName(string $steamId): ?string
    {
        $apiKey = (string) $this->app->make('config')->get('auth.steam.apikey', '');
        if ($apiKey === '') {
            return null;
        }
        try {
            $response = $this->app->make('http/client')->request('GET', 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/', [
                'query' => ['key' => $apiKey, 'steamids' => $steamId],
                'timeout' => 5,
            ]);
            $data = json_decode((string) $response->getBody(), true);
        } catch (Throwable $e) {
            return null;
        }
        $name = $data['response']['players'][0]['personaname'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Create an inactive, unvalidated account bound to the Steam ID and send the core "validate your email" mail.
     * Steam doesn't provide an email address, so the one the visitor typed in has to be confirmed. The link in the
     * mail (/login/callback/concrete/v/<hash>) marks the account as validated and activates it.
     */
    protected function registerUser(string $steamId, string $email, string $personaName): UserInfo
    {
        $registration = $this->app->make('user/registration');
        $userInfo = $registration->create([
            'uName' => $registration->getNewUsernameFromUserDetails($email, $personaName),
            'uPassword' => Str::random(64),
            'uEmail' => $email,
            'uIsValidated' => 0,
        ]);
        if (!$userInfo) {
            throw new \RuntimeException('Unable to create new account.');
        }
        $userInfo->deactivate();

        $user = User::getByUserID($userInfo->getUserID());
        if ($groupID = $this->registrationGroupID()) {
            $group = $this->app->make(GroupRepository::class)->getGroupById($groupID);
            if ($group) {
                $user->enterGroup($group);
            }
        }

        $attributes = UserKey::getRegistrationList();
        if (!empty($attributes)) {
            $userInfo->saveUserAttributesDefault($attributes);
        }

        $this->bindUser($user, $steamId);

        $this->app->make('user/status')->sendEmailValidation($userInfo);

        return $userInfo;
    }

    protected function redirectResponse(string $path): RedirectResponse
    {
        return new RedirectResponse((string) $this->app->make(ResolverManagerInterface::class)->resolve([$path]));
    }

    protected function errorResponse(string $error): RedirectResponse
    {
        $this->markError($error);

        return $this->redirectResponse('/login/callback/steam/handle_error');
    }

    protected function successResponse(string $message): RedirectResponse
    {
        $this->markSuccess($message);

        return $this->redirectResponse('/login/callback/steam/handle_success');
    }
}
