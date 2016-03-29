<?php

namespace Greenhouse\GreenhouseToolsPhp;

use Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient;

class GreenhouseService
{
    private $_apiKey;
    private $_boardToken;
    
    public function __construct($options=array())
    {
        $this->_apiKey      = isset($options['apiKey'])     ? $options['apiKey']     : null;
        $this->_boardToken  = isset($options['boardToken']) ? $options['boardToken'] : null;
    }
    
    /**
     * The Job API Service does not require an API key.  This service interacts with
     * the GET endpoints in the Greenhouse job boards.
     */
    public function getJobApiService()
    {
        $apiService = new \Greenhouse\GreenhouseToolsPhp\Services\JobApiService($this->_boardToken);
        $apiClient  = new GuzzleClient(array(
            'base_uri' => "https://api.greenhouse.io/v1/boards/{$this->_boardToken}/embed/"
        ));
        $apiService->setClient($apiClient);
        
        return $apiService;
    }
    
    /**
     * The Appliction API Service handles posting applications in to Greenhouse.  This
     * requires your API key to be set.
     */
    public function getApplicationApiService()
    {
        $applicationService = new \Greenhouse\GreenhouseToolsPhp\Services\ApplicationService($this->_apiKey, $this->_boardToken);
        $apiClient  = new GuzzleClient(array(
            'base_uri' => 'https://api.greenhouse.io/v1/applications/'
        ));
        $applicationService->setClient($apiClient);
        
        return $applicationService;
    }
    
    public function getJobBoardService()
    {
        return new \Greenhouse\GreenhouseToolsPhp\Services\JobBoardService($this->_boardToken);
    }
}