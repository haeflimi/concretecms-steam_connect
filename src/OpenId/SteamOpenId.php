<?php

namespace SteamConnect\OpenId;

use GuzzleHttp\ClientInterface;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Minimal OpenID 2.0 relying party for Steam.
 *
 * Steam is the only provider we talk to, so discovery is skipped and the endpoint is fixed.
 * The assertion is verified with a direct "check_authentication" request to Steam (stateless mode).
 */
class SteamOpenId
{
    public const PROVIDER = 'https://steamcommunity.com/openid/login';

    protected const NS = 'http://specs.openid.net/auth/2.0';
    protected const IDENTIFIER_SELECT = 'http://specs.openid.net/auth/2.0/identifier_select';
    protected const CLAIMED_ID_PATTERN = '#^https://steamcommunity\.com/openid/id/([0-9]{17})$#';
    protected const REQUIRED_SIGNED_FIELDS = ['op_endpoint', 'claimed_id', 'identity', 'return_to', 'response_nonce', 'assoc_handle'];

    /** @var ClientInterface */
    protected $httpClient;

    public function __construct(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Build the URL the visitor has to be redirected to in order to sign in at Steam.
     */
    public function getAuthUrl(string $returnTo, string $realm): string
    {
        return self::PROVIDER . '?' . http_build_query([
            'openid.ns' => self::NS,
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $returnTo,
            'openid.realm' => $realm,
            'openid.identity' => self::IDENTIFIER_SELECT,
            'openid.claimed_id' => self::IDENTIFIER_SELECT,
        ], '', '&');
    }

    /**
     * Verify the positive assertion Steam sent back and return the SteamID64 of the visitor.
     *
     * @param string $queryString the raw query string of the callback request
     *                            (PHP would mangle the dots in the "openid.*" keys of $_GET)
     * @param string $expectedReturnTo the exact return_to URL we sent to Steam
     *
     * @return string|null the SteamID64, or null if the assertion is not valid
     */
    public function validate(string $queryString, string $expectedReturnTo): ?string
    {
        $params = $this->parseOpenIdParams($queryString);

        if (($params['openid.mode'] ?? '') !== 'id_res'
            || ($params['openid.ns'] ?? '') !== self::NS
            || ($params['openid.op_endpoint'] ?? '') !== self::PROVIDER
            || ($params['openid.return_to'] ?? '') !== $expectedReturnTo
            || ($params['openid.claimed_id'] ?? '') !== ($params['openid.identity'] ?? null)
            || !preg_match(self::CLAIMED_ID_PATTERN, $params['openid.claimed_id'], $matches)
        ) {
            return null;
        }

        $signed = explode(',', $params['openid.signed'] ?? '');
        if (array_diff(self::REQUIRED_SIGNED_FIELDS, $signed) !== [] || empty($params['openid.sig'])) {
            return null;
        }

        $params['openid.mode'] = 'check_authentication';
        try {
            $response = $this->httpClient->request('POST', self::PROVIDER, [
                'form_params' => $params,
                'timeout' => 10,
            ]);
        } catch (Throwable $e) {
            return null;
        }

        // The response is in OpenID "key-value form": one "key:value" pair per line.
        foreach (preg_split('/\r?\n/', (string) $response->getBody()) as $line) {
            if (trim($line) === 'is_valid:true') {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function parseOpenIdParams(string $queryString): array
    {
        $params = [];
        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            if (strpos($key, 'openid.') === 0) {
                $params[$key] = urldecode($value);
            }
        }

        return $params;
    }
}
