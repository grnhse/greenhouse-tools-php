<?php

namespace Greenhouse\GreenhouseToolsPhp\Services;

use Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException;

class ApiService
{
    protected $_apiClient;
    protected $_clientToken;
    protected $_apiKey;
    protected $_authorizationHeader;
    
    const APPLICATION_URL = 'https://boards-api.greenhouse.io/v1/applications/';
    const API_V1_URL = 'https://boards-api.greenhouse.io/v1/';
    const HARVEST_V3_URL = 'https://harvest.greenhouse.io/v3/';
    const HARVEST_BASE_URL = 'https://harvest.greenhouse.io/';

    public function setClient($apiClient)
    {
        $this->_apiClient = $apiClient;
    }
    
    /**
     * This is in a static method instead of a constant because it has a variable in it.
     * If I wanted to be crafty I could assemble a constant with a variable here but
     * since it's still in one place, who cares?
     *
     * @param   string  $clientToken    Your company's URL token.
     * @return  string
     */
    public static function jobBoardBaseUrl($clientToken)
    {
        return self::API_V1_URL . "boards/{$clientToken}/embed/";
    }
    
    /**
     * This wraps the above static method so you don't have to call it statically from
     * within the package.
     */
    public function getJobBoardBaseUrl()
    {
        if (empty($this->_clientToken)) {
            throw new GreenhouseServiceException('A client token must be defined to get the base URL.');
        }
        return self::jobBoardBaseUrl($this->_clientToken);
    }
    
    /**
     * Return whatever client we're using.  This should be something that implements the
     * GreenhouseClientInterface
     */
    public function getClient()
    {
        return $this->_apiClient;
    }
    
    /**
     * Base64 Encode an API key.  Greenhouse's encoding treats the key as the username with
     * a blank password.  Here we append the : for convenience.  We also handle the case
     * where the user appends the : themselves.  If no key is provided, we will attempt
     * to encode the private api key property.
     *
     * @params  string  $apiKey     A greenhouse job board API key.
     * @return  string  The other side of an Authorization header (Basic: <encoded key>)
     */
    public function getAuthorizationHeader($apiKey="")
    {
        if (empty($apiKey)) {
            if (empty($this->_apiKey)) throw new GreenhouseServiceException('No key provided to encode.');
            $apiKey = $this->_apiKey;
        }
        $key = rtrim($apiKey, ':') . ':';
        
        return 'Basic ' . base64_encode($key);
    }
}
