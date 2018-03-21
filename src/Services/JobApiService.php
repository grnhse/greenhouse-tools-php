<?php

namespace Greenhouse\GreenhouseToolsPhp\Services;

use Greenhouse\GreenhouseToolsPhp\Services\ApiService;
use Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient;
use Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException;

/**
 * This class interacts with Greenhouse's job board API.  This class interacts with the GET
 * methods and does not require any API keys.  The constructor takes your job board 
 * token, which can be found on the job board configuration page, located
 * here: https://app.greenhouse.io/configure/dev_center/config
 */
class JobApiService extends ApiService
{
    /**
     * The client token is your job board token.  For instance in the following
     * API get URL: https://api.greenhouse.io/v1/boards/example_co/embed/
     * The board token is 'example_co'
     *
     * @param   string  $clientToken    As above
     */
    public function __construct($clientToken)
    {
        $this->_clientToken = $clientToken;
        $client = new GuzzleClient(array('base_uri' => self::jobBoardBaseUrl($clientToken)));
        $this->setClient($client);
    }
    
    /**
     * GET $baseUrl/offices
     *
     * @return string   JSON response string from Greenhouse API.
     * @throws GreenhouseAPIResponseException for non-200 responses
     */
    public function getOffices()
    {
        return $this->_apiClient->get('offices');
    }
    
    /**
     * GET $baseUrl/office?id=$id
     *
     * @param   $id     number      The id of the office to retrieve
     * @return  string  JSON response string from Greenhouse API.
     * @throws  GreenhouseAPIResponseException for non-200 responses
     */
    public function getOffice($id)
    {
        return $this->_apiClient->get("office?id=$id");
    }
    
    /**
     * GET $baseUrl/departments(?render_as=list|tree)
     *
     * @param  boolean     $tree     Render the results as a tree
     * @return string   JSON response string from Greenhouse API.
     * @throws GreenhouseAPIResponseException for non-200 responses
     */
    public function getDepartments($tree=false)
    {
        $queryString = $this->getRenderAsQuery('departments', $tree);
        return $this->_apiClient->get($queryString);
    }
    
    /**
     * GET $baseUrl/office?id=$id
     *
     * @param   $id     number      The id of the department to retrieve
     * @return  string  JSON response string from Greenhouse API.
     * @throws  GreenhouseAPIResponseException for non-200 responses
     */
    public function getDepartment($id)
    {
        return $this->_apiClient->get("department?id=$id");
    }
    
    /**
     * GET $baseUrl     (The Job board name and intro)
     *
     * @return  string  JSON response string from Greenhouse API.
     * @throws  GreenhouseAPIResponseException for non-200 responses
     */
    public function getBoard()
    {
        return $this->_apiClient->get();
    }
    
    /**
     * GET $baseUrl/jobs(?content=true)
     *
     * @param   boolean     $content    Append the content paramenter to get the
     *                                      job post content, department, and office.
     * @return  string      JSON response string from Greenhouse API.
     * @throws  GreenhouseAPIResponseException for non-200 responses
     */
    public function getJobs($content=false)
    {
        $queryString = $this->getContentQuery('jobs', $content);
        return $this->_apiClient->get($queryString);
    }
    
    /**
     * GET $baseUrl/job?id=$id(?questions=true)
     *
     * @param   $id         number      The id of the job to retrieve
     * @param   $question   boolean     Append the question paramenter to get the
     *                                      question info in the job response.
     * @return  string      JSON response string from Greenhouse API.
     * @throws  GreenhouseAPIResponseException for non-200 responses
     */
    public function getJob($id, $questions=false)
    {
        $queryString = $this->getQuestionsQuery("job?id=$id", $questions);
        return $this->_apiClient->get($queryString);
    }
    
    /**
     * Method appends the content parameter to the URL if content is true, returns
     * just the uriString if it's false.
     *
     * @param   string  $uriString      A base string.
     * @param   boolean $showConent     If true, appends ?content=true to $uriString
     * @return  string
     */
    public function getContentQuery($uriString, $showContent=false)
    {
        $queryString = $showContent ? '?content=true' : '';
        return $uriString . $queryString;
    }
    
    /**
     * Shortcut method appends questions=true to the query string for a single
     */
    public function getQuestionsQuery($uriString, $showQuestions=false)
    {
        $queryString = $showQuestions ? '&questions=true' : '';
        return $uriString . $queryString;
    }

    /**
     * Shortcut method appends render_as=list|tree to the query string for departments
     */
    public function getRenderAsQuery($uriString, $showTree=false)
    {
        $queryString = '?render_as=' . ($showTree ? 'tree' : 'list');
        return $uriString . $queryString;
    }
}
