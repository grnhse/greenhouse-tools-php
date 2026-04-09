<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Services;

use Greenhouse\GreenhouseToolsPhp\Services\HarvestService;
use Greenhouse\GreenhouseToolsPhp\GreenhouseService;

/**
 * This test only tests that the service requests generate the expected links and arrays.  This does not
 * test the response from harvest and, in most cases, that the responses are valid.  Harvest is expected
 * to reject invalid requests.
 */
class HarvestServiceTest extends \PHPUnit\Framework\TestCase
{
    private HarvestService $harvestService;
    private string $expectedAuth;

    public function setUp(): void
    {
        $this->harvestService = new HarvestService('greenhouse');
        $apiStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $apiStub->method('getNextLink')->willReturn('http://example.com/next');
        $apiStub->method('getPrevLink')->willReturn('http://example.com/prev');
        $apiStub->method('getLastLink')->willReturn('http://example.com/last');

        $this->harvestService->setClient($apiStub);
        $this->expectedAuth = 'Basic Z3JlZW5ob3VzZTo=';
    }
    
    public function testGetNextLink()
    {
        $this->assertEquals($this->harvestService->nextLink(), 'http://example.com/next');
    }
    
    public function testGetPrevLink()
    {
        $this->assertEquals($this->harvestService->prevLink(), 'http://example.com/prev');
    }
    
    public function testGetLastLink()
    {
        $this->assertEquals($this->harvestService->lastLink(), 'http://example.com/last');
    }
    
    public function testGetActivityFeed()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'candidates/12345/activity_feed',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);
            
        $this->harvestService->getActivityFeedForCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());    
    }
    
    public function testPostAdvanceApplication()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'applications/12345/advance',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"from_stage_id": 345}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"from_stage_id": 345}',
            'id' => 12345
        );

        $this->harvestService->postAdvanceApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPostTransferApplicationToJob()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'applications/12345/transfer_to_job',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"new_job_id": 345}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"new_job_id": 345}',
            'id' => 12345
        );

        $this->harvestService->postTransferApplicationToJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());    
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPostNote()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidates/12345/activity_feed/notes',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_json": "is_here"}',
            'id' => 12345
        );

        $this->harvestService->postNoteForCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPutAnonymize()
    {
        $expected = array(
            'method' => 'put',
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

        $this->harvestService->putAnonymizeCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPutMergeCandidate()
    {
        $expected = array(
            'method' => 'put',
            'url' => 'candidates/merge',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"primary_candidate_id":123,"duplicate_candidate_id":234}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"primary_candidate_id":123,"duplicate_candidate_id":234}'
        );
        
        $this->harvestService->putMergeCandidates($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testGetCustomFieldsNoPaging()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'custom_fields/',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();
        
        $this->harvestService->getCustomFields($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testGetCustomFieldsPaging()
    {
        $params = array('page' => 2, 'per_page' => 100);
        $expected = array(
            'method' => 'get',
            'url' => 'custom_fields/',
            'headers' => array(),
            'body' => null,
            'parameters' => $params
        );
            
        $this->harvestService->getCustomFields($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());    
    }
    
    public function testGetCustomFieldWithType()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'custom_fields/job',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 'job');

        $this->harvestService->getCustomFields($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testGetCustomField()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'custom_field/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => '12345');

        $this->harvestService->getCustomField($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testGetCustomFieldOptionsForCustomField()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'custom_field/12345/custom_field_options',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => '12345');

        $this->harvestService->getCustomFieldOptionsForCustomField($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPostCustomFieldOptionsForCustomField()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'custom_field/12345/custom_field_options',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->postCustomFieldOptionsForCustomField($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testDeleteCustomFieldOptionsForCustomField()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'custom_field/12345/custom_field_options',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->deleteCustomFieldOptionsForCustomField($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPatchCustomFieldOptionsForCustomField()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'custom_field/12345/custom_field_options',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->patchCustomFieldOptionsForCustomField($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());    
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());    
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());    
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());    
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testGetJobPostsForJob()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'jobs/12345/job_post',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getJobPostsForJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testGetJobStagesForJob()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'jobs/12345/stages',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getJobStagesForJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPostJob()
    {
        $expected = array(
            'method' => 'post',
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

        $this->harvestService->postJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPutHiringTeamForJob()
    {
        $expected = array(
            'method' => 'put',
            'url' => 'jobs/12345/hiring_team',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->putHiringTeamForJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testGetCurrentOfferForApplication()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applications/12345/offers/current_offer',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getCurrentOfferForApplication($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());  
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testGetCandidateTags()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'tags/candidate',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();

        $this->harvestService->getCandidateTags($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPostCandidateTags()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'tags/candidate',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"name":"Test Tag"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"name":"Test Tag"}',
        );

        $this->harvestService->postCandidateTags($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetTagsForCandidate()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'candidates/12345/tags',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getTagsForCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testDeleteTagsForCandidate()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'candidates/12345/tags/2345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345, 'second_id' => 2345);

        $this->harvestService->deleteTagsForCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPutTagsForCandidate()
    {
        $expected = array(
            'method' => 'put',
            'url' => 'candidates/12345/tags/2345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345, 'second_id' => 2345);

        $this->harvestService->putTagsForCandidate($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPatchDisableUser()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'users/12345/disable',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->patchDisableUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());    
    }
    
    public function testPatchEnableUser()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'users/12345/enable',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->patchEnableUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());    
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testGetPermissionForJobForUser()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'users/12345/permissions/jobs',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getPermissionForJobForUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testDeletePermissionForJobForUser()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'users/12345/permissions/jobs',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->deletePermissionForJobForUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPutPermissionForJobForUser()
    {
        $expected = array(
            'method' => 'put',
            'url' => 'users/12345/permissions/jobs',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->putPermissionForJobForUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetPermissionForFutureJobForUser()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'users/12345/permissions/future_jobs',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getPermissionForFutureJobForUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testDeletePermissionForFutureJobForUser()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'users/12345/permissions/future_jobs',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->deletePermissionForFutureJobForUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
    
    public function testPutPermissionForFutureJobForUser()
    {
        $expected = array(
            'method' => 'put',
            'url' => 'users/12345/permissions/future_jobs',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->putPermissionForFutureJobForUser($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
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
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetHiringTeamForJob()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'jobs/12345/hiring_team',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getHiringTeamForJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testPostHiringTeamForJob()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'jobs/12345/hiring_team',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->postHiringTeamForJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testDeleteHiringTeamForJob()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'jobs/12345/hiring_team',
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'parameters' => array()
        );
        $params = array(
            'headers' => array('On-Behalf-Of' => 234),
            'body' => '{"update_body":"json"}',
            'id' => 12345
        );

        $this->harvestService->deleteHiringTeamForJob($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetQuestionSetsForDemographics()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/question_sets',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();

        $this->harvestService->getQuestionSetsForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetQuestionSetsForDemographicsWithId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/question_sets/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getQuestionSetsForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetQuestionsForDemographics()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/questions',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();

        $this->harvestService->getQuestionsForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetQuestionsForDemographicsWithId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/questions/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getQuestionsForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetQuestionsForQuestionSetsForDemographic()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/question_sets/12345/questions',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getQuestionsForQuestionSetsForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetAnswerOptionsForDemographics()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/answer_options',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();

        $this->harvestService->getAnswerOptionsForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetAnswerOptionsForDemographicsWithId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/answer_options/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getAnswerOptionsForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetAnswerOptionsForQuestionsForDemographicsWithId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/questions/12345/answer_options',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getAnswerOptionsForQuestionsForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetAnswersForDemographics()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/answers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array();

        $this->harvestService->getAnswersForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetAnswersForDemographicsWithId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'demographics/answers/12345',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getAnswersForDemographics($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }

    public function testGetDemographicAnswersForApplication()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applications/12345/demographics/answers',
            'headers' => array(),
            'body' => null,
            'parameters' => array()
        );
        $params = array('id' => 12345);

        $this->harvestService->getDemographicAnswersForApplications($params);
        $this->assertEquals($expected, $this->harvestService->getHarvest());
        $this->assertEquals($this->expectedAuth, $this->harvestService->getAuthorizationHeader());
    }
}