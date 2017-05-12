<?php

class RDApiTest extends TestCase
{
    public function testUrl()
    {
        $api = new \RD\RDStationAPI('123', '456');

        $this->assertEquals('https://www.rdstation.com.br/api/', $api->baseURL);
        $this->assertEquals('123', $api->privateToken);
        $this->assertEquals('456', $api->token);
    }
}