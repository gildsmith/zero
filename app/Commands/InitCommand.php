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
            'Pulling project' => fn() => $this->pullProject(),
            'Creating directories' => fn() => $this->createDirectories(),
            'Modifying composer.json' => fn() => $this->modifyComposerJson(),
            'Modifying package.json' => fn() => $this->modifyNpmJson(),
            'Installing Composer dependencies' => fn() => $this->installComposerDependencies(),
            'Installing NPM dependencies' => fn() => $this->installNpmDependencies(),
        ];

        // Iterate over tasks and display a progress bar
        $totalTasks = count($tasks);
        $currentTask = 1;

        foreach ($tasks as $key => $task) {
            $this->newLine();
            $this->info("($currentTask/$totalTasks) $key...");
            $task();
            $currentTask++;
        }

        $this->newLine();
        $this->info('Gildsmith environment setup complete.');
    }

    /**
     * Clones the project repository from GitHub.
     */
    protected function pullProject(): void
    {
        // Skip the step if the directory already exists.
        if (is_dir('gildsmith')) {
            $this->warn("'gildsmith' directory already exists.");
            return;
        }

        // Clone the directory
        $this->info('Cloning the Gildsmith project from GitHub...');
        $process = new Process(['git', 'clone', 'git@github.com:gildsmith/gildsmith.git', 'gildsmith']);
        $process->run($this->processOutputHandler());

        // Output
        $process->isSuccessful()
            ? $this->error('Failed to clone the repository.')
            : $this->info('Project cloned successfully.');
    }

    /**
     * Function to handle process output based on verbose mode.
     */
    protected function processOutputHandler(): callable
    {
        return function ($type, $buffer) {
            if ($this->output->isVerbose()) {
                echo $buffer;
            }
        };
    }

    /**
     * Creates the necessary directories for the project.
     */
    protected function createDirectories(): void
    {
        $directories = ['packages/composer', 'packages/npm'];

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                $this->warn("'$directory' directory already exists.");

            } elseif (mkdir($directory, 0755, true)) {
                $this->info("Created '$directory' directory.");

            } else {
                $this->error("Failed to create '$directory' directory.");
            }
        }
    }

    /**
     * Modifies the composer.json to include custom repository configuration.
     */
    protected function modifyComposerJson(): void
    {
        $composerJsonPath = 'gildsmith/composer.json';

        $pathRepository = [
            'type' => 'path',
            'url' => '../packages/composer/*/*',
            'symlink' => true
        ];

        // Skip the step if composer.json doesn't exist
        if (!file_exists($composerJsonPath)) {
            $this->error("composer.json not found at $composerJsonPath.");
            return;
        }

        // Following code checks whether composer.json includes
        // local repository, and adds it if it doesn't.
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
            $this->warn('The repository configuration already exists in composer.json.');
        }
    }

    /**
     * Modifies the package.json to include custom workspace configuration.
     */
    protected function modifyNpmJson(): void
    {
        $packageJsonPath = 'gildsmith/package.json';
        $workspacePath = '../packages/npm/*/*';

        // Skip the step if package.json doesn't exist
        if (!file_exists($packageJsonPath)) {
            $this->error("package.json not found at $packageJsonPath.");
            return;
        }

        // Following code checks whether package.json includes
        // local workspace, and adds it if it doesn't.
        $packageJson = json_decode(file_get_contents($packageJsonPath), true);

        if (!isset($packageJson['workspaces'])) {
            $packageJson['workspaces'] = [];
        }


        if (!in_array($workspacePath, $packageJson['workspaces'])) {
            $packageJson['workspaces'][] = $workspacePath;
            file_put_contents($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Appended the workspace configuration to package.json.');

        } else {
            $this->warn('The workspace configuration already exists in package.json.');
        }
    }

    /**
     * Installs Composer dependencies.
     */
    protected function installComposerDependencies(): void
    {
        $process = new Process(['composer', 'install'], 'gildsmith');
        $process->run($this->processOutputHandler());

        $process->isSuccessful()
            ? $this->info('Composer dependencies installed successfully.')
            : $this->error('Failed to install Composer dependencies.');
    }

    /**
     * Installs NPM dependencies.
     */
    protected function installNpmDependencies(): void
    {
        $process = new Process(['npm', 'install'], 'gildsmith');
        $process->run($this->processOutputHandler());

        $process->isSuccessful()
            ? $this->info('Composer dependencies installed successfully.')
            : $this->error('Failed to install NPM dependencies.');
    }
}
