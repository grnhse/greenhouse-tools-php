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
    
    public function testParseGetDoubleWordMethodWithSecondId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'activity_feeds/12345/email_templates/2345',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $params = array_merge($this->parameters, array('id' => 12345, 'second_id' => 2345));
        $this->assertEquals($expected, $this->parser->parse('getEmailTemplateForActivityFeed', $params));
    }
    
    public function testParseGetDoubleWordMethodWithForNoId()
    {
        $expected = array(
            'method' => 'get',
            'url' => 'activity_feeds/email_templates',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $params = array_merge($this->parameters, array());
        $this->assertEquals($expected, $this->parser->parse('getEmailTemplateForActivityFeed', $params));
    }

    public function testParseTripleWordMethod()
    {
        $expected = array(
            'method' => 'delete',
            'url' => 'users/12345/permissions/jobs',
            'parameters' => $this->parameters,
            'headers' => array(),
            'body' => null
        );
        $params = array_merge($this->parameters, array('id' => 12345));
        $this->assertEquals($expected, $this->parser->parse('deletePermissionForJobForUser', $params));
    }
    
    public function testParseTripleWordMethodRequiresId()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException');
        $this->parser->parse('deletePermissionForJobForUser', array());
    }

    public function testBadHttpMethodFails()
    {
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseServiceException');
        $this->parser->parse('testCandidates', array());
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
