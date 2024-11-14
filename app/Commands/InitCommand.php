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
            /* Core project */
            'Pulling project' => fn() => $this->pullProject(),
            'Copying .env file' => fn() => $this->copyEnvFile(),
            'Regenerating the application key' => fn() => $this->regenerateAppKey(),

            /* Development environment */
            'Creating directories' => fn() => $this->createDirectories(),

            /* Composer */
            'Modifying composer.json' => fn() => $this->modifyComposerJson(),
            'Pulling default packages' => fn() => $this->pullPackages(),
            'Installing Composer dependencies' => fn() => $this->installComposerDependencies(),

            /* NPM */
            'Modifying package.json' => fn() => $this->modifyNpmJson(),
            'Installing NPM dependencies' => fn() => $this->installNpmDependencies(),
        ];

        $totalTasks = count($tasks);
        $currentTask = 1;

        foreach ($tasks as $key => $task) {
            $this->newLine();
            $this->line("($currentTask/$totalTasks) $key...");
            $task();
            $currentTask++;
        }

        $this->newLine();
        $this->info('Gildsmith environment setup complete.');
    }

    protected function pullProject(): void
    {
        if (is_dir('gildsmith')) {
            $this->warn("'gildsmith' directory already exists. Skipping.");
            return;
        }

        $process = new Process(['git', 'clone', 'git@github.com:gildsmith/gildsmith.git', 'gildsmith']);
        $process->run($this->processOutputHandler());

        $process->isSuccessful()
            ? $this->error('Failed to clone the repository.')
            : $this->info('Project cloned successfully.');
    }

    protected function processOutputHandler(): callable
    {
        return function ($type, $buffer): void {
            if ($this->output->isVerbose()) {
                echo $buffer;
            }
        };
    }

    protected function copyEnvFile(): void
    {
        $envPath = 'gildsmith/.env';
        $envExamplePath = 'gildsmith/.env.example';

        if (file_exists($envPath)) {
            $this->warn(".env file already exists. Skipping.");
            return;
        }

        if (!file_exists($envExamplePath)) {
            $this->error(".env.example file not found. Skipping.");
            return;
        }

        copy($envExamplePath, $envPath)
            ? $this->info(".env file created successfully from .env.example.")
            : $this->error("Failed to copy .env.example to .env.");
    }

    protected function regenerateAppKey(): void
    {
        $keyGenerateProcess = new Process(['php', 'artisan', 'key:generate'], 'gildsmith');
        $keyGenerateProcess->run($this->processOutputHandler());

        $keyGenerateProcess->isSuccessful()
            ? $this->info('Application key regenerated successfully.')
            : $this->error('Failed to regenerate the application key.');
    }

    protected function createDirectories(): void
    {
        $directories = ['packages/composer', 'packages/npm'];

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                $this->warn("'$directory' directory already exists. Skipping.");

            } else {
                mkdir($directory, 0755, true)
                    ? $this->info("Created '$directory' directory.")
                    : $this->error("Failed to create '$directory' directory.");
            }
        }
    }

    protected function pullPackages(): void
    {
        $repositories = [
            'core-api' => 'git@github.com:gildsmith/core-api.git',
            'profile-api' => 'git@github.com:gildsmith/profile-api.git',
        ];

        foreach ($repositories as $name => $repo) {
            $targetDir = "packages/composer/gildsmith/$name";

            if (is_dir($targetDir)) {
                $this->warn("Directory '$targetDir' already exists. Skipping.");
                continue;
            }

            $this->info("Cloning $name into $targetDir...");
            $cloneProcess = new Process(['git', 'clone', $repo, $targetDir]);
            $cloneProcess->run($this->processOutputHandler());

            $cloneProcess->isSuccessful()
                ? $this->info("Cloned $name successfully.")
                : $this->error("Failed to clone $name.");
        }
    }

    protected function modifyComposerJson(): void
    {
        $composerJsonPath = 'gildsmith/composer.json';

        $pathRepository = [
            'type' => 'path',
            'url' => '../packages/composer/*/*',
            'symlink' => true
        ];

        if (!file_exists($composerJsonPath)) {
            $this->error("composer.json not found at $composerJsonPath.");
            return;
        }

        $composerJson = json_decode(file_get_contents($composerJsonPath), true);

        if (!isset($composerJson['repositories'])) {
            $composerJson['repositories'] = [];
        }

        $repositoryExists = array_filter($composerJson['repositories'], function ($repo) use ($pathRepository) {
            return $repo['type'] === $pathRepository['type']
                && $repo['url'] === $pathRepository['url'];
        });

        if (empty($repositoryExists)) {
            $composerJson['repositories'][] = $pathRepository;
            file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Appended the repository configuration to composer.json.');

        } else {
            $this->warn('The repository configuration already exists in composer.json. Skipping.');
        }
    }

    protected function modifyNpmJson(): void
    {
        $packageJsonPath = 'gildsmith/package.json';
        $workspacePath = '../packages/npm/*/*';

        if (!file_exists($packageJsonPath)) {
            $this->error("package.json not found at $packageJsonPath.");
            return;
        }

        $packageJson = json_decode(file_get_contents($packageJsonPath), true);

        if (!isset($packageJson['workspaces'])) {
            $packageJson['workspaces'] = [];
        }

        if (!in_array($workspacePath, $packageJson['workspaces'])) {
            $packageJson['workspaces'][] = $workspacePath;
            file_put_contents($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Appended the workspace configuration to package.json.');

        } else {
            $this->warn('The workspace configuration already exists in package.json. Skipping.');
        }
    }

    protected function installComposerDependencies(): void
    {
        $process = new Process(['composer', 'install'], 'gildsmith');
        $process->run($this->processOutputHandler());

        $process->isSuccessful()
            ? $this->info('Composer dependencies installed successfully.')
            : $this->error('Failed to install Composer dependencies.');
    }

    protected function installNpmDependencies(): void
    {
        $process = new Process(['npm', 'install'], 'gildsmith');
        $process->run($this->processOutputHandler());

        $process->isSuccessful()
            ? $this->info('Composer dependencies installed successfully.')
            : $this->error('Failed to install NPM dependencies.');
    }
}
