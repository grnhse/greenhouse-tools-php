<?php

namespace Greenhouse\GreenhouseToolsPhp\Services;

use Greenhouse\GreenhouseToolsPhp\Services\ApiService;
use Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient;
use Greenhouse\GreenhouseToolsPhp\Clients\HarvestAuthClient;
use Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException;
use Greenhouse\GreenhouseToolsPhp\Tools\HarvestHelper;

/**
 * This class interacts with Greenhouse's Harvest API.
 */
class HarvestService extends ApiService
{
    private $_harvestHelper;
    private $_harvest;
    private $_publicAuthKey;
    private $_secretAuthKey;
    private $_tokenExpires;

    /**
     * This gives a Harvest service keyed to a specific version of the API. Version in the Harvest API is defined
     * in the URL. It is normal for some endpoints to skip versions.
     *
     * @params publicAuthKey    string  This is your public oauth key
     * @params secretAuthKey    string  This is your secret oauth key
     * @params version  string  The version of the API endpoint you expect to hit. V3 is the default as of
     *                          V3.0.0. Version 1 and 2 of Harvest has been deprecated and will be discontinued in 2026.
     */
    public function __construct($publicAuthKey, $secretAuthKey, $version='v3')
    {
        $this->_publicAuthKey = $publicAuthKey;
        $this->_secretAuthKey = $secretAuthKey;
        $client = new GuzzleClient(array('base_uri' => self::HARVEST_BASE_URL . $version . '/'));
        $this->setClient($client);
        $this->_authorize();
        $this->_harvestHelper = new HarvestHelper();
    }

    /**
     * Harvest V3 requires keys to be exchanged for a Bearer token. Do that automatically on
     * construction and set the GuzzleClient's Authorization header using the bearer token.
     */
    private function _authorize()
    {
        $authClient = new HarvestAuthClient($this->_publicAuthKey, $this->_secretAuthKey);
        $this->_authorizationHeader = $authClient->getBearerToken();
        $this->_tokenExpires = $authClient->getExpiresAt();
    }

    public function getHarvest()
    {
        return $this->_harvest;
    }

    public function getTokenExpiration()
    {
        return $this->_tokenExpires;
    }

    public function sendRequest()
    {
        $authHeader = array('Authorization' => $this->_authorizationHeader);
        $allHeaders = array_merge($this->_harvest['headers'], $authHeader);
        $requestUrl = $this->_harvestHelper->addQueryString($this->_harvest['url'], $this->_harvest['parameters']);
        $options = array(
            'headers' => $allHeaders,
            'body' => $this->_harvest['body'],
        );

        return $this->_apiClient->send($this->_harvest['method'], $requestUrl, $options);
    }

    /**
     * This method can be used to get the next page of paginated results. It will fail if the nextLink has not
     * already been set. It will throw an exception if the next link is not available, which means there are no more pages to get.
     */
    public function getNextPage()
    {
        if (!$this->_apiClient->getNextLink()) {
            throw new GreenhouseServiceException("Harvest Service: No next link available for paging.");
        }

        $authHeader = array('Authorization' => $this->_authorizationHeader);
        $allHeaders = array_merge($this->_harvest['headers'], $authHeader);

        return $this->_apiClient->send('GET', $this->_apiClient->getNextLink(), array('headers' => $allHeaders));
    }
    
    /**
     * This method is for harvest paging. The getNextLink method will return an API url
     * with a cursor. When this method returns nothing, there are no more pages.
     */
    public function nextLink()
    {
        return $this->_apiClient->getNextLink();
    }

    /**
     * In order to keep up to date with changes to the Harvest api and not trigger a re-release of this 
     * package each time a new method is created, the magic Call method is used to construct URLs to the
     * Harvest API.  This will use the called method and the arguments provided to create the proper URL
     * to request the service.  This should return the response from the API on success and raise an 
     * exception on failure.  In most cases, this should be straightforward parsing.
     *
     * 1) getApplications() will transform in to a Get request to "applications"
     * 2) getApplications(array('id' => 12345)) will translate to a GET request to "applications/12345"
     * 3) getScorecardsForApplications(array('id' => 12345)) will translate to 
     *      "applications/12345/scorecards
     */
    public function __call($name, $arguments)
    {
        $args = sizeof($arguments) > 0 ? $arguments[0] : array();
        $this->_harvest = $this->_harvestHelper->parse($name, $args);

        return $this->sendRequest();
    }
    
    /**
     * All methods below this point are methods that don't fit in the standard url format.  Either the 
     * words are not pluralized (application/12345/move instead of moves) or there is an additional word
     * at the end of the URL (application/123/offers/current_offer) which we can't handle in the magic method
     * above.  Standard URLs that fit the common Harvest format will work automatically going forward.  Any
     * exceptions should go below this line.
     */
    public function patchAnonymizeCandidate($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('patchAnonymizeForCandidate', $parameters);
        return $this->_trimUrlAndSendRequest();
    }

    public function postConvertProspectToCandidate($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('postConvertToCandidateForApplication', $parameters);
        return $this->_trimUrlAndSendRequest();
    }

    public function postHireApplication($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('postHireForApplication', $parameters);
        return $this->_trimUrlAndSendRequest();
    }

    public function postMergeCandidates($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('postMergeForCandidate', $parameters);
        return $this->_trimUrlAndSendRequest();
    }
        
    public function postMoveApplication($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('postMoveForApplication', $parameters);
        return $this->_trimUrlAndSendRequest();
    }

    public function postRejectApplication($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('postRejectForApplication', $parameters);
        return $this->_trimUrlAndSendRequest();
    }
    
    public function postUnrejectApplication($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('postUnrejectForApplication', $parameters);
        return $this->_trimUrlAndSendRequest();
    }
    
    public function getEeoc($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('getEeoc', $parameters);
        $this->_harvest['url'] = array_key_exists('id', $parameters) ? 'eeoc/' . $parameters['id'] : 'eeoc';
        return $this->sendRequest();
    }

    public function postActivateUser($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('postActivateUser', $parameters);
        if (isset($parameters['bulk']) && $parameters['bulk'] === true) {
            $this->_harvest['url'] = 'users/activate/bulk';
        } else {
            $this->_harvest['url'] = 'users/' . $parameters['id'] . '/activate';
        }
        return $this->sendRequest();
    }
    
    public function postDeactivateUser($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('postDeactivateUser', $parameters);
        if (isset($parameters['bulk']) && $parameters['bulk'] === true) {
            $this->_harvest['url'] = 'users/deactivate/bulk';
        } else {
            $this->_harvest['url'] = 'users/' . $parameters['id'] . '/deactivate';
        }
        return $this->sendRequest();
    }

    public function putReplaceApproverForApproverGroup($parameters=array())
    {
        $this->_harvest = $this->_harvestHelper->parse('putReplaceApproverForApproverGroup', $parameters);
        return $this->_trimUrlAndSendRequest();
    }

    private function _trimUrlAndSendRequest()
    {
        $this->_harvest['url'] = substr($this->_harvest['url'], 0, -1);
        return $this->sendRequest();
    }
}