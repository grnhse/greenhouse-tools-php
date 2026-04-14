<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Clients;

use Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient;
use Greenhouse\GreenhouseToolsPhp\Tests\Clients\Mocks\MockGuzzleResponse;

class GuzzleClientTest extends \PHPUnit\Framework\TestCase
{
    private GuzzleClient $client;
    private string $resumePath;

    public function setUp(): void
    {
        $this->client = new GuzzleClient(array('base_uri' => 'http://www.example.com'));
        $this->resumePath = realpath(dirname(__FILE__)) . '/../files/documents/test_resume.docx';
    }

    public function testGuzzleInitialize()
    {
        $client = new GuzzleClient(array('base_uri' => 'http://www.example.com'));
        $this->assertInstanceOf(
            '\Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient',
            $client
        );

        $this->assertInstanceOf('\GuzzleHttp\Client', $client->getClient());
    }

    public function testGetException()
    {
        $errorUrl = 'https://boards-api.greenhouse.io/v1/boards/exception_co/embed/';
        $client = new GuzzleClient(array('base_uri' => $errorUrl));
        $this->expectException('\Greenhouse\GreenhouseToolsPhp\Clients\Exceptions\GreenhouseAPIResponseException');
        $response = $client->get('jobs');
    }

    public function testFormatPostParametersNoFiles()
    {
        $postVars = array(
            'first_name' => 'Hiram',
            'last_name' => 'Abiff',
            'talents' => array('building', 'things', 'and', 'stuff')
        );
        $expected = array(
            ['name' => 'first_name', 'contents' => 'Hiram'],
            ['name' => 'last_name', 'contents' => 'Abiff'],
            ['name' => 'talents[]', 'contents' => 'building'],
            ['name' => 'talents[]', 'contents' => 'things'],
            ['name' => 'talents[]', 'contents' => 'and'],
            ['name' => 'talents[]', 'contents' => 'stuff']
        );

        $this->assertEquals($expected, $this->client->formatPostParameters($postVars));
    }

    public function testFormatPostParametersWithFiles()
    {
        $testDoc = new \CURLFile($this->resumePath, 'application/msword', 'resume');

        $postVars = array(
          'first_name' => 'Hiram',
          'last_name' => 'Abiff',
          'talents' => array('building', 'things', 'and', 'stuff'),
          'resume' =>  $testDoc,
          'demographic_answers' => [
            [
              'question_id' => '1',
              'answer_options' => [
                ['answer_option_id' => '100'],
                ['answer_option_id' => '101']
              ]
            ]
          ],
          'educations' => [
            [
              "school_name_id"=>5417077,
              "degree_id" => 5494452,
              "discipline_id" => 5494865,
              "start_date" => [
                "month" => 8,
                "year" => 2012
              ],
              "end_date" => [
                "month" => 5,
                "year" => 2016
              ]
            ]
          ]
        );

        $response = $this->client->formatPostParameters($postVars);

        $this->assertEquals($response[0], ['name' => 'first_name', 'contents' => 'Hiram']);
        $this->assertEquals($response[1], ['name' => 'last_name', 'contents' => 'Abiff']);
        $this->assertEquals($response[2], ['name' => 'talents[]', 'contents' => 'building']);
        $this->assertEquals($response[3], ['name' => 'talents[]', 'contents' => 'things']);
        $this->assertEquals($response[4], ['name' => 'talents[]', 'contents' => 'and']);
        $this->assertEquals($response[5], ['name' => 'talents[]', 'contents' => 'stuff']);
        $this->assertEquals($response[6]['name'],       'resume');
        $this->assertEquals($response[6]['filename'],   'resume');
        $this->assertEquals($response[7], ['name' => 'demographic_answers[]question_id', 'contents' => '1']);
        $this->assertEquals($response[8], [
          'name' => 'demographic_answers[]answer_options[][answer_option_id]',
          'contents' => '100'
        ]);
        $this->assertEquals($response[9], [
          'name' => 'demographic_answers[]answer_options[][answer_option_id]',
          'contents' => '101'
        ]);
        $this->assertEquals($response[10], ['name' => 'educations[]school_name_id', 'contents' => '5417077']);
        $this->assertEquals($response[11], ['name' => 'educations[]degree_id', 'contents' => '5494452']);
        $this->assertEquals($response[12], ['name' => 'educations[]discipline_id', 'contents' => '5494865']);
        $this->assertEquals($response[13], ['name' => 'educations[]start_date[month]', 'contents' => '8']);
        $this->assertEquals($response[14], ['name' => 'educations[]start_date[year]', 'contents' => '2012']);
        $this->assertEquals($response[15], ['name' => 'educations[]end_date[month]', 'contents' => '5']);
        $this->assertEquals($response[16], ['name' => 'educations[]end_date[year]', 'contents' => '2016']);
    }

    public function testLinksAllIncluded()
    {
        $linksResponse = array(
            '<https://harvest.greenhouse.io/v1/candidates?page=3&per_page=100>; rel="next",' .
            '<https://harvest.greenhouse.io/v1/candidates?page=1&per_page=100>; rel="prev",' .
            '<https://harvest.greenhouse.io/v1/candidates?page=8273&per_page=100>; rel="last"'
        );

        $mockResponse = $this->createStub('Greenhouse\GreenhouseToolsPhp\Tests\Clients\Mocks\MockGuzzleResponse');
        $mockResponse->method('getHeader')
                     ->willReturn($linksResponse);
        $this->client->guzzleResponse = $mockResponse;

        $reflector = new \ReflectionClass('Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $method = $reflector->getMethod('_setLinks');

        $this->assertEquals($this->client->getNextLink(), '');
        $method->invokeArgs($this->client, array());
        $this->assertEquals($this->client->getNextLink(), 'https://harvest.greenhouse.io/v1/candidates?page=3&per_page=100');
    }

    public function testLinksNoneIncluded()
    {
        $linksResponse = array('');

        $mockResponse = $this->createStub('Greenhouse\GreenhouseToolsPhp\Tests\Clients\Mocks\MockGuzzleResponse');
        $mockResponse->method('getHeader')
                     ->willReturn($linksResponse);
        $this->client->guzzleResponse = $mockResponse;

        $reflector = new \ReflectionClass('Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $method = $reflector->getMethod('_setLinks');

        $this->assertEquals($this->client->getNextLink(), '');
        $method->invokeArgs($this->client, array());
        $this->assertEquals($this->client->getNextLink(), '');
    }

    public function testLinksSomeIncluded()
    {
        $linksResponse = array(
            '<https://harvest.greenhouse.io/v1/candidates?cursor=abcdefg>; rel="next",' .
            '<https://harvest.greenhouse.io/v1/candidates?page=8273&per_page=100>; rel="last"'
        );

        $mockResponse = $this->createStub('Greenhouse\GreenhouseToolsPhp\Tests\Clients\Mocks\MockGuzzleResponse');
        $mockResponse->method('getHeader')
                     ->willReturn($linksResponse);
        $this->client->guzzleResponse = $mockResponse;

        $reflector = new \ReflectionClass('Greenhouse\GreenhouseToolsPhp\Clients\GuzzleClient');
        $method = $reflector->getMethod('_setLinks');

        $this->assertEquals($this->client->getNextLink(), '');
        $method->invokeArgs($this->client, array());
        $this->assertEquals($this->client->getNextLink(), 'https://harvest.greenhouse.io/v1/candidates?cursor=abcdefg');
    }
}
