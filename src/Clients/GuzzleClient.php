<?php

namespace Greenhouse\GreenhouseToolsPhp\Clients;

use Greenhouse\GreenhouseToolsPhp\Clients\ApiClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
use Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIClientException;
use Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException;

/**
 * Client to wrap the Guzzle package.
 */
class GuzzleClient implements ApiClientInterface
{
    public $guzzleResponse;
    private $_client;
    private $_nextLink;
    private $_prevLink;
    private $_lastLink;
    
    /**
     * Constructor should receive an array that would be understood by the Guzzle
     * client constructor.  Constructor hands off an unmodified array to the Guzzle
     * constructor.
     *
     * @params  $options    Array
     */
    public function __construct($options)
    {
        $this->_client = new Client($options);
    }
    
    /**
     * Fetch the URL. As this is guzzle, this can take a relative URL.  See the Guzzle
     * docs more info.
     *
     * @params  string  $url        A relative URL off the base URL. A full URL 
     *                                  should work, too
     * @return  string  The Raw JSON response from Greenhouse
     * @throws  GreenhouseAPIResponseException  if the get request fails
     */
    public function get($url="")
    {
        try {
            $this->guzzleResponse = $this->_client->request('GET', $url);
            $this->_setLinks();
        } catch (RequestException $e) {
            throw new GreenhouseAPIResponseException($e->getMessage(), 0, $e);
        }
        
        /**
         * Just return the response cast as a string.  The rest of the universe need
         * not be aware of Guzzle's details.
         */
        return (string) $this->guzzleResponse->getBody();
    }
    
    /**
     * Post to the application endpoint.
     * 
     * @params  Array   $postVars       A guzzle compliant multipart array of post parameters.
     * @params  Array   $headers        This should only contain the Authorization header.
     * @params  string  $url            This can be left blank.  Url is set in the constructor.
     * @return  string
     * @throws  GreenhouseAPIResponseException  for non-200 responses
     */
    public function post(Array $postVars, Array $headers, $url=null)
    {
        try {
            $this->guzzleResponse = $this->_client->request(
                'POST',
                $url,
                array('multipart' => $postVars, 'headers' => $headers)
            );
        } catch (RequestException $e) {
            throw new GreenhouseAPIResponseException($e->getMessage(), 0, $e);
        }
        
        return (string) $this->guzzleResponse->getBody();
    }
    
    public function send($method, $url, Array $options=array())
    {
        try {
            $this->guzzleResponse = $this->_client->request($method, $url, $options);
            $this->_setLinks();
        } catch (RequestException $e) {
            throw new GreenhouseAPIResponseException($e->getMessage(), 0, $e);
        }
        
        return (string) $this->guzzleResponse->getBody();
    }
    
    /**
     * Return a Guzzle post parameter array that can be entered in to the 'multipart'
     * argument of a post request.  For details on this, see the Guzzle
     * documentation here: http://docs.guzzlephp.org/en/latest/request-options.html#multipart
     *
     * @params  Array   $postParameters
     * @return  Array
     */
    public function formatPostParameters(Array $postParameters=array())
    {
      $guzzleParams = array();
      /**
       * There are three possibile inputs for $postParameter values.  Strings, Curlfiles, and arrays.
       * This look should process them in to something that Guzzle understands.
       */
      foreach ($postParameters as $key => $value) {
        if ($value instanceof \CURLFile) {
          $guzzleParams[] = array(
            'name' => $key,
            'contents' => fopen($value->getFilename(), 'r'),
            'filename' => $value->getPostFilename()
          );
        } else if (is_array($value)) {
          foreach ($value as $val) {
            if (!is_array($val)) {
              $guzzleParams[] = array('name' => $key .'[]', 'contents' => "$val");
              continue;
            }

            // $val is an array, potentially containing nested elements that need to be serialized in
            // a way that the API accepts. See the `curl` example in the documentation to better understand the
            // nested array sturcture: https://developers.greenhouse.io/job-board.html#submit-an-application
            // Namely, arrays must be non-indexed, e.g. myarray[][mykey]=value instead of myarray[0][mykey]=value
            $query = http_build_query($val);
            $query = preg_replace('/%5B[\d]+%5D/simU', '%5B%5D', $query);
            foreach (explode('&', $query) as $val) {
              $val = urldecode($val);
              $tuple = explode('=', $val);
              $guzzleParams[] = array('name' => $key .'[]'. $tuple[0], 'contents' => "$tuple[1]");
            }
          }
        } else {
          $guzzleParams[] = array('name' => $key, 'contents' => "$value");
        }
      }

      return $guzzleParams;
    }

    public function getClient()
    {
        return $this->_client;
    }

    /**
     * Set the next/prev/last links using the current response object.
     */
    private function _setLinks()
    {
        $links = Psr7\parse_header($this->guzzleResponse->getHeader('Link'));
        foreach ($links as $link) {
            if ($link['rel'] == 'last') {
                $this->_lastLink = str_replace(['<', '>'], '', $link[0]);
            } elseif ($link['rel'] == 'next') {
                $this->_nextLink = str_replace(['<', '>'], '', $link[0]);
            } elseif ($link['rel'] == 'prev') {
                $this->_prevLink = str_replace(['<', '>'], '', $link[0]);
            }
        }
    }
    
    public function getNextLink()
    {
        return $this->_nextLink;
    }

    public function getPrevLink()
    {
        return $this->_prevLink;
    }

    public function getLastLink()
    {
        return $this->_lastLink;
    }

    public function getResponse()
    {
        return $this->guzzleResponse;
    }
}
