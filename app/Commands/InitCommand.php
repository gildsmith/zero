<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class InitCommand extends Command
{
    protected $signature = 'init';

    protected $description = 'Sets up the Gildsmith environment';

    public function handle(): void
    {
        $this->info('Setting up the Gildsmith environment...');

        $tasks = [
            'Creating project' => fn() => $this->createProject(),
            'Creating directories' => fn() => $this->createDirectories(),
            'Modifying composer.json' => fn() => $this->modifyComposerJson(),
            'Modifying package.json' => fn() => $this->modifyPackageJson(),
        ];

        $this->withProgressBar(array_keys($tasks), function ($task) use ($tasks) {
            $this->info("\n$task...");
            $tasks[$task]();
        });

        $this->newLine();
        $this->info('Gildsmith environment setup complete.');
    }

    protected function createProject(): void
    {
        if (!is_dir('gildsmith')) {
            $this->info('Creating the Gildsmith project using Composer...');
            $process = new Process(['composer', 'create-project', 'gildsmith/gildsmith:dev-master', 'gildsmith']);
            $process->run();

            $process->isSuccessful()
                ? $this->info('Gildsmith project created successfully.')
                : $this->error('Failed to create the project: ' . $process->getErrorOutput());

        } else {
            $this->warn("'gildsmith' directory already exists.");
        }
    }

    protected function createDirectories(): void
    {
        $directories = ['packages/composer', 'packages/npm'];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
                $this->info("Created '$directory' directory.");
            } else {
                $this->warn("'$directory' directory already exists.");
            }
        }
    }

    protected function modifyComposerJson(): void
    {
        $composerJsonPath = 'gildsmith/composer.json';

        if (!file_exists($composerJsonPath)) {
            $this->error("composer.json not found at $composerJsonPath.");
            return;
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), true);

        if (!isset($composerJson['repositories'])) {
            $composerJson['repositories'] = [];
        }

        $pathRepository = [
            'type' => 'path',
            'url' => '../packages/composer/*/*',
            'symlink' => true
        ];

        $repositoryExists = array_filter($composerJson['repositories'], function ($repo) use ($pathRepository) {
            return $repo['type'] === $pathRepository['type']
                && $repo['url'] === $pathRepository['url'];
        });

        if (empty($repositoryExists)) {
            $composerJson['repositories'][] = $pathRepository;
            file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Appended the repository configuration to composer.json.');
        } else {
            $this->info('The repository configuration already exists in composer.json.');
        }
    }

    protected function modifyPackageJson(): void
    {
        $packageJsonPath = 'gildsmith/package.json';

        if (!file_exists($packageJsonPath)) {
            $this->error("package.json not found at $packageJsonPath.");
            return;
        }

        $packageJson = json_decode(file_get_contents($packageJsonPath), true);

        if (!isset($packageJson['workspaces'])) {
            $packageJson['workspaces'] = [];
        }

        $workspacePath = '../packages/npm/*/*';

        if (!in_array($workspacePath, $packageJson['workspaces'])) {
            $packageJson['workspaces'][] = $workspacePath;
            file_put_contents($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Appended the workspace configuration to package.json.');
        } else {
            $this->info('The workspace configuration already exists in package.json.');
        }
    }
}
