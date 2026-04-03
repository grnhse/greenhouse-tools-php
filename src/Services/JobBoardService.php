<?php

namespace Greenhouse\GreenhouseToolsPhp\Services;

class JobBoardService
{
    const BOARDS_URL = 'https://boards.greenhouse.io/';

    private string $_clientToken;

    public function __construct($clientToken)
    {
        $this->_clientToken = $clientToken;
    }
    
    /**
     * Get the Greenhouse div tag that will hold the iframe.
     *
     * @return string
     */
    public function jobBoardTag()
    {
        return '<div id="grnhse_app"></div>';
    }
    
    /**
     * Get the script tag that will populate the job board iframe.
     *
     * @return string
     */
    public function scriptTag()
    {
        return "<script src='https://app.greenhouse.io/embed/job_board/js?for={$this->_clientToken}'></script>";
    }
    
    /**
     * If you'd prefer to render the Greenhouse tags together, this just wraps the
     * preceding two methods.
     *
     * @return string
     */
    public function embedGreenhouseJobBoard()
    {
        return $this->jobBoardTag() . "\n" . $this->scriptTag();
    }
         
    /**
     * Returns a link to the Greenhouse-hosted job board.  Note this only returns the 
     * default link to a Greenhouse-hosted board.  It will not update if the job board
     * link in Greenhouse is changed.
     *
     * @param   string      $linkText       What you want the job board link to say.
     * @return  string
     */
    public function linkToGreenhouseJobBoard($linkText="Open Positions")
    {
        return "<a href='" . self::BOARDS_URL . "{$this->_clientToken}'>$linkText</a>";
    }
    
    /**
     * Returns a link to a Greenhouse hosted job application.  Same as above, this does
     * not change if you change the job application link in Greenhouse.
     *
     * @param   int     $jobId      The Job ID you want to apply to.
     * @param   string  $linkText   What you want the job board link to say.
     */
    public function linkToGreenhouseJobApplication($jobId, $linkText="Apply to this job", $sourceToken="")
    {
        $ghSrc = empty($sourceToken) ? '' : "?gh_src={$sourceToken}";
        
        return "<a href='" . self::BOARDS_URL . "{$this->_clientToken}/jobs/{$jobId}{$ghSrc}'>{$linkText}</a>";
    }
}