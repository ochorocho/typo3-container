<?php

namespace Ochorocho\T3Container\Command;

use Ochorocho\T3Container\Service\ComposerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'build')]
class CreateContainerCommand extends Command
{
    protected static $defaultDescription = 'Build a docker container for TYPO3.';

    protected function configure()
    {
        $this->addArgument('image-name', InputArgument::REQUIRED, 'Name of the container to be created.');
        $this->addArgument('version', InputArgument::REQUIRED, 'TYPO3 version to build.');
        $this->addOption('buildx', 'x', InputOption::VALUE_NONE, 'Build multiarch image.');
        $this->addOption('push', 'p', InputOption::VALUE_NONE, 'Push image to repository after the build has finished.');
        $this->addOption('load', 'l', InputOption::VALUE_NONE, 'Load image into docker.');
        $this->addOption('php-modules', 'm', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'Install additional PHP modules.');
        $this->addOption('composer-packages', 'c', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'Install additional composer packages.');
        $this->addOption('platforms', 'a', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'List of platform architectures to build.', ['linux/arm64','linux/amd64']);
        $this->addOption('container-engine', 'e', InputOption::VALUE_OPTIONAL, 'Choose a container engine for building the image (supported: docker, podman)', 'docker');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = $input->getArgument('version');
        $imageName = $input->getArgument('image-name');
        $composerPackages = $input->getOption('composer-packages');
        $engine = $input->getOption('container-engine');
        $allowedEngines = ['docker', 'podman'];
        if(!in_array($engine, $allowedEngines, true)) {
            $output->writeln('<error>❌ Unknown container engine' . $engine .'. Engines available: ' . implode(', ', $allowedEngines) . '</error>');
            return Command::FAILURE;
        }

        $composerService = new ComposerService();
        $requirements = $composerService->getRequirements($version, $input->getOption('php-modules'));
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

        $executableFinder = new ExecutableFinder();
        $binary = $executableFinder->find($engine);
        if ($binary === null) {
            $output->writeln('<error>❌ No binary named "' . $engine . '" found in any $PATH.</error>');
            return Command::FAILURE;
        }

        // Output general details
        $output->writeln('<info>PHP version to be included:</info>');
        $output->writeln(' * ' . $requirements['php']);
        $output->writeln('<info>PHP modules to be included (excluding those already enabled):</info>');
        $output->writeln(' * ' . implode(PHP_EOL . ' * ', $requirements['modules']));
        $output->writeln('⛵️ Using container engine "' . $engine . '" (' . $binary . ')');
        $output->writeln('🧱 Build TYPO3 version ' . $version . ' ' . $platformMessage);

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
            $command[] = 'composer_packages_command=composer req ' . implode(' ', $composerPackages);
        }

        if($input->getOption('push')) {
            $command[] = '--push';
        }

        if($input->getOption('load')) {
            $command[] = '--load';
        }

        $finalCommand = array_merge($command, $tagOption);

        $process = new Process($finalCommand);
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
            $output->writeln('<error>❌ Failed to build container '. $imageName . ':' . $version . ' with TYPO3 ' . $version .'</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
