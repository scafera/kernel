<?php

declare(strict_types=1);

namespace Scafera\Kernel\Test;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

final class TestResponse
{
    /** @internal */
    public function __construct(private readonly Response $inner)
    {
    }

    public function assertOk(): self
    {
        Assert::assertSame(200, $this->inner->getStatusCode(), 'Expected status 200, got ' . $this->inner->getStatusCode());

        return $this;
    }

    public function assertStatus(int $expected): self
    {
        Assert::assertSame($expected, $this->inner->getStatusCode(), 'Expected status ' . $expected . ', got ' . $this->inner->getStatusCode());

        return $this;
    }

    public function assertSuccessful(): self
    {
        $status = $this->inner->getStatusCode();
        Assert::assertTrue($status >= 200 && $status < 300, 'Expected successful status (2xx), got ' . $status);

        return $this;
    }

    public function assertRedirect(?string $uri = null): self
    {
        $status = $this->inner->getStatusCode();
        Assert::assertTrue($status >= 300 && $status < 400, 'Expected redirect status (3xx), got ' . $status);

        if ($uri !== null) {
            Assert::assertSame($uri, $this->inner->headers->get('Location'));
        }

        return $this;
    }

    public function assertHeader(string $name, string $expected): self
    {
        Assert::assertSame($expected, $this->inner->headers->get($name), 'Header "' . $name . '" does not match');

        return $this;
    }

    public function assertHeaderExists(string $name): self
    {
        Assert::assertTrue($this->inner->headers->has($name), 'Header "' . $name . '" not found');

        return $this;
    }

    public function assertJsonContains(array $expected): self
    {
        $data = $this->json();
        foreach ($expected as $key => $value) {
            Assert::assertArrayHasKey($key, $data, 'JSON key "' . $key . '" not found');
            Assert::assertSame($value, $data[$key], 'JSON value for "' . $key . '" does not match');
        }

        return $this;
    }

    public function assertJsonPath(string $key, mixed $expected): self
    {
        $data = $this->json();
        Assert::assertArrayHasKey($key, $data, 'JSON key "' . $key . '" not found');
        Assert::assertSame($expected, $data[$key]);

        return $this;
    }

    public function assertContentContains(string $needle): self
    {
        Assert::assertStringContainsString($needle, $this->getContent());

        return $this;
    }

    public function getContent(): string
    {
        return (string) $this->inner->getContent();
    }

    public function getStatusCode(): int
    {
        return $this->inner->getStatusCode();
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $data = json_decode($this->getContent(), true);
        Assert::assertIsArray($data, 'Response is not valid JSON');

        return $data;
    }
}
