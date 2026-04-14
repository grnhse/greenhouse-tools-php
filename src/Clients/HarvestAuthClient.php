<?php

namespace Greenhouse\GreenhouseToolsPhp\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIClientException;
use Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException;

/**
 * This is a simple client to exchange Harvest V3 credentials for an Oauth Bearer token.
 */
class HarvestAuthClient
{
    private $_auth;
    private $_client;
    private $_bearerToken;
    private $_tokenExpiresAt;

    const AUTH_URL = 'https://auth.greenhouse.io/token?grant_type=client_credentials';

    public function __construct($publicAuthKey, $secretAuthKey)
    {
        $this->_auth = [$publicAuthKey, $secretAuthKey];
        $this->_client = new Client([
            'base_uri' => self::AUTH_URL,
            'auth' => $this->_auth
        ]);
    }

    public function getBearerToken()
    {
        $this->_authorize();
        return $this->_bearerToken;
    }

    public function getExpiresAt()
    {
        return $this->_tokenExpiresAt;
    }

    private function _authorize()
    {
        try {
            $response = $this->_client->request('POST');
            $body = json_decode($response->getBody(), true);
            if (!isset($body['token_type']) || !isset($body['access_token']) || !isset($body['expires_at'])) {
                throw new GreenhouseAPIResponseException("Invalid response from Harvest auth endpoint.");
            }

            $this->_bearerToken = $body['token_type'] . " " . $body['access_token'];
            $this->_tokenExpiresAt = $body['expires_at'];
        } catch (ClientException $e) {
            throw new GreenhouseAPIClientException($e->getMessage(), 0, $e);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new GreenhouseAPIResponseException($e->getMessage(), 0, $e);
        }
    }
}
