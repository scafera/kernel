<?php

declare(strict_types=1);

namespace Scafera\Kernel\Console\Command;

use Composer\InstalledVersions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'about', description: 'Display information about the current project')]
class AboutCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        $scaferaVersion = InstalledVersions::getVersion('scafera/kernel') ?? 'unknown';

        $xdebugMode = getenv('XDEBUG_MODE') ?: \ini_get('xdebug.mode');

        $rows = [
            ['<info>Scafera</>'],
            new TableSeparator(),
            ['Version', $scaferaVersion],
            ['Environment', $kernel->getEnvironment()],
            ['Debug', $kernel->isDebug() ? 'true' : 'false'],
            ['Cache directory', self::formatPath($kernel->getCacheDir(), $kernel->getProjectDir()).' (<comment>'.self::formatFileSize($kernel->getCacheDir()).'</>)'],
            ['Log directory', self::formatPath($kernel->getLogDir(), $kernel->getProjectDir()).' (<comment>'.self::formatFileSize($kernel->getLogDir()).'</>)'],
            new TableSeparator(),
            ['<info>Symfony</>'],
            new TableSeparator(),
            ['Version', Kernel::VERSION],
            ['Long-Term Support', 4 === Kernel::MINOR_VERSION ? 'Yes' : 'No'],
            ['End of maintenance', Kernel::END_OF_MAINTENANCE.(self::isExpired(Kernel::END_OF_MAINTENANCE) ? ' <error>Expired</>' : ' (<comment>'.self::daysBeforeExpiration(Kernel::END_OF_MAINTENANCE).'</>)')],
            ['End of life', Kernel::END_OF_LIFE.(self::isExpired(Kernel::END_OF_LIFE) ? ' <error>Expired</>' : ' (<comment>'.self::daysBeforeExpiration(Kernel::END_OF_LIFE).'</>)')],
            new TableSeparator(),
            ['<info>PHP</>'],
            new TableSeparator(),
            ['Version', \PHP_VERSION],
            ['Architecture', (\PHP_INT_SIZE * 8).' bits'],
            ['Intl locale', class_exists(\Locale::class, false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a'],
            ['Timezone', date_default_timezone_get().' (<comment>'.(new \DateTimeImmutable())->format(\DateTimeInterface::W3C).'</>)'],
            ['OPcache', \extension_loaded('Zend OPcache') ? (filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Not enabled') : 'Not installed'],
            ['APCu', \extension_loaded('apcu') ? (filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Not enabled') : 'Not installed'],
            ['Xdebug', \extension_loaded('xdebug') ? ($xdebugMode && 'off' !== $xdebugMode ? 'Enabled ('.$xdebugMode.')' : 'Not enabled') : 'Not installed'],
        ];

        $io->table([], $rows);

        return 0;
    }

    private static function formatPath(string $path, string $baseDir): string
    {
        return preg_replace('~^'.preg_quote($baseDir, '~').'~', '.', $path);
    }

    private static function formatFileSize(string $path): string
    {
        if (is_file($path)) {
            $size = filesize($path) ?: 0;
        } else {
            if (!is_dir($path)) {
                return 'n/a';
            }

            $size = 0;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS)) as $file) {
                if ($file->isReadable()) {
                    $size += $file->getSize();
                }
            }
        }

        return Helper::formatMemory($size);
    }

    private static function isExpired(string $date): bool
    {
        $date = \DateTimeImmutable::createFromFormat('d/m/Y', '01/'.$date);

        return false !== $date && new \DateTimeImmutable() > $date->modify('last day of this month 23:59:59');
    }

    private static function daysBeforeExpiration(string $date): string
    {
        $date = \DateTimeImmutable::createFromFormat('d/m/Y', '01/'.$date);

        return (new \DateTimeImmutable())->diff($date->modify('last day of this month 23:59:59'))->format('in %R%a days');
    }
}
