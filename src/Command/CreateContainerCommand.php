<?php

namespace Ochorocho\T3Container\Command;

use Composer\IO\IOInterface;
use Composer\Util\ProcessExecutor;
use Ochorocho\T3Container\Service\ComposerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * HELP
 * -v --version v12 ....... v12.4 narrow down
 * -x --buildx "--platform linux/arm64,linux/amd64" cross compile
 * -p --push "--push" push to repo
 * -m --php-modules list of additional php modules
 * -n --image-name image name eg ochorocho/typo3-container
 * -c --composer-packages "vendor/package:version-constraint"
 */

#[AsCommand(name: 'container:build')]
class CreateContainerCommand extends Command
{
    protected static $defaultDescription = 'Build a docker container for TYPO3.';

    protected function configure()
    {
        $this->addArgument('image-name', InputArgument::REQUIRED, 'Name of the container to be created.');
        $this->addArgument('version', InputArgument::REQUIRED, 'TYPO3 version to build.');
        $this->addOption('buildx', 'x', InputOption::VALUE_NONE, 'Build multiarch image.');
        $this->addOption('push', 'p', InputOption::VALUE_NONE, 'Push image to repository after the build has finished.');
        $this->addOption('php-modules', 'm', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'Install additional PHP modules.');
        $this->addOption('composer-packages', 'c', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'Install additional composer packages.');
        $this->addOption('platforms', 'a', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'List of platform architectures to build.', ['linux/arm64','linux/amd64']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = $input->getArgument('version');
        $imageName = $input->getArgument('image-name');
        $composerPackages = $input->getOption('composer-packages');

        $composerService = new ComposerService();
        $requirements = $composerService->getRequirements($version, $input->getOption('php-modules'));

        $output->writeln('<info>PHP version to be included:</info>');
        $output->writeln(' * ' . $requirements['php']);

        $output->writeln('<info>PHP modules to be included (excluding those already enabled):</info>');
        $output->writeln(' * ' . implode(PHP_EOL . ' * ', $requirements['modules']));

        $phpModules = $requirements['modules'];
        $phpVersion = $requirements['php'];
        $tags = $requirements['tags'];

        // Image tags, option: -t
        $tagOption = [];
        foreach ($tags as $tag) {
            $tagOption[] = '-t';
            $tagOption[] = $imageName . ':' . $tag;
        }

        $platformMessage = '';
        if($input->getOption('buildx')) {
            $platformMessage = 'for the following platforms ' . implode(',', $input->getOption('platforms'));
        }

        $output->writeln('⛵️Build TYPO3 version ' . $version . ' ' . $platformMessage);

        $executableFinder = new ExecutableFinder();
        $binary = $executableFinder->find('docker');
        $command = [$binary];

        if($input->getOption('buildx')) {
            // ensure buildx is ready "docker buildx create --name typo3-builder --use --bootstrap"
            $command[] = 'buildx';
        }

        $command[] = 'build';

        if($input->getOption('buildx')) {
            $command[] = '--platform';
            $command[] = implode(',', $input->getOption('platforms'));
        }

        $command[] = '--no-cache';
        $command[] = '--progress=plain';
        $command[] = '.';
        $command[] = '-f';
        $command[] = 'Dockerfile';

        $command[] = '--build-arg';
        $command[] = 'php_version=' . $phpVersion;
        $command[] = '--build-arg';
        $command[] = 'php_modules=' . implode(' ', $phpModules);
        $command[] = '--build-arg';
        $command[] = 'typo3_version=' . $version;
        $command[] = '--build-arg';
        $command[] = 'php_ext_configure=docker-php-ext-configure gd --with-freetype --with-jpeg';

        if (!empty($composerPackages)) {
            $command[] = '--build-arg';
            $command[] = 'composer_packages_command="' . implode(' ', $composerPackages) . '"';
        }

        $command += $tagOption;

        if($input->getOption('push')) {
            $command[] = '--push';
        }
        $process = new Process($command);
        $process->setTty(1);
        $process->setTimeout(null);
        $process->setIdleTimeout(3600);
        $process->run();

        if($process->isSuccessful() && $input->getOption('push')) {
            $output->writeln('🐳 Pushed container ' . $imageName . ':' . $version . ' with tags ' . implode(', ', $tags) . ' to docker hub.');
        } elseif ($process->isSuccessful()) {
            $output->writeln('🎁 Build of container ' . $imageName . ':' . $version . ' with tags ' . implode(', ', $tags) . ' successful');
        }

        if(!$process->isSuccessful()) {
            $output->writeln($process->getErrorOutput());
            $output->writeln('<error>𐄂 Failed to build container '. $imageName . ':' . $version . ' with TYPO3 ' . $version .'</error>');
        }

        return Command::SUCCESS;
    }
}
