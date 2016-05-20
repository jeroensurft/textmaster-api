<?php

/*
 * This file is part of the Textmaster Api v1 client package.
 *
 * (c) Christian Daguerre <christian@daguer.re>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Textmaster\Unit\HttpClient;

use Guzzle\Http\Client as GuzzleClient;
use Guzzle\Http\Message\Response;
use Textmaster\Client;
use Textmaster\HttpClient\HttpClient;
use Textmaster\HttpClient\Message\ResponseMediator;

class HttpClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function shouldBeAbleToPassOptionsToConstructor()
    {
        $httpClient = new TestHttpClient(array(
            'timeout' => 33,
        ), $this->getBrowserMock());

        $this->assertSame(33, $httpClient->getOption('timeout'));
        $this->assertSame('v1', $httpClient->getOption('api_version'));
    }

    /**
     * @test
     */
    public function shouldBeAbleToSetOption()
    {
        $httpClient = new TestHttpClient(array(), $this->getBrowserMock());
        $httpClient->setOption('timeout', 666);

        $this->assertSame(666, $httpClient->getOption('timeout'));
    }

    /**
     * @test
     */
    public function shouldAuthenticateUsingAllGivenParameters()
    {
        $client = new GuzzleClient();
        $listeners = $client->getEventDispatcher()->getListeners('request.before_send');
        $this->assertCount(1, $listeners);

        $httpClient = new TestHttpClient(array(), $client);
        $httpClient->authenticate('api_key', 'secret');

        $listeners = $client->getEventDispatcher()->getListeners('request.before_send');
        $this->assertCount(2, $listeners);

        $authListener = $listeners[1][0];
        $this->assertInstanceOf('Textmaster\HttpClient\Listener\AuthListener', $authListener);
    }

    /**
     * @test
     */
    public function shouldDoGETRequest()
    {
        $path = '/some/path';
        $parameters = array('a' => 'b');
        $headers = array('c' => 'd');

        $client = $this->getBrowserMock();

        $httpClient = new HttpClient(array(), $client);
        $httpClient->get($path, $parameters, $headers);
    }

    /**
     * @test
     */
    public function shouldDoPOSTRequest()
    {
        $path = '/some/path';
        $body = 'a = b';
        $headers = array('c' => 'd');

        $client = $this->getBrowserMock();
        $client->expects($this->once())
            ->method('createRequest')
            ->with('POST', $path, $this->isType('array'), $body);

        $httpClient = new HttpClient(array(), $client);
        $httpClient->post($path, $body, $headers);
    }

    /**
     * @test
     */
    public function shouldDoPOSTRequestWithoutContent()
    {
        $path = '/some/path';

        $client = $this->getBrowserMock();
        $client->expects($this->once())
            ->method('createRequest')
            ->with('POST', $path, $this->isType('array'));

        $httpClient = new HttpClient(array(), $client);
        $httpClient->post($path);
    }

    /**
     * @test
     */
    public function shouldDoPATCHRequest()
    {
        $path = '/some/path';
        $body = 'a = b';
        $headers = array('c' => 'd');

        $client = $this->getBrowserMock();

        $httpClient = new HttpClient(array(), $client);
        $httpClient->patch($path, $body, $headers);
    }

    /**
     * @test
     */
    public function shouldDoDELETERequest()
    {
        $path = '/some/path';
        $body = 'a = b';
        $headers = array('c' => 'd');

        $client = $this->getBrowserMock();

        $httpClient = new HttpClient(array(), $client);
        $httpClient->delete($path, $body, $headers);
    }

    /**
     * @test
     */
    public function shouldDoPUTRequest()
    {
        $path = '/some/path';
        $headers = array('c' => 'd');

        $client = $this->getBrowserMock();

        $httpClient = new HttpClient(array(), $client);
        $httpClient->put($path, $headers);
    }

    /**
     * @test
     */
    public function shouldDoCustomRequest()
    {
        $path = '/some/path';
        $body = 'a = b';
        $options = array('c' => 'd');

        $client = $this->getBrowserMock();

        $httpClient = new HttpClient(array(), $client);
        $httpClient->request($path, $body, 'HEAD', $options);
    }

    /**
     * @test
     */
    public function shouldHandlePagination()
    {
        $path = '/some/path';
        $body = 'a = b';
        $headers = array('c' => 'd');

        $response = new Response(200);
        $response->addHeader('Link', "<page1>; rel=\"page2\", \n<page3>; rel=\"page4\"");

        $client = $this->getBrowserMock();

        $httpClient = new HttpClient(array(), $client);
        $httpClient->request($path, $body, 'HEAD', $headers);

        $this->assertSame(array('page2' => 'page1', 'page4' => 'page3'), ResponseMediator::getPagination($response));
    }

    /**
     * @test
     */
    public function shouldAllowToReturnRawContent()
    {
        $path = '/some/path';
        $parameters = array('a = b');
        $headers = array('c' => 'd');

        $message = $this->getMock('Guzzle\Http\Message\Response', array(), array(200));
        $message->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue('Just raw context'));

        $client = $this->getBrowserMock();
        $client->expects($this->once())
            ->method('send')
            ->will($this->returnValue($message));

        $httpClient = new TestHttpClient(array(), $client);
        $response = $httpClient->get($path, $parameters, $headers);

        $this->assertSame('Just raw context', $response->getBody());
        $this->assertInstanceOf('Guzzle\Http\Message\MessageInterface', $response);
    }

    protected function getBrowserMock(array $methods = array())
    {
        $mock = $this->getMock(
            'Guzzle\Http\Client',
            array_merge(
                array('send', 'createRequest'),
                $methods
            )
        );

        $mock->expects($this->any())
            ->method('createRequest')
            ->will($this->returnValue($this->getMock('Guzzle\Http\Message\Request', array(), array('GET', 'some'))));

        return $mock;
    }
}

class TestHttpClient extends HttpClient
{
    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    public function request($path, $body, $httpMethod = 'GET', array $headers = array(), array $options = array())
    {
        $request = $this->client->createRequest($httpMethod, $path);

        return $this->client->send($request);
    }
}