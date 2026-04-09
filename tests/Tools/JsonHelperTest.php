<?php

namespace Greenhouse\GreenhouseToolsPhp\Tests\Tools;

use Greenhouse\GreenhouseToolsPhp\Tools\JsonHelper;

/**
 * This test is not exhaustive and should be added to as more uses for JsonHelper come up.
 */
class JsonHelperTest extends \PHPUnit\Framework\TestCase
{
    private string $json;

    public function setUp(): void
    {
        $root = realpath(dirname(__FILE__));
        $this->json = file_get_contents("$root/../files/test_json/single_job_response.json");
    }

    public function testDecodeToObjectsSingleJob()
    {
        $item = JsonHelper::decodeToObjects($this->json);
        $this->assertInstanceOf('stdClass', $item);
        $this->assertEquals($item->id, 167538);
        $this->assertInstanceOf('stdClass', $item->departments[0]);
        $this->assertEquals($item->departments[0]->id, 7219);
    }
    
    public function testDecodeToHashSingleJob()
    {
        $item = JsonHelper::decodeToHash($this->json);
        $this->assertEquals($item['id'], 167538);
        $this->assertEquals($item['departments'][0]['id'], 7219);
    }
}
