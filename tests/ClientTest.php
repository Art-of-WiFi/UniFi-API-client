<?php

namespace UniFi_API\Tests;

use UniFi_API\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private $client;

    protected function setUp()
    {
        $this->client = new Client('unifi', 'unifi');
    }

    public function testSetSite()
    {
        // default
        $this->assertEquals('default', $this->client->get_site());

        // custom
        $this->client->set_site('foobar');
        $this->assertEquals('foobar', $this->client->get_site());

        // whitespace
        $this->client->set_site('   foobar ');
        $this->assertEquals('foobar', $this->client->get_site());

        // whitespace (debug mode)
        $this->client->set_debug(true);
        $this->expectException(\PHPUnit_Framework_Error_Notice::class);
        $this->expectExceptionCode(E_USER_NOTICE);
        $this->expectExceptionMessage('The provided (short) site name may not contain any spaces');
        $this->client->set_site('   foobar ');
    }
}
