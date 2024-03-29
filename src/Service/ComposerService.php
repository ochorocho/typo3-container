<?php

namespace Ochorocho\T3Container\Service;

use Composer\Package\Version\VersionParser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ComposerService
{
    private HttpClientInterface $client;

    public function __construct()
    {
        $this->client = HttpClient::create();
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function getRequirements(string $version = 'dev-main', array $additionalPhpModules = []): array
    {
        if ($version === 'dev-main') {
            $composerJson = $this->request('https://raw.githubusercontent.com/TYPO3/typo3/main/composer.json');
            $versionName = $version;
        } else {
            // @todo: use composer to find package?!
            $tags = $this->request('https://api.github.com/repos/typo3/typo3/tags?per_page=999');
            $filteredVersions = array_filter($tags, function ($value) use ($version) {
                return str_contains($value['name'], $version);
            });
            $sha = reset($filteredVersions)['commit']['sha'];
            $composerJson = $this->request('https://raw.githubusercontent.com/TYPO3/typo3/' . $sha . '/composer.json');
            $versionName = reset($filteredVersions)['name'];
        }

        // PHP Version to install
        $installablePhpVersion = $this->getPhpVersionFromComposer($composerJson);
        $phpModules = $this->getPhpModulesFromComposer($composerJson, $installablePhpVersion, $additionalPhpModules);

        return [
            'php' => $installablePhpVersion,
            'modules' => $phpModules,
            'tags' => $this->getVersionTags($versionName, $version)
        ];
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
     * - Remove all modules from array already contained in the php available in the docker image
     */
    private function getPhpModulesFromComposer(array $json, string $phpVersion, array $additionalPhpModules = []): array
    {
        $modules = ["zip", "opcache", "pgsql", "pdo_pgsql", "pdo_mysql", "mysqli", "openssl", "zlib"];
        $modules += $additionalPhpModules;
        foreach ($json['require'] as $key => $value) {
            if($key !== 'ext-PDO' && str_starts_with($key, 'ext-')) {
                $modules[] = str_replace('ext-', '', $key);
            }
        }

        $executableFinder = new ExecutableFinder();
        $binary = $executableFinder->find('docker');
        $command = [$binary, 'run', '--rm', 'php:'. $phpVersion . '-apache', 'php', '-m'];
        $process = new Process($command);
        $process->run();

        if ($process->isSuccessful()) {
            $phpModulesInContainer = explode("\n", $process->getOutput());
        } else {
            throw new ProcessFailedException($process);
        }

        return array_filter($modules, function ($value) use ($phpModulesInContainer) {
            return !in_array($value, $phpModulesInContainer);
        });
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
