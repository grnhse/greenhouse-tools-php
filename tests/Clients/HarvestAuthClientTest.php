<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Clients;

use Greenhouse\GreenhouseToolsPhp\Clients\HarvestAuthClient;

class HarvestAuthClientTest extends \PHPUnit\Framework\TestCase
{
    private HarvestAuthClient $client;

    public function setUp(): void
    {
        $this->client = new HarvestAuthClient('public', 'secret');
    }

    public function testInitialize()
    {
        $client = new HarvestAuthClient('public', 'secret');
        $this->assertInstanceOf(
            '\Greenhouse\GreenhouseToolsPhp\Clients\HarvestAuthClient',
            $client
        );
    }

    public function testGetException()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIClientException');

        $mockRequest     = new \GuzzleHttp\Psr7\Request('POST', HarvestAuthClient::AUTH_URL);
        $mockResponse    = new \GuzzleHttp\Psr7\Response(401, [], 'Unauthorized');
        $clientException = new \GuzzleHttp\Exception\ClientException('401 Unauthorized', $mockRequest, $mockResponse);

        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->expects($this->once())
                   ->method('request')
                   ->with('POST')
                   ->willThrowException($clientException);

        $reflection     = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('_client');
        $clientProperty->setValue($this->client, $mockClient);

        $this->client->getBearerToken();
    }

    public function testGetBearerToken()
    {
        $expectedTokenType   = 'Bearer';
        $expectedAccessToken = 'test_access_token_abc123';
        $expectedExpiresAt   = '2099-12-31T23:59:59+00:00';
        $responseBody = json_encode([
            'token_type'   => $expectedTokenType,
            'access_token' => $expectedAccessToken,
            'expires_at'   => $expectedExpiresAt,
        ]);
        $mockResponse = new \GuzzleHttp\Psr7\Response(200, [], $responseBody);
        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->expects($this->once())
                   ->method('request')
                   ->with('POST')
                   ->willReturn($mockResponse);

        $reflection = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('_client');
        $clientProperty->setValue($this->client, $mockClient);
        $bearerToken = $this->client->getBearerToken();
        $this->assertEquals($expectedTokenType . ' ' . $expectedAccessToken, $bearerToken);
        $tokenExpiresAtProperty = $reflection->getProperty('_tokenExpiresAt');
        $tokenExpiresAt = $tokenExpiresAtProperty->getValue($this->client);
        $this->assertEquals($expectedExpiresAt, $tokenExpiresAt);
    }

    public function testGetExceptionForInvalidResponse()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');
        $this->expectExceptionMessage('Invalid response from Harvest auth endpoint.');

        // Return a 200 but with a body that is missing the required fields.
        $mockResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode(['unexpected_key' => 'value']));
        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->expects($this->once())
                   ->method('request')
                   ->with('POST')
                   ->willReturn($mockResponse);

        $reflection     = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('_client');
        $clientProperty->setValue($this->client, $mockClient);

        $this->client->getBearerToken();
    }

    public function testGetExceptionForGuzzleException()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');

        // ConnectException extends GuzzleException but is NOT a ClientException,
        // so it exercises the second catch block.
        $mockRequest      = new \GuzzleHttp\Psr7\Request('POST', HarvestAuthClient::AUTH_URL);
        $connectException = new \GuzzleHttp\Exception\ConnectException('Connection refused', $mockRequest);

        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->expects($this->once())
                   ->method('request')
                   ->with('POST')
                   ->willThrowException($connectException);

        $reflection     = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('_client');
        $clientProperty->setValue($this->client, $mockClient);

        $this->client->getBearerToken();
    }
}
