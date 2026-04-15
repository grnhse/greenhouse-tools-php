<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Services;

use Greenhouse\GreenhouseToolsPhp\Services\HarvestService;
use Greenhouse\GreenhouseToolsPhp\Services\ApiService;
use Greenhouse\GreenhouseToolsPhp\GreenhouseService;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * This test only tests that the service requests generate the expected links and arrays.  This does not
 * test the response from harvest and, in most cases, that the responses are valid.  Harvest is expected
 * to reject invalid requests.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class HarvestServiceTest extends \PHPUnit\Framework\TestCase
{
    const MOCK_BEARER_TOKEN = 'Bearer mock_harvest_token';

    private HarvestService $harvestService;
    private string $expectedAuth;

    public function setUp(): void
    {
        $mockAuthClient = Mockery::mock(
            'overload:Greenhouse\GreenhouseToolsPhp\Clients\HarvestAuthClient'
        );
        $mockAuthClient->shouldReceive('getBearerToken')
                       ->andReturn(self::MOCK_BEARER_TOKEN);
        $mockAuthClient->shouldReceive('getExpiresAt')
                       ->andReturn('2099-12-31T23:59:59+00:00');

        $this->harvestService = new HarvestService('test_public', 'test_secret');
        $apiStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $apiStub->method('getNextLink')->willReturn('http://example.com/next');

        $this->harvestService->setClient($apiStub);
        $this->expectedAuth = self::MOCK_BEARER_TOKEN;
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Read the protected _authorizationHeader property set by HarvestService::_authorize()
     * (ApiService::getAuthorizationHeader() recomputes Basic-auth from _apiKey; it does not
     * return the Bearer token stored directly in _authorizationHeader, so reflection is the
     * only way to verify the Bearer token without changing production code.)
     */
    private function authHeader(): string
    {
        $prop = (new \ReflectionClass(ApiService::class))->getProperty('_authorizationHeader');
        return $prop->getValue($this->harvestService);
    }
    
    public function testGetNextLink()
    {
        $this->assertEquals($this->harvestService->nextLink(), 'http://example.com/next');
    }

    // ---------------------------------------------------------------
    // sendRequest() tests
    // ---------------------------------------------------------------

    public function testSendRequestIncludesAuthorizationHeader()
    {
        $mockClient = $this->createMock('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $mockClient->expects($this->once())
                   ->method('send')
                   ->with(
                       'get',
                       'applications',
                       $this->callback(function ($options) {
                           return isset($options['headers']['Authorization'])
                               && $options['headers']['Authorization'] === self::MOCK_BEARER_TOKEN;
                       })
                   )
                   ->willReturn('{"applications":[]}');
        $mockClient->method('getNextLink')->willReturn('');
        $this->harvestService->setClient($mockClient);

        $result = $this->harvestService->getApplications(array());
        $this->assertEquals('{"applications":[]}', $result);
    }

    public function testSendRequestMergesCustomHeadersWithAuth()
    {
        $mockClient = $this->createMock('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $mockClient->expects($this->once())
                   ->method('send')
                   ->with(
                       'post',
                       'candidates',
                       $this->callback(function ($options) {
                           return $options['headers'] === [
                               'On-Behalf-Of' => 234,
                               'Authorization' => self::MOCK_BEARER_TOKEN,
                           ];
                       })
                   )
                   ->willReturn('{}');
        $mockClient->method('getNextLink')->willReturn('');
        $this->harvestService->setClient($mockClient);

        $this->harvestService->postCandidates(array(
            'headers' => array('On-Behalf-Of' => 234),
            'body'    => '{"name":"test"}',
        ));
    }

    public function testSendRequestAppendsQueryParametersToUrl()
    {
        $mockClient = $this->createMock('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $mockClient->expects($this->once())
                   ->method('send')
                   ->with('get', 'applications?page=2&per_page=100', $this->anything())
                   ->willReturn('{}');
        $mockClient->method('getNextLink')->willReturn('');
        $this->harvestService->setClient($mockClient);

        $this->harvestService->getApplications(array('page' => 2, 'per_page' => 100));
    }

    public function testSendRequestPassesBodyToApiClient()
    {
        $body = '{"first_name":"Jane","last_name":"Doe"}';
        $mockClient = $this->createMock('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $mockClient->expects($this->once())
                   ->method('send')
                   ->with(
                       'post',
                       'candidates',
                       $this->callback(function ($options) use ($body) {
                           return $options['body'] === $body;
                       })
                   )
                   ->willReturn('{}');
        $mockClient->method('getNextLink')->willReturn('');
        $this->harvestService->setClient($mockClient);

        $this->harvestService->postCandidates(array('body' => $body));
    }

    public function testSendRequestReturnsApiClientResponse()
    {
        $apiResponse = '{"id":42,"name":"Engineering"}';
        $stubClient = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $stubClient->method('send')->willReturn($apiResponse);
        $stubClient->method('getNextLink')->willReturn('');
        $this->harvestService->setClient($stubClient);

        $result = $this->harvestService->getDepartments(array());
        $this->assertEquals($apiResponse, $result);
    }

    // ---------------------------------------------------------------
    // getNextPage() tests
    // ---------------------------------------------------------------

    public function testGetNextPageThrowsExceptionWhenNoNextLink()
    {
        $stubClient = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $stubClient->method('getNextLink')->willReturn('');
        $this->harvestService->setClient($stubClient);

        $this->expectException(
            \Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException::class
        );
        $this->expectExceptionMessage('Harvest Service: No next link available for paging.');

        $this->harvestService->getNextPage();
    }

    public function testGetNextPageSendsGetRequestToNextLinkUrl()
    {
        $nextUrl   = 'https://harvest.greenhouse.io/v3/applications?cursor=abc123';
        $pageTwo   = '{"applications":[{"id":2}]}';

        // First call: populate $_harvest via a real harvest method using the default stub.
        $this->harvestService->getApplications(array());

        // Now swap in a mock to capture the getNextPage() call.
        $mockClient = $this->createMock('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $mockClient->method('getNextLink')->willReturn($nextUrl);
        $mockClient->expects($this->once())
                   ->method('send')
                   ->with(
                       'GET',
                       $nextUrl,
                       $this->callback(function ($options) {
                           return isset($options['headers']['Authorization'])
                               && $options['headers']['Authorization'] === self::MOCK_BEARER_TOKEN;
                       })
                   )
                   ->willReturn($pageTwo);
        $this->harvestService->setClient($mockClient);

        $result = $this->harvestService->getNextPage();
        $this->assertEquals($pageTwo, $result);
    }

    public function testGetNextPageMergesLastRequestHeadersWithAuth()
    {
        $nextUrl = 'https://harvest.greenhouse.io/v3/candidates?cursor=xyz';

        // Make an initial request that carries a custom header.
        $setupStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $setupStub->method('getNextLink')->willReturn($nextUrl);
        $setupStub->method('send')->willReturn('{}');
        $this->harvestService->setClient($setupStub);
        $this->harvestService->postCandidates(array(
            'headers' => array('On-Behalf-Of' => 99),
            'body'    => '{}',
        ));

        // Verify getNextPage() forwards those headers alongside Authorization.
        $mockClient = $this->createMock('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $mockClient->method('getNextLink')->willReturn($nextUrl);
        $mockClient->expects($this->once())
                   ->method('send')
                   ->with(
                       'GET',
                       $nextUrl,
                       $this->callback(function ($options) {
                           return $options['headers'] === [
                               'On-Behalf-Of'  => 99,
                               'Authorization' => self::MOCK_BEARER_TOKEN,
                           ];
                       })
                   )
                   ->willReturn('{"candidates":[]}');
        $this->harvestService->setClient($mockClient);

        $result = $this->harvestService->getNextPage();
        $this->assertEquals('{"candidates":[]}', $result);
    }

    public function testGetApplicationsNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applications',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getApplications($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetApplicationsPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'applications',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getApplications($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetApplication()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applications/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getApplications($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testDeleteApplication()
    {
       $expected = array(
            'method' => 'delete',
            'url' => 'applications/12345',
            'headers' => array('On-Behalf-Of' => 23456),
            'body' => null,
            'parameters' => array()
        );
        $params = array(
            'id' => 12345,
            'headers' => array('On-Behalf-Of' => 23456)
        );
        
        $this->harvestService->deleteApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPatchApplication()
    {
       $expected = array(
            'method' => 'patch',
            'url' => 'applications/12345',
            'headers' => array('On-Behalf-Of' => 23456),
            'body' => '{"source_id": 1234}',
            'parameters' => array()
        );
        $params = array(
            'id' => 12345,
            'body' => '{"source_id": 1234}',
            'headers' => array('On-Behalf-Of' => 23456)
        );
        
        $this->harvestService->patchApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());    
    }

    public function testPostMoveApplication()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'applications/12345/move',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"from_stage_id": 345}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"from_stage_id": 345}',
            'id' => 12345
        );

        $this->harvestService->postMoveApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostHireApplication()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'applications/12345/hire',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"opening_id": 345}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"opening_id": 345}',
            'id' => 12345
        );

        $this->harvestService->postHireApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostConvertProspectToCandidate()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'applications/12345/convert_to_candidate',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->postConvertProspectToCandidate(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostRejectApplication()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'applications/12345/reject',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"from_stage_id": 345}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"from_stage_id": 345}',
            'id' => 12345
        );

        $this->harvestService->postRejectApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostUnrejectApplication()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'applications/12345/unreject',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => null,
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'id' => 12345
        );

        $this->harvestService->postUnrejectApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetCandidatesNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'candidates',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getCandidates($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetCandidatesPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'candidates',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getCandidates($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetCandidate()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'candidates/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getCandidates($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPatchCandidate()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'candidates/12345',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'id' => 12345
        );

        $this->harvestService->patchCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostApplicationForCandidate()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidates/12345/applications',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'id' => 12345
        );

        $this->harvestService->postApplicationForCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostAttachment()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidates/12345/attachments',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'id' => 12345
        );

        $this->harvestService->postAttachmentForCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostCandidate()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidates',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"first_name":"John","last_name":"Doe","phone_numbers":[{"value":"31012345","type":"other"}],"email_addresses":[{"value":"john@doe.com","type":"personal"}],"applications":[{"job_id":146855}]}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"first_name":"John","last_name":"Doe","phone_numbers":[{"value":"31012345","type":"other"}],"email_addresses":[{"value":"john@doe.com","type":"personal"}],"applications":[{"job_id":146855}]}',
        );
        
        $this->harvestService->postCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
        
    public function testPostProspect()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'prospects',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"first_name":"John","last_name":"Doe","phone_numbers":[{"value":"31012345","type":"other"}],"email_addresses":[{"value":"john@doe.com","type":"personal"}],"applications":[{"job_id":146855}]}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"first_name":"John","last_name":"Doe","phone_numbers":[{"value":"31012345","type":"other"}],"email_addresses":[{"value":"john@doe.com","type":"personal"}],"applications":[{"job_id":146855}]}',
        );
        
        $this->harvestService->postProspect($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPatchAnonymizeCandidate()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'candidates/12345/anonymize',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'parameters' => array('fields' => 'some,fields,go,here')
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'id' => 12345,
            'fields' => 'some,fields,go,here'
        );

        $this->harvestService->patchAnonymizeCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostMergeCandidate()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidates/12345/merge',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"secondary_candidate_id":234}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"secondary_candidate_id":234}',
            'id' => 12345
        );
        
        $this->harvestService->postMergeCandidates($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetCustomFieldsNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'custom_fields',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
        
        $this->harvestService->getCustomFields($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetCustomFieldsPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'custom_fields',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getCustomFields($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());    
    }
    
    public function testGetCustomField()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'custom_fields/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => '12345');

        $this->harvestService->getCustomField($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetDepartmentsNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'departments',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getDepartments($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetDepartmentsPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'departments',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getDepartments($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetDepartment()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'departments/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getDepartments($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostDepartment()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'departments',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}'
        );

        $this->harvestService->postDepartments($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());    
    }
    
    public function testGetEeocNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'eeoc',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getEeoc($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());    
    }
    
    public function testGetEeocWithPaging()
    {
        $params = array('per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'eeoc',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getEeoc($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());    
    }
    
    public function testGetEeocById()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'eeoc/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getEeoc($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());    
    }
    
    public function testGetEmailTemplatesNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'email_templates',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getEmailTemplates($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetEmailTemplatesPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'email_templates',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getEmailTemplates($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetEmailTemplate()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'email_templates/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getEmailTemplates($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobPosts()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'job_posts',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();

        $this->harvestService->getJobPosts($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetJobPostsPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'job_posts',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getJobPosts($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchJobPost()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'job_posts/12345',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->patchJobPost($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobsNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'jobs',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getJobs($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetJobsPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'jobs',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getJobs($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetJob()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'jobs/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getJobs($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchJob()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'jobs/12345',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->patchJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostJob()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'jobs',
            'body' => '{"update_body":"json"}',
            'parameters' => array(),
            'headers' => array()
        );
        $params = array(
            'body' => '{"update_body":"json"}'
        );

        $this->harvestService->postJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetOfferNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'offers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getOffers($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetOffersPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'offers',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getOffers($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetOffer()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'offers/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getOffers($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetOffersForApplications()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applications/12345/offers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getOffersForApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetOfficesNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'offices',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();

        $this->harvestService->getOffices($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetOfficesPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'offices',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getOffices($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetOffice()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'offices/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getOffices($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostOffice()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'offices',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}'
        );

        $this->harvestService->postOffice($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());  
    }  

    
    public function testGetRejectionReasonsNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'rejection_reasons',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getRejectionReasons($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetRejectionReasonsPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'rejection_reasons',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getRejectionReasons($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetSecheduledInterviewsNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scheduled_interviews',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getScheduledInterviews($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetScheduledInterviewsPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'scheduled_interviews',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getScheduledInterviews($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetScheduledInterviewById()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scheduled_interviews/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getScheduledInterview($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetScorecardsNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scorecards',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getScorecards($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetScorecardsPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'scorecards',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getScorecards($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetScorecard()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scorecards/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getScorecards($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetScorecardForApplication()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applications/12345/scorecards',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getScorecardsForApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetSourcesNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'sources',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getSource($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetSourcesPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'sources',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getSource($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetSource()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'sources/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getSource($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetCandidateTags()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'candidate_tags',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();

        $this->harvestService->getCandidateTags($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostCandidateTags()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidate_tags',
            'body' => '{"name":"Test Tag"}',
            'parameters' => array(),
            'headers' => array()
        );
        $params = array(
            'body' => '{"name":"Test Tag"}',
        );

        $this->harvestService->postCandidateTags($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetUsersNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'users',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
            
        $this->harvestService->getUsers($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetUsersPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'users',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getUsers($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testGetUser()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'users/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getUsers($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
    
    public function testPostDeactivateUser()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'users/12345/deactivate',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $params = array('id' => 12345);

        $this->harvestService->postDeactivateUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());    
    }
    
    public function testPostDeactivateUserBulk()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'users/deactivate/bulk',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $params = array('bulk' => true);

        $this->harvestService->postDeactivateUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostActivateUser()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'users/12345/activate',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $params = array('id' => 12345);

        $this->harvestService->postActivateUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());    
    }
    
    public function testPostActivateUserBulk()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'users/activate/bulk',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $params = array('bulk' => true);

        $this->harvestService->postActivateUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostRevokePermissionsFromUser()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'users/12345/revoke_permissions',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $params = array('id' => 12345);

        $this->harvestService->postRevokePermissionForUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostRevokePermissionForUserBulk()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'users/revoke_permissions/bulk',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $params = array('bulk' => true);

        $this->harvestService->postRevokePermissionForUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostUser()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'users',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}'
        );

        $this->harvestService->postUsers($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
        
    public function testGetUserRoles()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'user_roles',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();

        $this->harvestService->getUserRoles($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetApplicationStages()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'application_stages',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getApplicationStages(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetAppliedCandidateTags()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applied_candidate_tags',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getAppliedCandidateTags(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteAppliedCandidateTag()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'applied_candidate_tags/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteAppliedCandidateTags(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostAppliedCandidateTag()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'applied_candidate_tags',
            'headers' => array(),
            'body' => '{"candidate_id":1,"tag_id":2}',
            'parameters' => array()
        );
        $this->harvestService->postAppliedCandidateTags(array('body' => '{"candidate_id":1,"tag_id":2}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetApprovalFlows()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'approval_flows',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getApprovalFlows(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchApprovalFlow()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'approval_flows/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchApprovalFlows(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostApprovalFlow()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'approval_flows',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postApprovalFlows(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetApproverGroups()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'approver_groups',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getApproverGroups(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostRequestApprovalForApprovalFlows()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'approval_flows/12345/request_approvals',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->postRequestApprovalForApprovalFlows(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPutReplaceApproverGroupsForApprovalFlow()
    {
        $expected = array(
            'method' => 'put',
            'url' => 'approval_flows/12345/replace_approver_groups',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->putReplaceApproverGroupsForApprovalFlow(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPutReplaceApproverForApproverGroup()
    {
        $expected = array(
            'method' => 'put',
            'url' => 'approver_groups/12345/replace_approver',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->putReplaceApproverForApproverGroup(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetApprovers()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'approvers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getApprovers(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetAttachments()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'attachments',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getAttachments(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostAttachments()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'attachments',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postAttachments(array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"body":"json"}'
        ));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteAttachment()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'attachments/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteAttachments(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetBlockedSpamSources()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'blocked_spam_sources',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getBlockedSpamSources(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostBlockedSpamSource()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'blocked_spam_sources',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postBlockedSpamSources(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostBlockedSpamSourcesBulk()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'blocked_spam_sources/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postBlockedSpamSources(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchBlockedSpamSource()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'blocked_spam_sources/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchBlockedSpamSources(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchBlockedSpamSourcesBulk()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'blocked_spam_sources/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchBlockedSpamSources(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteBlockedSpamSource()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'blocked_spam_sources/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteBlockedSpamSources(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteBlockedSpamSourcesBulk()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'blocked_spam_sources/bulk',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteBlockedSpamSources(array('bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetBulkRequests()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'bulk_requests',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getBulkRequests(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetBulkRequestByUuid()
    {
        $uuid = 'abc-def-123';
        $expected = array(
            'method' => 'get',
            'url' => 'bulk_requests/' . $uuid,
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getBulkRequests(array('id' => $uuid));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetCandidateAttributeTypes()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'candidate_attribute_types',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getCandidateAttributeTypes(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetCandidateEducations()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'candidate_educations',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getCandidateEducations(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostCandidateEducation()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidate_educations',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postCandidateEducations(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteCandidateEducation()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'candidate_educations/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteCandidateEducations(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetCandidateEmployments()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'candidate_employments',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getCandidateEmployments(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostCandidateEmployment()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidate_employments',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postCandidateEmployments(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteCandidateEmployment()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'candidate_employments/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteCandidateEmployments(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteCandidateTag()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'candidate_tags/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteCandidateTags(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteCandidate()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'candidates/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteCandidates(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchCandidates()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'candidates/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchCandidates(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostCandidates()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidates',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postCandidates(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetCloseReasons()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'close_reasons',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getCloseReasons(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetCustomFieldDepartments()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'custom_field_departments',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getCustomFieldDepartments(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostCustomFieldDepartment()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'custom_field_departments',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postCustomFieldDepartments(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteCustomFieldDepartment()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'custom_field_departments/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteCustomFieldDepartments(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetCustomFieldOffices()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'custom_field_offices',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getCustomFieldOffices(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostCustomFieldOffice()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'custom_field_offices',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postCustomFieldOffices(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteCustomFieldOffice()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'custom_field_offices/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteCustomFieldOffices(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetCustomFieldOptions()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'custom_field_options',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getCustomFieldOptions(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostCustomFieldOption()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'custom_field_options',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postCustomFieldOptions(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostCustomFieldOptionsBulk()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'custom_field_options/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postCustomFieldOptions(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchCustomFieldOption()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'custom_field_options/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchCustomFieldOptions(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchCustomFieldOptionsBulk()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'custom_field_options/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchCustomFieldOptions(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteCustomFieldOption()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'custom_field_options/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteCustomFieldOptions(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteCustomFieldOptionBulk()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'custom_field_options/bulk',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteCustomFieldOptions(array('bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchCustomField()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'custom_fields/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchCustomFields(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostCustomField()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'custom_fields',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postCustomFields(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteCustomField()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'custom_fields/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteCustomFields(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetDefaultInterviewers()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'default_interviewers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getDefaultInterviewers(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetDemographicAnswerOptions()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographic_answer_options',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getDemographicAnswerOptions(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetDemographicAnswers()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographic_answers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getDemographicAnswers(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetDemographicQuestionSets()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographic_question_sets',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getDemographicQuestionSets(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetDemographicQuestions()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographic_questions',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getDemographicQuestions(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchDepartments()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'departments/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchDepartments(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchDepartmentsBulk()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'departments/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchDepartments(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetFocusCandidateAttributes()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'focus_candidate_attributes',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getFocusCandidateAttributes(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetFutureJobPermissions()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'future_job_permissions',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getFutureJobPermissions(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostFutureJobPermissions()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'future_job_permissions',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postFutureJobPermissions(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteFutureJobPermission()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'future_job_permissions/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteFutureJobPermissions(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetInterviewKits()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'interview_kits',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getInterviewKits(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetInterviewerTags()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'interviewer_tags',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getInterviewerTags(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetInterviewers()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'interviewers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getInterviewers(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetInterviews()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'interviews',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getInterviews(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetInterviewById()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'interviews/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getInterviews(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostInterviews()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'interviews',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postInterviews(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchInterviews()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'interviews/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchInterviews(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteInterviews()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'interviews/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteInterviews(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobBoardCustomLocations()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'job_board_custom_locations',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getJobBoardCustomLocations(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobCandidateAttributes()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'job_candidate_attributes',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getJobCandidateAttributes(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobHiringManagers()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'job_hiring_managers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getJobHiringManagers(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostJobHiringManagers()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'job_hiring_managers',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postJobHiringManagers(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteJobHiringManagers()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'job_hiring_managers/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteJobHiringManagers(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobInterviewStages()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'job_interview_stages',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getJobInterviewStages(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobInterviews()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'job_interviews',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getJobInterviews(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobNotes()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'job_notes',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getJobNotes(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostJobNotes()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'job_notes',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postJobNotes(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchJobNotes()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'job_notes/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchJobNotes(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteJobNotes()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'job_notes/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteJobNotes(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobOwners()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'job_owners',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getJobOwners(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostJobOwners()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'job_owners',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postJobOwners(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteJobOwners()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'job_owners/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteJobOwners(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetJobPostLocations()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'job_post_locations',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getJobPostLocations(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostJobPostLocations()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'job_post_locations',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postJobPostLocations(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteJobPostLocations()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'job_post_locations/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteJobPostLocations(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostJobPosts()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'job_posts',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postJobPosts(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostJobs()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'jobs',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postJobs(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostJobsBulk()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'jobs/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postJobs(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetNotes()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'notes',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getNotes(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostNotes()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'notes',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postNotes(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostOffers()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'offers',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postOffers(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchOffers()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'offers/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchOffers(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchOffices()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'offices/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchOffices(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchOfficesBulk()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'offices/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchOffices(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetOpenings()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'openings',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getOpenings(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostOpenings()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'openings',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postOpenings(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostOpeningsBulk()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'openings/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postOpenings(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchOpenings()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'openings/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchOpenings(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchOpeningsBulk()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'openings/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchOpenings(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteOpenings()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'openings/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteOpenings(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteOpeningsBulk()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'openings/bulk',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteOpenings(array('bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetPayInputRanges()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'pay_input_ranges',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getPayInputRanges(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetPayInputs()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'pay_inputs',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getPayInputs(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetProspectDetails()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'prospect_details',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getProspectDetails(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetProspectPoolStages()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'prospect_pool_stages',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getProspectPoolStages(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetProspectPools()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'prospect_pools',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getProspectPools(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetReferrers()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'referrers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getReferrers(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetRejectionDetails()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'rejection_details',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getRejectionDetails(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchRejectionDetails()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'rejection_details/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchRejectionDetails(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetRejectionReasonById()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'rejection_reasons/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getRejectionReasons(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetScorecardCandidateAttributes()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scorecard_candidate_attributes',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getScorecardCandidateAttributes(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostScorecardCandidateAttributes()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'scorecard_candidate_attributes',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postScorecardCandidateAttributes(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchScorecardCandidateAttributes()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'scorecard_candidate_attributes/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchScorecardCandidateAttributes(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetScorecardQuestionAnswerOptions()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scorecard_question_answer_options',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getScorecardQuestionAnswerOptions(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostScorecardQuestionAnswerOptions()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'scorecard_question_answer_options',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postScorecardQuestionAnswerOptions(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetScorecardQuestionAnswers()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scorecard_question_answers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getScorecardQuestionAnswers(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostScorecardQuestionAnswers()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'scorecard_question_answers',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postScorecardQuestionAnswers(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchScorecardQuestionAnswers()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'scorecard_question_answers/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchScorecardQuestionAnswers(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetScorecardQuestionCandidateAttributes()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scorecard_question_candidate_attributes',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getScorecardQuestionCandidateAttributes(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetScorecardQuestionOptions()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scorecard_question_options',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getScorecardQuestionOptions(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetScorecardQuestions()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'scorecard_questions',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getScorecardQuestions(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostScorecards()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'scorecards',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postScorecards(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchScorecards()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'scorecards/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchScorecards(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetTrackingLinks()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'tracking_links',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getTrackingLinks(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetUserEmails()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'user_emails',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getUserEmails(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostUserEmails()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'user_emails',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postUserEmails(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetUserJobPermissions()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'user_job_permissions',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getUserJobPermissions(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostUserJobPermissions()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'user_job_permissions',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postUserJobPermissions(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteUserJobPermissions()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'user_job_permissions/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteUserJobPermissions(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostUsers()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'users',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postUsers(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostUsersBulk()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'users/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postUsers(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchUsers()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'users/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchUsers(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchUsersBulk()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'users/bulk',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchUsers(array('body' => '{"body":"json"}', 'bulk' => true));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testGetWebhooks()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'webhooks',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->getWebhooks(array());
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostWebhooks()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'webhooks',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postWebhooks(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchWebhooks()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'webhooks/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchWebhooks(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteWebhooks()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'webhooks/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteWebhooks(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testDeleteApplications()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'applications/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $this->harvestService->deleteApplications(array('id' => 12345));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPatchApplications()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'applications/12345',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->patchApplications(array('id' => 12345, 'body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }

    public function testPostApplications()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'applications',
            'headers' => array(),
            'body' => '{"body":"json"}',
            'parameters' => array()
        );
        $this->harvestService->postApplications(array('body' => '{"body":"json"}'));
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->authHeader());
    }
}