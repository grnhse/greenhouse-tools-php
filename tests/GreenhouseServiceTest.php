<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests;

use Greenhouse\GreenhouseToolsPhp\GreenhouseService;
use Greenhouse\GreenhouseToolsPhp\Services\ApiService;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class GreenhouseServiceTest extends \PHPUnit\Framework\TestCase
{
    private string $apiKey;
    private string $boardToken;
    private string $publicAuthKey;
    private string $secretAuthKey;
    private GreenhouseService $greenhouseService;

    public function setUp(): void
    {
        $this->apiKey           = 'testapikey';
        $this->boardToken       = 'test_token';
        $this->publicAuthKey    = 'test_public_auth_key';
        $this->secretAuthKey    = 'test_secret_auth_key';
        $this->greenhouseService = new GreenhouseService(array(
            'apiKey'    => $this->apiKey,
            'boardToken'=> $this->boardToken,
            'publicAuthKey' => $this->publicAuthKey,
            'secretAuthKey' => $this->secretAuthKey
        ));
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructWithNoBoardToken()
    {
        $service = new GreenhouseService(array('apiKey' => 'test_key'));
        $this->assertInstanceOf(
            '\Greenhouse\GreenhouseToolsPhp\GreenhouseService',
            $service
        );
    }
    
    public function testConstructWithNoApiKey()
    {
        $service = new GreenhouseService(array('boardToken' => 'test_token'));
        $this->assertInstanceOf(
            '\Greenhouse\GreenhouseToolsPhp\GreenhouseService',
            $service
        );
    }
    
    public function testGetJobBoardService()
    {
        $service = $this->greenhouseService->getJobBoardService();
        $this->assertInstanceOf(
            '\Greenhouse\GreenhouseToolsPhp\Services\JobBoardService',
            $service
        );
        $this->assertStringContainsString($this->boardToken, $service->scriptTag());
    }
    
    public function testGetJobApiService()
    {
        $service = $this->greenhouseService->getJobApiService();
        $this->assertInstanceOf(
            '\Greenhouse\GreenhouseToolsPhp\Services\JobApiService',
            $service
        );
        
        $this->assertEquals(
            'https://boards-api.greenhouse.io/v1/boards/test_token/embed/',
            $service->getJobBoardBaseUrl()
        );
        
        $this->assertInstanceOf('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient', $service->getClient());
    }
    
    public function testGetApplicationService()
    {
        $service = $this->greenhouseService->getApplicationApiService();
        $this->assertInstanceOf(
            '\Greenhouse\GreenhouseToolsPhp\Services\ApplicationService',
            $service
        );
        
        $baseUrl = ApiService::jobBoardBaseUrl($this->boardToken);
        $authHeader = 'Basic ' . base64_encode($this->apiKey . ':');
        $this->assertEquals($baseUrl, $service->getJobBoardBaseUrl());
        $this->assertEquals($authHeader, $service->getAuthorizationHeader());
    }
    
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetHarvestService()
    {
        $expectedBearerToken = 'Bearer mock_access_token_xyz';

        // Mockery::mock('overload:...') intercepts every `new HarvestAuthClient()`
        // call in this process — the PHP equivalent of RSpec's
        // allow(HarvestAuthClient).to receive(:new).and_return(double(getBearerToken: ...))
        $mockAuthClient = Mockery::mock(
            'overload:Greenhouse\GreenhouseToolsPhp\Clients\HarvestAuthClient'
        );
        $mockAuthClient->shouldReceive('getBearerToken')
                       ->once()
                       ->andReturn($expectedBearerToken);
        $mockAuthClient->shouldReceive('getExpiresAt')
                       ->once()
                       ->andReturn(null);

        $service = $this->greenhouseService->getHarvestService();

        $this->assertInstanceOf(
            '\Greenhouse\GreenhouseToolsPhp\Services\HarvestService',
            $service
        );

        // ApiService::getAuthorizationHeader() recomputes Basic auth from _apiKey
        // and never uses _authorizationHeader, so we read the stored value directly.
        $reflection = new \ReflectionClass(ApiService::class);
        $prop = $reflection->getProperty('_authorizationHeader');
        $this->assertEquals($expectedBearerToken, $prop->getValue($service));
    }
}
