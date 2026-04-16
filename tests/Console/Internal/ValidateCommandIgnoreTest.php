<?php

declare(strict_types=1);

namespace Scafera\Kernel\Tests\Console\Internal;

use PHPUnit\Framework\TestCase;
use Scafera\Kernel\Console\Internal\ValidateCommand;
use Scafera\Kernel\Contract\AdvisorInterface;
use Scafera\Kernel\Contract\ValidatorInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class ValidateCommandIgnoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_validate_ignore_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->tmpDir . '/public', 0777, true);
        mkdir($this->tmpDir . '/var', 0777, true);
        mkdir($this->tmpDir . '/config', 0777, true);
        file_put_contents($this->tmpDir . '/composer.json', '{}');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testProjectLevelIgnoreSuppressesValidator(): void
    {
        $this->writeConfig(<<<YAML
        scafera:
            ignore:
                - test.always-fails
        YAML);

        $tester = $this->runValidate(
            validators: [new FakeValidator('test.always-fails', 'Always fails', ['boom'])],
        );

        $tester->assertCommandIsSuccessful();
        self::assertStringNotContainsString('FAILED', $tester->getDisplay());
        self::assertStringContainsString('Ignored:', $tester->getDisplay());
        self::assertStringContainsString('[IGNORED] Always fails (test.always-fails)', $tester->getDisplay());
        self::assertStringContainsString('1 rule(s) ignored', $tester->getDisplay());
    }

    public function testProjectLevelIgnoreSuppressesAdvisor(): void
    {
        $this->writeConfig(<<<YAML
        scafera:
            ignore:
                - test.chatty-advisor
        YAML);

        $tester = $this->runValidate(
            advisors: [new FakeAdvisor('test.chatty-advisor', 'Chatty', ['hey'])],
        );

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('[IGNORED] Chatty (test.chatty-advisor)', $tester->getDisplay());
    }

    public function testStrictFlagDisablesIgnores(): void
    {
        $this->writeConfig(<<<YAML
        scafera:
            ignore:
                - test.always-fails
        YAML);

        $tester = $this->runValidate(
            validators: [new FakeValidator('test.always-fails', 'Always fails', ['boom'])],
            inputs: ['--strict' => true],
        );

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('FAILED', $tester->getDisplay());
        self::assertStringNotContainsString('Ignored:', $tester->getDisplay());
    }

    public function testUnknownIgnoreIdIsHarmless(): void
    {
        $this->writeConfig(<<<YAML
        scafera:
            ignore:
                - test.does-not-exist
        YAML);

        $tester = $this->runValidate(
            validators: [new FakeValidator('test.always-passes', 'Always passes', [])],
        );

        $tester->assertCommandIsSuccessful();
        self::assertStringNotContainsString('Ignored:', $tester->getDisplay());
    }

    public function testDuplicateIgnoreIdsAreDedupedInTailBlock(): void
    {
        $this->writeConfig(<<<YAML
        scafera:
            ignore:
                - test.always-fails
                - test.always-fails
        YAML);

        $tester = $this->runValidate(
            validators: [new FakeValidator('test.always-fails', 'Always fails', ['boom'])],
        );

        // Validator appears only once in the ignored tail
        $output = $tester->getDisplay();
        self::assertSame(1, substr_count($output, '[IGNORED]'));
    }

    public function testIgnoreListWithNoConfigYamlIsHarmless(): void
    {
        // no config.yaml written

        $tester = $this->runValidate(
            validators: [new FakeValidator('test.always-passes', 'Always passes', [])],
        );

        $tester->assertCommandIsSuccessful();
        self::assertStringNotContainsString('Ignored:', $tester->getDisplay());
    }

    public function testUnknownIgnoreIdIsReportedAsWarning(): void
    {
        $this->writeConfig(<<<YAML
        scafera:
            ignore:
                - test.srvice-final
        YAML);

        $tester = $this->runValidate(
            validators: [new FakeValidator('test.service-final', 'Service final', [])],
        );

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        self::assertStringContainsString('unknown ignore ID', $output);
        self::assertStringContainsString('test.srvice-final', $output);
    }

    public function testKnownIgnoreIdDoesNotEmitUnknownWarning(): void
    {
        $this->writeConfig(<<<YAML
        scafera:
            ignore:
                - test.service-final
        YAML);

        $tester = $this->runValidate(
            validators: [new FakeValidator('test.service-final', 'Service final', [])],
        );

        $tester->assertCommandIsSuccessful();
        self::assertStringNotContainsString('unknown ignore ID', $tester->getDisplay());
    }

    public function testStrictStillReportsUnknownIgnoreIds(): void
    {
        // --strict bypasses the *ignoring* behaviour, but typos in the ignore
        // list are still a config problem that CI should surface.
        $this->writeConfig(<<<YAML
        scafera:
            ignore:
                - test.srvice-final
        YAML);

        $tester = $this->runValidate(
            validators: [new FakeValidator('test.service-final', 'Service final', [])],
            inputs: ['--strict' => true],
        );

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        self::assertStringContainsString('unknown ignore ID', $output);
        self::assertStringContainsString('test.srvice-final', $output);
    }

    public function testMalformedYamlEmitsWarningAndContinues(): void
    {
        $this->writeConfig("scafera:\n    ignore:\n  - bad indent\n    : broken");

        $tester = $this->runValidate(
            validators: [new FakeValidator('test.always-passes', 'Always passes', [])],
        );

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        self::assertStringContainsString('invalid YAML', $output);
        // validation still ran
        self::assertStringContainsString('Always passes', $output);
    }

    /**
     * @param list<ValidatorInterface> $validators
     * @param list<AdvisorInterface> $advisors
     * @param array<string, mixed> $inputs
     */
    private function runValidate(
        array $validators = [],
        array $advisors = [],
        array $inputs = [],
    ): CommandTester {
        $command = new ValidateCommand($this->tmpDir, $validators, $advisors);
        $tester = new CommandTester($command);
        $tester->execute($inputs);

        return $tester;
    }

    private function writeConfig(string $yaml): void
    {
        file_put_contents($this->tmpDir . '/config/config.yaml', $yaml);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}

final class FakeValidator implements ValidatorInterface
{
    /** @param list<string> $violations */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly array $violations,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function validate(string $projectDir): array
    {
        return $this->violations;
    }
}

final class FakeAdvisor implements AdvisorInterface
{
    /** @param list<string> $hints */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly array $hints,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function skipped(string $projectDir): ?string
    {
        return null;
    }

    public function advise(string $projectDir): array
    {
        return $this->hints;
    }
}
