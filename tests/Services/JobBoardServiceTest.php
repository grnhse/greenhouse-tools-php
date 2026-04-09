<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Services;

use Greenhouse\GreenhouseToolsPhp\Services\JobBoardService;

class JobBoardServiceTest extends \PHPUnit\Framework\TestCase
{
    private JobBoardService $jobBoardService;

    public function setUp(): void
    {
        $this->jobBoardService = new JobBoardService('test_token');
    }
    
    public function testJobBoardTag()
    {
        $this->assertEquals(
            $this->jobBoardService->jobBoardTag(),
            '<div id="grnhse_app"></div>'
        );
    }
    
    public function testScriptTag()
    {
        $this->assertEquals(
            $this->jobBoardService->scriptTag(),
            "<script src='https://app.greenhouse.io/embed/job_board/js?for=test_token'>" . 
                '</script>'
        );
    }
    
    public function testEmbedGreenhouseJobBoard()
    {
        $this->assertEquals(
            $this->jobBoardService->embedGreenhouseJobBoard(),
            '<div id="grnhse_app"></div>' . 
            "\n" . 
            "<script src='https://app.greenhouse.io/embed/job_board/js?for=test_token'>" . 
            "</script>"
        );
    }
    
    public function testLinkToGreenhouseJobBoardWithDefault()
    {
        $this->assertEquals(
            $this->jobBoardService->linkToGreenhouseJobBoard(),
            "<a href='https://boards.greenhouse.io/test_token'>Open Positions</a>"
        );
    }
    
    public function testLinkToGreenhouseJobBoardWithArgument()
    {
        $this->assertEquals(
            $this->jobBoardService->linkToGreenhouseJobBoard('Link to jobs!'),
            "<a href='https://boards.greenhouse.io/test_token'>Link to jobs!</a>"
        );
    }
    
    public function testLinkToGreenhouseJobApplicationWithDefault()
    {
        $this->assertEquals(
            $this->jobBoardService->linkToGreenhouseJobApplication(12345),
            "<a href='https://boards.greenhouse.io/test_token/jobs/12345'>" .
            'Apply to this job</a>'
        );
    }
    
    public function testLinkToGreenhouseJobApplicationWithArgument()
    {
        $this->assertEquals(
            $this->jobBoardService->linkToGreenhouseJobApplication(12345, 'Some work'),
            "<a href='https://boards.greenhouse.io/test_token/jobs/12345'>" .
            'Some work</a>'
        );
    }
    
    public function testLinkToGreenhouseJobApplicationWithToken()
    {
        $this->assertEquals(
            $this->jobBoardService->linkToGreenhouseJobApplication(12345, 'Some work', 'abcde'),
            "<a href='https://boards.greenhouse.io/test_token/jobs/12345?gh_src=abcde'>" .
            'Some work</a>'
        );
    }
}