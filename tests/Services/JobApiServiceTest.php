<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Services;

use Greenhouse\GreenhouseToolsPhp\Services\JobApiService;

class JobApiServiceTest extends \PHPUnit\Framework\TestCase
{
    private JobApiService $jobApiService;
    private JobApiService $errorService;
    private string $baseUrl;

    public function setUp(): void
    {
        $this->jobApiService = new JobApiService('greenhouse');
        $this->errorService  = new JobApiService('exception_co');
        $this->baseUrl = JobApiService::jobBoardBaseUrl('greenhouse');
    }
    
    public function testGetContentQueryTrue()
    {
        $this->assertEquals(
            'test?content=true', 
            $this->jobApiService->getContentQuery('test', true)
        );
    }
    
    public function testGetContentQueryFalse()
    {
        $this->assertEquals(
            'test', 
            $this->jobApiService->getContentQuery('test')
        );
    }
    
    public function testGetQuestionsQueryTrue()
    {
        $this->assertEquals(
            'test?id=12345&questions=true', 
            $this->jobApiService->getQuestionsQuery('test?id=12345', true)
        );
    }

    public function testGetQuestionsQueryFalse()
    {
        $this->assertEquals(
            'test?id=12345', 
            $this->jobApiService->getQuestionsQuery('test?id=12345')
        );
    }
    
    public function testGetOfficesException()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');
        $this->errorService->getOffices();
    }
    
    public function testGetOfficeException()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');
        $this->errorService->getOffice(12345);
    }
    
    public function testGetDepartmentsException()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');
        $this->errorService->getDepartments();
    }
    
    public function testGetDepartmentException()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');
        $this->errorService->getDepartment(12345);
    }
    
    public function testGetBoardException()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');
        $this->errorService->getBoard();
    }
    
    public function testGetJobsException()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');
        $this->errorService->getJobs();
    }
    
    public function testGetJobException()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');
        $this->errorService->getJob(1);
    }
    
}