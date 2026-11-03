<?php

namespace Ochorocho\T3Container\Service;

use Composer\Package\Version\VersionParser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ComposerService
{
    // Part of the PHP binaries, there is no package to install
    private const BUNDLED_MODULES = ['json', 'libxml', 'openssl', 'pcre', 'session', 'sodium', 'zlib'];

    private HttpClientInterface $client;

    public function __construct()
    {
        $this->client = HttpClient::create();
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function getRequirements(string $version = 'dev-main', array $additionalPhpModules = [], ?string $phpVersion = null): array
    {
        if ($version === 'dev-main') {
            $composerJson = $this->request('https://raw.githubusercontent.com/TYPO3/typo3/main/composer.json');
            $versionName = $version;
        } else {
            // @todo: use composer to find package?!
            $tag = $this->findLatestTag($version);
            $composerJson = $this->request('https://raw.githubusercontent.com/TYPO3/typo3/' . $tag['commit']['sha'] . '/composer.json');
            $versionName = $tag['name'];
        }

        // PHP Version to install
        $installablePhpVersion = $phpVersion ?: $this->getPhpVersionFromComposer($composerJson);
        $phpModules = $this->getPhpModulesFromComposer($composerJson, $additionalPhpModules);

        return [
            'php' => $installablePhpVersion,
            'modules' => $phpModules,
            'packages' => $this->getPhpPackages($phpModules, $installablePhpVersion),
            'tags' => $this->getVersionTags($versionName, $version)
        ];
    }

    /**
     * GitHub returns at most 100 tags per page, newest version first.
     */
    private function findLatestTag(string $version): array
    {
        $page = 1;
        do {
            $tags = $this->request('https://api.github.com/repos/typo3/typo3/tags?per_page=100&page=' . $page++);
            foreach ($tags as $tag) {
                if (str_contains($tag['name'], $version)) {
                    return $tag;
                }
            }
        } while ($tags !== []);

        throw new \RuntimeException('No TYPO3 tag found for version "' . $version . '".');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    private function request(string $url, string $method = 'GET'): array
    {
        return json_decode($this->client->request($method, $url)->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR);
    }

    private function getPhpVersionFromComposer(array $json): string
    {
        $php = array_reverse(explode('||', $json['require']['php']))[0];
        $versionParser = new VersionParser();
        $requiredVersion = $versionParser->parseConstraints($php)->getLowerBound();

        return implode('.', array_slice(explode('.', $requiredVersion->getVersion()), 0, 2));
    }

    /**
     * Get all PHP modules
     * - Read modules from composer
     * - Add necessary modules not defined in composer
     * - Add modules given by the user, a value may contain several modules, e.g. "intl opcache"
     */
    private function getPhpModulesFromComposer(array $json, array $additionalPhpModules = []): array
    {
        $modules = [
            "gd", "redis", "curl", "mbstring", "xml", "zip", "opcache", "pgsql", "pdo_pgsql",
            "pdo_mysql", "mysqli", "sqlite3", "pdo_sqlite", "openssl", "zlib",
        ];
        $modules = array_merge($modules, preg_split('/\s+/', implode(' ', $additionalPhpModules), -1, PREG_SPLIT_NO_EMPTY));
        foreach ($json['require'] as $key => $value) {
            if($key !== 'ext-PDO' && str_starts_with($key, 'ext-')) {
                $modules[] = str_replace('ext-', '', $key);
            }
        }

        return array_values(array_unique(array_map('strtolower', $modules)));
    }

    /**
     * Translate PHP modules to Sury apt packages, e.g. "pdo_mysql" to "php8.4-pdo-mysql".
     * Some of these are virtual packages, apt selects the package providing them.
     */
    private function getPhpPackages(array $modules, string $phpVersion): array
    {
        $bundledModules = self::BUNDLED_MODULES;
        // OPcache is part of PHP itself since 8.5
        if (version_compare($phpVersion, '8.5', '>=')) {
            $bundledModules[] = 'opcache';
        }

        return array_map(
            static fn(string $module): string => 'php' . $phpVersion . '-' . str_replace('_', '-', $module),
            array_values(array_diff($modules, $bundledModules))
        );
    }

    private function getVersionTags($detectedVersion, $version): array
    {
        // To follow the container standard, dev-main
        // will also get the latest tag.
        if($version === 'dev-main') {
            return ['dev-main', 'latest'];
        }

        // Take the given version and add tags for major and minor.
        // * "v12" will result in tags for v12, v12.<latest> and v12.<latest>.<latest>
        // * "v12.1" will result in tags for v12, v12.1 and v12.<latest>.<latest>
        $tags[] = $version;
        foreach (explode('.', str_replace($version . '.', '', $detectedVersion)) as $versionPart) {
            $version .= '.' . $versionPart;
            $tags[] = $version;
        }

        return $tags;
    }
}
