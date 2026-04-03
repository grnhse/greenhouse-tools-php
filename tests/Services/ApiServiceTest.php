<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Services;

use Greenhouse\GreenhouseToolsPhp\Services\ApiService;
use Greenhouse\GreenhouseToolsPhp\Services\JobApiService;
use Greenhouse\GreenhouseToolsPhp\Services\ApplicationService;

class ApiServiceTest extends \PHPUnit\Framework\TestCase
{
    private ApiService $apiService;

    public function setUp(): void
    {
        $this->apiService = new ApiService();
    }
    
    public function testJobBoardBaseUrl()
    {
        $expected = 'https://boards-api.greenhouse.io/v1/boards/test_token/embed/';
        $this->assertEquals($expected, ApiService::jobBoardBaseUrl('test_token'));
    }
    
    public function testGetJobBoardBaseUrl()
    {
        $jobService = new JobApiService('test_token');
        $expected = 'https://boards-api.greenhouse.io/v1/boards/test_token/embed/';
        $this->assertEquals($expected, $jobService->getJobBoardBaseUrl());
    }
    
    public function testGetJobBoardBaseUrlFailsWithNoTokenSet()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException');
        $this->apiService->getJobBoardBaseUrl();
    }
    
    public function testGetAndSetClient()
    {
        $this->assertNull($this->apiService->getClient());
        $this->apiService->setClient('some_client');
        $this->assertEquals($this->apiService->getClient(), 'some_client');
    }
    
    public function testGetAuthorizationHeaderFromPrivateVariable()
    {
        $appService = new ApplicationService('test_this_api_key1', 'test_token');
        $expected = 'Basic dGVzdF90aGlzX2FwaV9rZXkxOg==';
        $this->assertEquals($expected, $appService->getAuthorizationHeader());
    }
    
    public function testGetAuthorizationHeaderFromArgument()
    {
        $expected = 'Basic dGhpc19pc19hbm90aGVyX2FwaV9rZXk6';
        $this->assertEquals($expected, $this->apiService->getAuthorizationHeader('this_is_another_api_key'));
    }
    
    public function testGetAuthorizationHeaderPrefersArgument()
    {
        $appService = new ApplicationService('test_this_api_key1', 'test_token');
        $expected = 'Basic dGhpc19pc19hbm90aGVyX2FwaV9rZXk6';
        $this->assertEquals($expected, $appService->getAuthorizationHeader('this_is_another_api_key'));
    }
    
    public function testGetAuthorizationHeaderExceptionBlank()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException');
        $this->apiService->getAuthorizationHeader();
    }
}
