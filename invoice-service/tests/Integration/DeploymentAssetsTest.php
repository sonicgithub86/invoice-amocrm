<?php

declare(strict_types=1);

namespace InvoiceService\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class DeploymentAssetsTest extends TestCase
{
    public function testLocalComposePublishesOnlyTheLoopbackWebPort(): void
    {
        $config = $this->renderCompose('compose.local.yaml', 'invoice-service:local');

        self::assertSame('invoice-service', $config['name']);
        self::assertSame('127.0.0.1', $config['services']['web']['ports'][0]['host_ip']);
        self::assertSame('18080', $config['services']['web']['ports'][0]['published']);
        self::assertSame(80, $config['services']['web']['ports'][0]['target']);
        self::assertArrayNotHasKey('ports', $config['services']['db']);
        self::assertArrayNotHasKey('ports', $config['services']['worker']);
        self::assertArrayNotHasKey('ports', $config['services']['refresher']);
        self::assertTrue($config['networks']['database']['internal']);
    }

    public function testVpsComposeHasNoPublishedPortsAndOnlyWebJoinsTheEdge(): void
    {
        $config = $this->renderCompose('deploy/compose.vps.yaml', 'registry.example.test/invoice-service:release-test');

        foreach (['web', 'worker', 'refresher', 'db'] as $service) {
            self::assertArrayNotHasKey('ports', $config['services'][$service], $service . ' must not publish a host port');
        }

        self::assertTrue($config['networks']['invoice-edge']['external']);
        self::assertArrayHasKey('invoice-edge', $config['services']['web']['networks']);
        self::assertArrayNotHasKey('invoice-edge', $config['services']['worker']['networks']);
        self::assertArrayNotHasKey('invoice-edge', $config['services']['refresher']['networks']);
        self::assertArrayNotHasKey('invoice-edge', $config['services']['db']['networks']);
        self::assertSame('registry.example.test/invoice-service:release-test', $config['services']['web']['image']);
        self::assertSame('registry.example.test/invoice-service:release-test', $config['services']['worker']['image']);
        self::assertSame('registry.example.test/invoice-service:release-test', $config['services']['refresher']['image']);
    }

    public function testEveryServiceHasResourceAndLogBounds(): void
    {
        $config = $this->renderCompose('deploy/compose.vps.yaml', 'registry.example.test/invoice-service:release-test');

        foreach (['web', 'worker', 'refresher', 'db'] as $service) {
            $definition = $config['services'][$service];
            self::assertNotEmpty($definition['mem_limit'] ?? null, $service . ' must have a memory limit');
            self::assertNotEmpty($definition['cpus'] ?? null, $service . ' must have a CPU limit');
            self::assertGreaterThan(0, $definition['pids_limit'] ?? 0, $service . ' must have a PID limit');
            self::assertSame('json-file', $definition['logging']['driver'] ?? null);
            self::assertSame('10m', $definition['logging']['options']['max-size'] ?? null);
            self::assertSame('3', (string) ($definition['logging']['options']['max-file'] ?? ''));
        }
    }

    /** @return array<string, mixed> */
    private function renderCompose(string $overlay, string $image): array
    {
        $root = dirname(__DIR__, 2);
        $command = [
            'docker', 'compose',
            '--project-directory', $root,
            '-f', $root . '/compose.yaml',
            '-f', $root . '/' . $overlay,
            'config', '--format', 'json',
        ];
        $environment = array_merge($_ENV, [
            'INVOICE_SERVICE_ENV_FILE' => $root . '/deploy/invoice-service.env.example',
            'POSTGRES_ENV_FILE' => $root . '/deploy/postgres.env.example',
            'INVOICE_SERVICE_IMAGE' => $image,
        ]);

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment, ['bypass_shell' => true]);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, $stderr ?: $stdout);
        $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
