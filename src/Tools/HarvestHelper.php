<?php

namespace Greenhouse\GreenhouseToolsPhp\Tools;

use Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException;

/**
 * Given a method call from the HarvestService, parse it in to a URL and proper parameters.  This lets
 * us not have to write a new method every time a Harvest endpoint is added.
 */
class HarvestHelper
{
    public function parse($methodName, $parameters=array())
    {
        $return = array();
        $pattern = '/^(get|post|patch|put|delete)(\w+)$/i';
        $matched = preg_match($pattern, $methodName, $matches);
        
        if (!$matched) throw new GreenhouseServiceException("Harvest Service: invalid method $methodName.");

        $return['method'] = $matches[1];
        $return['url'] = $this->methodToEndpoint($matches[2], $parameters);

        if (isset($parameters['id'])) unset($parameters['id']);
        if (isset($parameters['bulk'])) unset($parameters['bulk']);

        if (isset($parameters['headers'])) {
            $return['headers'] = $parameters['headers'];
            unset($parameters['headers']);
        } else {
            $return['headers'] = array();
        }

        if (isset($parameters['body'])) {
            $return['body'] = $parameters['body'];
            unset($parameters['body']);
        } else {
            $return['body'] = null;
        }
        
        $return['parameters'] = $parameters;
        
        return $return;
    }

    /**
      * This parses method names into endpoints.
      * The two accepted formats are methodX and methodXForY.
      */
    
    public function methodToEndpoint($methodText, $parameters)
    {
        $id = isset($parameters['id']) ? $parameters['id'] : null;
        $bulk = isset($parameters['bulk']) ? $parameters['bulk'] : null;
        $objects = explode('For', $methodText);

        // A single object, just return the snaked version of it.
        if (sizeof($objects) == 1) {
            $url = $this->_decamelizeAndPluralize($objects[0]);
            if ($id) $url .= "/$id";
            if ($bulk === true) $url .= '/bulk';

        // Double object, expect the format object/id/object
        } else if (sizeof($objects) == 2) {
            if ($id) {
                $url = $this->_decamelizeAndPluralize($objects[1]) .
                        $this->_getDivider($id) .
                        $this->_decamelizeAndPluralize($objects[0]);
            } else if ($bulk === true) {
                $url = $this->_decamelizeAndPluralize($objects[1]) .
                        '/' .
                        $this->_decamelizeAndPluralize($objects[0]) .
                        '/bulk';
            }
            if (!isset($url)) {
                throw new GreenhouseServiceException("Harvest Service: Invalid method call $methodText. ID or bulk parameter is required for method calls with 'For' in them.");
            }
        } else {
            throw new GreenhouseServiceException("Harvest Service: Invalid method call $methodText.");
        }

        return $url;
    }

    public function addQueryString($url, $parameters=array())
    {
        if (sizeof($parameters)) {
            return $url . '?' . http_build_query($parameters);
        } else {
            return $url;
        }
    }

    private function _getDivider($id)
    {
        return $id ? "/$id/" : '/';
    }
    
    private function _decamelizeAndPluralize($string)
    {
        $decamelized = strtolower(preg_replace(['/([a-z0-9])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
        if (substr($decamelized, -1) != 's') $decamelized .= 's';
        
        return $decamelized;
    }
}
