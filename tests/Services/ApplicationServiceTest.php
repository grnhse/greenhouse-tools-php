<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Services;

use Greenhouse\GreenhouseToolsPhp\Services\ApplicationService;
use Greenhouse\GreenhouseToolsPhp\Services\JobApiService;

class ApplicationServiceTest extends \PHPUnit\Framework\TestCase
{
    private ApplicationService $appService;

    public function setUp(): void
    {
        $this->appService = new ApplicationService('test_api_key', 'greenhouse');
    }
    
    public function getTestJobJson()
    {
        $root = realpath(dirname(__FILE__));
        return file_get_contents("$root/../files/test_json/single_job_response.json");
    }
    
    public function getTestJobJsonWithMultiselect()
    {
        $root = realpath(dirname(__FILE__));
        return file_get_contents("$root/../files/test_json/single_job_response_with_multiselect_required.json");
    }
    
    public function getTestJobJsonNothingRequired()
    {
        $root = realpath(dirname(__FILE__));
        return file_get_contents("$root/../files/test_json/single_job_response_no_required_fields.json");
    }
    
    /**
     * This is not a real test, but a programmatic way to test submissions. Commented out because
     * it's externally dependent.
    public function testPost()
    {
        $greenhouse = new \Greenhouse\GreenhouseToolsPhp\GreenhouseService(array(
            'apiKey' => 'key',
            'boardToken' => 'dungeons'
        ));
        
        $appService = $greenhouse->getApplicationApiService();
        
        $resume = realpath(dirname(__FILE__)) . '/../files/documents/test_resume.docx';
        $cover = realpath(dirname(__FILE__)) . '/../files/documents/test_cover_letter.docx';
        
        $postVars = array(
            'id' => 141993,
            'first_name' => 'Thomas',
            'last_name' => 'Tester',
            'email' => 'thomas.tester@example.com',
            'phone' => '1-555-555-5555',
            'resume' => new \CURLFile($resume, null, 'my_api_resume.docx'),
            'cover_letter' => new \CURLFile($cover, null, 'my_api_cover_letter.docx'),
            'question_884945' => 'https://www.linkedin.com',
            'question_884947' => 'The internet',
            'question_1115883' => array(542885, 542886)
        );
        
        $appService->postApplication($postVars);
    }
    **/
    
    public function testValidateRequiredFieldsPass()
    {
        $apiStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Services\JobApiService');
        $apiStub->method('getJob')->willReturn($this->getTestJobJson());
        $this->appService->setJobApiService($apiStub);
        
        $postVars = array(
            'id' => '12345',
            'first_name' => 'Hiram',
            'last_name' => 'Abiff',
            'email' => 'widowson@example.com',
            'resume_text' => 'Builder',
            'cover_letter_text' => 'I built things',
            'question_1042159' => 'stuff'
        );
        
        $this->assertTrue($this->appService->validateRequiredFields($postVars));
    }
    
    public function testValidateRequiredFieldsWithMultiselect()
    {
        $apiStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Services\JobApiService');
        $apiStub->method('getJob')->willReturn($this->getTestJobJsonWithMultiselect());
        $this->appService->setJobApiService($apiStub);
        
        $postVars = array(
            'id' => '12345',
            'first_name' => 'Hiram',
            'last_name' => 'Abiff',
            'email' => 'widowson@example.com',
            'resume_text' => 'Builder',
            'cover_letter_text' => 'I built things',
            'question_1042159' => 'stuff',
            'question_579460' => array(225451, 225452)
        );
        
        $this->assertTrue($this->appService->validateRequiredFields($postVars));
    }
    
    /**
     * Backward compatable version.  This is how we checked required fields originally (with the square
     * brackets, as returned by the API, but not how we required the request to be submitted.
     */
    public function testValidateRequiredFieldsWithMultiselectLegacy()
    {
        $apiStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Services\JobApiService');
        $apiStub->method('getJob')->willReturn($this->getTestJobJsonWithMultiselect());
        $this->appService->setJobApiService($apiStub);
        
        $postVars = array(
            'id' => '12345',
            'first_name' => 'Hiram',
            'last_name' => 'Abiff',
            'email' => 'widowson@example.com',
            'resume_text' => 'Builder',
            'cover_letter_text' => 'I built things',
            'question_1042159' => 'stuff',
            'question_579460[]' => array(225451, 225452)
        );
        
        $this->assertTrue($this->appService->validateRequiredFields($postVars));
    }
    
    public function testValidateRequiredFieldsFailSingle()
    {
        $apiStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Services\JobApiService');
        $apiStub->method('getJob')->willReturn($this->getTestJobJson());
        $this->appService->setJobApiService($apiStub);
        
        $postVars = array(
            'id' => '12345',
            'first_name' => 'Hiram',
            'last_name' => 'Abiff',
            'resume_text' => 'Builder',
            'cover_letter_text' => 'I built things',
            'question_1042159' => 'stuff'
        );
        
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseApplicationException');
        $this->expectExceptionMessage('Submission missing required answers for: Email');
        $this->appService->validateRequiredFields($postVars);
    }
    
    public function testValidateRequiredFieldsFailMultiple()
    {
        $apiStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Services\JobApiService');
        $apiStub->method('getJob')->willReturn($this->getTestJobJson());
        $this->appService->setJobApiService($apiStub);
        
        $postVars = array(
            'id' => '12345',
            'last_name' => 'Abiff',
            'resume_text' => 'Builder',
            'cover_letter_text' => 'I built things',
            'question_1042159' => 'stuff'
        );
        
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Services\Exceptions\GreenhouseApplicationException');
        $this->expectExceptionMessage('Submission missing required answers for: First Name, Email');
        $this->appService->validateRequiredFields($postVars);
    }
        
    public function testHasRequredValueSingleHappy()
    {
        $postVars = array('foo' => 'something', 'bar' => 'something else');
        $keys = array('foo');
        
        $this->assertTrue($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasRequiredValueSingleKeyNotFound()
    {
        $postVars = array('foo' => 'something', 'bar' => 'something else');
        $keys = array('baz');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasRequiredValueSingleKeyEmpty()
    {
        $postVars = array('foo' => '', 'bar' => 'something else');
        $keys = array('foo');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasRequiredValueSingleKeyZeroTrue()
    {
        $postVars = array('foo' => 0, 'bar' => 'something else');
        $keys = array('foo');
        
        $this->assertTrue($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasRequiredValueMultipleHappy()
    {
        $postVars = array('foo' => 'something', 'bar' => 'something else');
        $keys = array('foo', 'bar');
        
        $this->assertTrue($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasRequiredValueMultipleFirstIndex()
    {
        $postVars = array('foo' => 'something', 'bar' => 'something else');
        $keys = array('foo', 'baz');
        
        $this->assertTrue($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasRequiredValueMultipleSecondIndex()
    {
        $postVars = array('foo' => 'something', 'bar' => 'something else');
        $keys = array('baz', 'bar');
        
        $this->assertTrue($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasRequiredValueMultipleKeyNotFound()
    {
        $postVars = array('foo' => 'something', 'bar' => 'something else');
        $keys = array('oof', 'baz');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }

    public function testHasRequiredValueMultipleEmpty()
    {
        $postVars = array('foo' => 'something', 'bar' => 'something else', 'baz' => '');
        $keys = array('oof', 'baz');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasRequiredValueMultipleZero()
    {
        $postVars = array('foo' => 0, 'bar' => 'something else');
        $keys = array('foo', 'baz');
        
        $this->assertTrue($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasBinaryFilenameFieldPass()
    {
        $postVars = array('foo' => '', 'foo_content' => 'abce', 'foo_content_filename' => 'test.pdf');
        $keys = array('foo');
        
        $this->assertTrue($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasBinaryFilenameFieldFailContentBlank()
    {
        $postVars = array('foo' => '', 'foo_content' => '', 'foo_content_filename' => 'test.pdf');
        $keys = array('foo');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }

    public function testHasBinaryFilenameFieldFailContentNotIncluded()
    {
        $postVars = array('foo' => '', 'foo_content_filename' => 'test.pdf');
        $keys = array('foo');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }

    public function testHasBinaryFilenameFieldFailFilenameBlank()
    {
        $postVars = array('foo' => '', 'foo_content' => 'abce', 'foo_content_filename' => '');
        $keys = array('foo');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }

    public function testHasBinaryFilenameFieldFailFilenameNotIncluded()
    {
        $postVars = array('foo' => '', 'foo_content' => 'abcde');
        $keys = array('foo');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasUrlFilenameFieldPass()
    {
        $postVars = array('foo' => '', 'foo_url' => 'http://t.co', 'foo_url_filename' => 'test.pdf');
        $keys = array('foo');
        
        $this->assertTrue($this->appService->hasRequiredValue($postVars, $keys));
    }
    
    public function testHasUrlFilenameFieldFailUrlBlank()
    {
        $postVars = array('foo' => '', 'foo_url' => '', 'foo_url_filename' => 'test.pdf');
        $keys = array('foo');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }

    public function testHasUrlFilenameFieldFailUrlFilenameBlank()
    {
        $postVars = array('foo' => '', 'foo_url' => 'http://t.co', 'foo_url_filename' => '');
        $keys = array('foo');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }

    public function testHasUrlFilenameFieldFailUrlNotIncluded()
    {
        $postVars = array('foo' => '', 'foo_url_filename' => 'test.pdf');
        $keys = array('foo');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }

    public function testHasUrlFilenameFieldFailUrlFilenameNotIncluded()
    {
        $postVars = array('foo' => '', 'foo_url' => 'http://t.co');
        $keys = array('foo');
        
        $this->assertFalse($this->appService->hasRequiredValue($postVars, $keys));
    }

    public function testGetRequiredFields()
    {
        $apiStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Services\JobApiService');
        $apiStub->method('getJob')->willReturn($this->getTestJobJson());
        $this->appService->setJobApiService($apiStub);
        
        $expected = array(
            'First Name' => array('first_name'),
            'Last Name' => array('last_name'),
            'Email' => array('email'),
            'Resume' => array('resume', 'resume_text'),
            'Cover Letter' => array('cover_letter', 'cover_letter_text'),
            'LinkedIn Profile' => array('question_1042159')
        );
        
        $this->assertEquals($expected, $this->appService->getRequiredFields(0));
    }
    
    public function testGetRequiredFieldsNoRequiredFields()
    {
        $apiStub = $this->createStub('\Greenhouse\GreenhouseToolsPhp\Services\JobApiService');
        $apiStub->method('getJob')->willReturn($this->getTestJobJsonNothingRequired());
        $this->appService->setJobApiService($apiStub);
        
        $this->assertEquals(array(), $this->appService->getRequiredFields(0));
    }
}
    
    
