<?php

declare(strict_types=1);

namespace Scafera\Kernel\Test;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as SymfonyWebTestCase;

abstract class WebTestCase extends SymfonyWebTestCase
{
    private ?KernelBrowser $browser = null;

    protected function tearDown(): void
    {
        $this->browser = null;
        parent::tearDown();
    }

    /**
     * @internal Returns the Symfony browser client. This method is internal
     * infrastructure and should not be called directly from user tests.
     * Use get(), post(), put(), patch(), delete() methods instead.
     */
    private function client(): KernelBrowser
    {
        if ($this->browser === null) {
            $this->browser = static::createClient();
        }

        return $this->browser;
    }

    protected function get(string $uri, array $headers = []): TestResponse
    {
        $this->client()->request('GET', $uri, [], [], $this->formatHeaders($headers));

        return new TestResponse($this->client()->getResponse());
    }

    protected function post(string $uri, array $parameters = [], array $headers = []): TestResponse
    {
        $this->client()->request('POST', $uri, $parameters, [], $this->formatHeaders($headers));

        return new TestResponse($this->client()->getResponse());
    }

    protected function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $this->client()->request('POST', $uri, [], [], $this->formatHeaders($headers), json_encode($data));

        return new TestResponse($this->client()->getResponse());
    }

    protected function put(string $uri, array $parameters = [], array $headers = []): TestResponse
    {
        $this->client()->request('PUT', $uri, $parameters, [], $this->formatHeaders($headers));

        return new TestResponse($this->client()->getResponse());
    }

    protected function putJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $this->client()->request('PUT', $uri, [], [], $this->formatHeaders($headers), json_encode($data));

        return new TestResponse($this->client()->getResponse());
    }

    protected function delete(string $uri, array $headers = []): TestResponse
    {
        $this->client()->request('DELETE', $uri, [], [], $this->formatHeaders($headers));

        return new TestResponse($this->client()->getResponse());
    }

    protected function patch(string $uri, array $parameters = [], array $headers = []): TestResponse
    {
        $this->client()->request('PATCH', $uri, $parameters, [], $this->formatHeaders($headers));

        return new TestResponse($this->client()->getResponse());
    }

    protected function patchJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $this->client()->request('PATCH', $uri, [], [], $this->formatHeaders($headers), json_encode($data));

        return new TestResponse($this->client()->getResponse());
    }

    /** @return array<string, string> */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));
            if ($key === 'CONTENT_TYPE') {
                $formatted['CONTENT_TYPE'] = $value;
            } else {
                $formatted['HTTP_' . $key] = $value;
            }
        }

        return $formatted;
    }
}
