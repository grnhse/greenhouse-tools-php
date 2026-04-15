<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Tools;

use Greenhouse\GreenhouseToolsPhp\Tools\HarvestHelper;

/**
 * This test is not exhaustive and should be added to as more uses for JsonHelper come up.
 */
class HarvestHelperTest extends \PHPUnit\Framework\TestCase
{
    private string $json;
    private HarvestHelper $parser;
    private array $parameters;

    public function setUp(): void
    {
        $root = realpath(dirname(__FILE__));
        $this->json = file_get_contents("$root/../files/test_json/single_job_response.json");
        $this->parser = new HarvestHelper();
        $this->parameters = array('per_page' => 10, 'page' => 2);
    }
    
    public function testParseGetSingleWordMethodNoId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applications',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $this->assertEquals($expected, $this->parser->parse('getApplications', $this->parameters));
    }
    
    public function testParsePostSingleWordMethodNoId()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidates',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $this->assertEquals($expected, $this->parser->parse('postCandidate', $this->parameters));
    }

    public function testParsePostSingleWordMethodBulk()
    {
        $expected = array(
            'method' => 'post',
            'url' => 'candidates/bulk',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $this->assertEquals($expected, $this->parser->parse('postCandidate', array_merge($this->parameters, ['bulk' => true])));
    }

    public function testParseDeleteSingleWordMethodBulk()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'applications/bulk',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );

        $this->assertEquals($expected, $this->parser->parse('deleteApplications', ['bulk' => true]));
    }

    public function testParsePatchSingleWordMethodBulk()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'applications/bulk',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );

        $this->assertEquals($expected, $this->parser->parse('patchApplications', ['bulk' => true]));
    }
    
    public function testParseGetSingleWordMethodWithId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applications/12345',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $params = array_merge($this->parameters, array('id' => 12345));
        $this->assertEquals($expected, $this->parser->parse('getApplications', $params));
    }
    
    public function testParseGetDoubleWordMethodNoId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'email_templates',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $this->assertEquals($expected, $this->parser->parse('getEmailTemplates', $this->parameters));
    }
    
    public function testParseGetDoubleWordMethodWithId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'email_templates/12345',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $this->assertEquals($expected, $this->parser->parse('getEmailTemplates', array('id' => 12345)));
    }
    
    public function testParseWithDeleteMethod()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'applications/12345',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $this->assertEquals($expected, $this->parser->parse('deleteApplication', array('id' => 12345)));
    }

    public function testParseDeleteMethodWithForBulk()
    {
        $expected = array(
            'method' => 'patch',
            'url' => 'approval_flows/request_approvals/bulk',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $this->assertEquals($expected, $this->parser->parse('patchRequestApprovalForApprovalFlows', array('bulk' => true)));
    }

    public function testParseDeleteMethodWithoutForBulk()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'approval_flows/12345/request_approvals',
            'parameters' => array(),
            'headers' => array(),
            'body' => null
        );
        $this->assertEquals($expected, $this->parser->parse('deleteRequestApprovalForApprovalFlows', array('id' => 12345)));
    }
    
    public function testParseGetSingleWordMethodWithForWithId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'applications/12345/scorecards',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $params = array_merge($this->parameters, array('id' => 12345));
        $this->assertEquals($expected, $this->parser->parse('getScorecardsForApplication', $params));
    }

    public function testParseGetDoubleWordMethodWithForWithId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'activity_feeds/12345/email_templates',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $params = array_merge($this->parameters, array('id' => 12345));
        $this->assertEquals($expected, $this->parser->parse('getEmailTemplatesForActivityFeeds', $params));
    }
    
    public function testParseGetDoubleWordMethodWithForWithIdPluralized()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'activity_feeds/12345/email_templates',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $params = array_merge($this->parameters, array('id' => 12345));
        $this->assertEquals($expected, $this->parser->parse('getEmailTemplateForActivityFeed', $params));
    }

    public function testBadHttpMethodFails()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException');
        $this->parser->parse('testCandidates', array());
    }

    public function testForMethodWithoutIdOrBulkThrowsException()
    {
        // A "For" method with no 'id' and no 'bulk' must throw — the URL cannot be built.
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException');
        $this->expectExceptionMessage('ID or bulk parameter is required for method calls with \'For\' in them.');
        $this->parser->parse('getScorecardsForApplication', []);
    }

    public function testForMethodWithStringBulkThrowsException()
    {
        // 'bulk' => 'true' (string) no longer satisfies === true, so the URL is never
        // set and the new exception must fire.
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException');
        $this->expectExceptionMessage('ID or bulk parameter is required for method calls with \'For\' in them.');
        $this->parser->parse('patchRequestApprovalForApprovalFlows', ['bulk' => 'true']);
    }

    public function testSingleMethodWithStringBulkDoesNotAddBulkSuffix()
    {
        // 'bulk' => 'true' (string) must not append /bulk on a single-object method.
        $expected = array(
            'method' => 'post',
            'url' => 'candidates',
            'parameters' => [],
            'headers' => array(),
            'body' => null
        );
        $this->assertEquals($expected, $this->parser->parse('postCandidate', ['bulk' => 'true']));
    }

    public function testAddQueryString()
    {
        $expected = 'candidate/12345/person?per_page=10&page=2';
        $this->assertEquals($expected, $this->parser->addQueryString('candidate/12345/person', $this->parameters));
    }
    
    public function testAddQueryStringWithNoParameters()
    {
        $expected = 'candidate/12345/person';
        $this->assertEquals($expected, $this->parser->addQueryString('candidate/12345/person'));
    }
}
