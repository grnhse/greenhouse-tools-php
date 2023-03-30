<?php

namespace Greenhouse\GreenhouseToolsPhp\Services;

use Greenhouse\GreenhouseToolsPhp\Services\JobApiService;
use Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient;
use Greenhouse\GreenhouseToolsPhp\Tools\JsonHelper;
use Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseApplicationException;

/**
 * This class interacts with Greenhouse's job board API.  This class interacts with the GET
 * methods and does not require any API keys.  The constructor takes your job board 
 * token, which can be found on the job board configuration page, located
 * here: https://app.greenhouse.io/configure/dev_center/config
 *
 * 
 */
class ApplicationService extends ApiService
{
    private $_jobApiService;
    
    /**
     * The client token is your job board token.  For instance in the following
     * API get URL: https://boards-api.greenhouse.io/v1/boards/example_co/embed/
     * The board token is 'example_co'
     *
     * @param   string  $clientToken    As above
     */
    public function __construct($apiKey, $clientToken)
    {
        $this->_apiKey = $apiKey;
        $this->_clientToken = $clientToken;
        $this->_authorizationHeader = $this->getAuthorizationHeader($apiKey);

        $client = new GuzzleClient(array('base_uri' => self::APPLICATION_URL));
        $this->setClient($client);
        $jobService = new JobApiService($this->_clientToken);
        $this->_jobApiService = $jobService;
    }
    
    /**
     * Allow the job service to be overwritten by a new JobService.  Mostly for testing.
     *
     * @params  JobApiService   $jobService     A new job service.
     */
    public function setJobApiService(JobApiService $jobService)
    {
        $this->_jobApiService = $jobService;
    }
    
    /**
     * Post an application to Greenhouse.  The post parameters should be defined in an
     * array similar to the way you would send the item using libcurl.  The difference is
     * that instead of sending multiselect with indexed arrays; ie:
     *
     * 'var[0]' => 'foo',
     * 'var[1]' => 'bar'
     *
     * You will instead send them as an array
     *
     * 'var' => array('foo', 'bar')
     *
     * We'll use the client interface to transform this in to whatever works for the
     * given client and then post the application.
     *
     * Document attachments may also be defined with CurlFile.
     *
     * This method will also verify that required fields are not empty.  As this currently
     * requires verification on the client-side, we will run that verification before we
     * submit anything to the API.
     *
     * @params  Array   $postVars       An array of questions to be posted to the 
     *                                      Applications endpoint.
     * @return  boolean
     * @throws  GreenhouseApplicationException  if required fields are not included.
     * @throws  GreenhouseAPIResponseException  if a non-200 response is returned.
     */
    public function postApplication(Array $postVars=array())
    {
        $this->validateRequiredFields($postVars);
        $postParams = $this->_apiClient->formatPostParameters($postVars);
        $headers    = array('Authorization' => $this->_authorizationHeader);
        
        return $this->_apiClient->post($postParams, $headers);
    }
    
    /**
     * Method ensures that a non-empty response is contained in all fields marked as required.  If not,
     * an Exception is thrown.
     *
     * @params  Array   $postVars   The Greenhouse-formatted post parameters.
     * @return  boolean
     * @throws  GreenhouseApplicationException
     */
    public function validateRequiredFields($postVars)
    {
        $requiredFields = $this->getRequiredFields($postVars['id']);
        $missingKeys = array();
        
        foreach ($requiredFields as $human => $keys) {
            if (!$this->hasRequiredValue($postVars, $keys)) {
                $missingKeys[] = $human;
            }
        }
        
        if (!empty($missingKeys)) {
            throw new GreenhouseApplicationException('Submission missing required answers for: ' . implode(', ', $missingKeys));
        }
        
        return true;
    }
    
    /**
     * Since a required field can have multiple possible inputs, this method just checks the 
     * field has a value and returns true if it does.  If it finds no values in any of the inputs
     * it returns false.
     *
     * @params  Array   $postVars   Greenhouse post parameters.
     * @params  Array   $keys       The keys to check for in $postVars
     * @return  boolean
     */
    public function hasRequiredValue($postVars, $keys)
    {
        foreach ($keys as $key) {
            $requiredKey = $this->findKey($key, $postVars);
            if (array_key_exists($requiredKey, $postVars) && $postVars[$requiredKey] !== '') return true;
        }
        
        if ($this->_hasRequiredValuesForFiles($postVars, $key)) return true;

        return false;
    }
    
    /**
     * Previously, Greenhouse only accepted files in one way via resume and resume_text. Since, they 
     * included the ability to send files by linking to them or via binary content. However, they still
     * only mark the resume as required using the 'resume' field in the jobs description. This method also
     * checks the alternatives. The alternatives are:
     *   <field>_content and <field>_content_filename
     *   <field>_url and <field>_url_filename
     * If either of these are included, the requirement should pass. This is the case for resume 
     * and cover_letter; and also the case for custom questions which may also contain a file.
     *
     * @params  Array   $postVars   Greenhouse post parameters
     * @params  string  $key        The key we are validating
     * @return  boolean
     */
    private function _hasRequiredValuesForFiles($postVars, $key)
    {
        $contentKey = "{$key}_content";
        $contentFilenameKey = "{$key}_content_filename";
        $urlKey = "{$key}_url";
        $urlFilenameKey = "{$key}_url_filename";
        
        if (array_key_exists($contentKey, $postVars) && 
            array_key_exists($contentFilenameKey, $postVars) && 
            $postVars[$contentKey] !== '' &&
            $postVars[$contentFilenameKey] !== '') {
            return true;
        }
        
        if (array_key_exists($urlKey, $postVars) && 
            array_key_exists($urlFilenameKey, $postVars) && 
            $postVars[$urlKey] !== '' &&
            $postVars[$urlFilenameKey] !== '') {
            return true;
        }
        
        return false;
    }
    
    /**
     * This fixes an issue where the Greenhouse API returns required multiselects as <field[]> but we require
     * the user to submit it as <field>, thus making the system think the required field is not set.  This
     * checks that either <field> or <field[]> is set, in order to work correctly but also be backward 
     * compatable in case external systems have already corrected for it.
     * 
     * @params  Array   $key        The key to check for in $postVars
     * @params  Array   $postVars   Greenhouse post parameters.
     * @return  Mixed   string/null
     */
    public function findKey($key, $postVars)
    {
        $multiselectKey = str_replace('[]', '', $key);
        if (array_key_exists($key, $postVars)) {
            return $key;
        } else if (array_key_exists($multiselectKey, $postVars)) {
            return $multiselectKey;
        } else {
            return null;
        }
    }
    
    /**
     * Given a job id, make a requrest to the Greenhouse API for those questions and build an associative 
     * array indexed on the human-readable name containing an array of the indices that must be set.  The 
     * array is due to the fact that one  required question can have one of two things required.  For example, 
     * if first name, last name, and resume are required, your response would look like this:
     *
     * <code>
     *      array(
     *          'First Name' => array('first_name'),
     *          'Last Name'  => array('last_name'),
     *          'Resume'     => array('resume', 'resume_text')
     *      );
     * </code>
     *
     * Where either resume or resume_text must have a value.
     *
     * @params  number  $jobId      A Greenhouse job id.
     * @returns Array
     * @throws  GreenhouseAPIResponseException if getJob returns a non-200 response.
     */
    public function getRequiredFields($jobId)
    {
        $job = $this->_jobApiService->getJob($jobId, true);
        $jobHash = JsonHelper::decodeToHash($job);
        $requiredFields = array();
        
        foreach ($jobHash['questions'] as $question) {
            if ($question['required']) {
                $questionFields = array();
                foreach ($question['fields'] as $field) {
                    $questionFields[] = $field['name'];
                }
                $requiredFields[$question['label']] = $questionFields;
            }
        }
        
        return $requiredFields;
    }
}
