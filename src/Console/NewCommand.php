<?php

namespace AHDCreative\AHDPressInstaller\Console;

use RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class NewCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('new');
        $this->setDescription('Create a new AHDPress Theme');
        $this->addArgument('folder', InputArgument::REQUIRED);
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'The name of your theme', false);
        $this->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git Repository');
        $this->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch that should be created for a new repository', $this->defaultBranch());
        $this->addOption('wordpress', null, InputOption::VALUE_NONE, 'Install Wordpress.');
        $this->addOption('compiler', null, InputOption::VALUE_OPTIONAL, 'Compiling tool can be either be mix (Laravel Mix) or esbuild.', 'mix');
        $this->addOption('dbname', null, InputOption::VALUE_OPTIONAL, 'The name of your database.');
        $this->addOption('dbuser', null, InputOption::VALUE_OPTIONAL, 'The name of your database user.', 'root');
        $this->addOption('dbpass', null, InputOption::VALUE_OPTIONAL, 'The password of your database.', 'root');
        $this->addOption('dbhost', null, InputOption::VALUE_OPTIONAL, 'The host of your database.', 'localhost');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commands = [];

        $output->write(PHP_EOL . "<fg=green>
    _    _   _ ____  ____                    
   / \  | | | |  _ \|  _ \ _ __ ___  ___ ___ 
  / _ \ | |_| | | | | |_) | '__/ _ \/ __/ __|
 / ___ \|  _  | |_| |  __/| | |  __/\__ \__ \
/_/   \_\_| |_|____/|_|   |_|  \___||___/___/'</>" . PHP_EOL . PHP_EOL . PHP_EOL);
        $installWordpress = ($input->getOption('wordpress') || (new SymfonyStyle($input, $output))->confirm(
                'Want to install wordpress as well?', false
            ));

        $compiler = $input->getOption('compiler');
        $folder = $input->getArgument('folder');
        $slug = $this->determineSlug($folder);
        $prefix = $this->determineSlug($folder, true);
        $workingDirectory = $folder !== '.' ? getcwd() . '/' . $folder : '.';

        if ($installWordpress) {
            $this->installWordpress($workingDirectory, $input, $output);
            $workingDirectory = $workingDirectory . "/wp-content/themes/$slug";
            $commands[] = "mkdir \"$workingDirectory\"";
        } else {
            $commands[] = "mkdir \"$workingDirectory\"";
        }

        $commands[] = "cd \"$workingDirectory\"";
        $commands[] = "git clone -b main https://github.com/ahdcreative/ahdpress.git . --q";

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            if ($compiler === 'esbuild') {
                $this->replaceFilesWithStubs($workingDirectory, 'esbuild', ['package.json', 'postcss.config.js']);
                $this->deleteFiles($workingDirectory, ['webpack.mix.js', 'mix-manifest.json']);
            }

            if ($name = $input->getOption('name')) {
                $this->replaceInFile('AHDPress', $name, $workingDirectory . '/style.css');
                $this->replaceInFile('ahdpress', $prefix, $workingDirectory . '/style.css');

                $this->replaceInFile('ahdpress_', $prefix . '_', $workingDirectory . '/functions.php');
                $this->replaceInFile('ahdpress_', $prefix . '_', $workingDirectory . '/header.php');
                $this->replaceInFile('ahdpress_', $prefix . '_', $workingDirectory . '/footer.php');

                $this->replacePackageJsonInfo($workingDirectory . '/package.json.stub', 'name', $folder);
                $this->replacePackageJsonInfo($workingDirectory . '/package.json.stub', 'text_domain', $folder);

                $this->replaceInFile('https://github.com/ahdcreative/ahdpress', 'https://github.com/username/' . $folder, $workingDirectory . '/package.json.stub');
                $this->replaceInFile('ahdpress.test', $folder . 'test', $workingDirectory . '/package.json.stub');

                if (file_exists($workingDirectory . '/ahdpress.json')) {
                    rename($workingDirectory . '/ahdpress.json', $workingDirectory . '/' . $slug . '.json');
                }
            }

            $this->replaceThemeHeader($workingDirectory . '/style.css', 'Version', '0.1.0');
            $this->replaceThemeHeader($workingDirectory . '/style.css', 'Description', 'A WordPress theme made with AHDPress');
            $this->replacePackageJsonInfo($workingDirectory . '/package.json.stub', 'version', '0.1.0');

            if ($installWordpress) {
                $this->replaceInFile('database_name_here', $input->getOption('dbname') ?? $prefix, $workingDirectory . '/../../../wp-config.php');
                $this->replaceInFile('username_here', $input->getOption('dbuser'), $workingDirectory . '/../../../wp-config.php');
                $this->replaceInFile('password_here', $input->getOption('dbpass'), $workingDirectory . '../../../wp-config.php');
                $this->replaceInFile('localhost', $input->getOption('dbhost'), $workingDirectory . '/../../../wp-config.php');
                $this->replaceInFile("define( 'WP_DEBUG', false );", "define( 'WP_DEBUG', false );\ndefine( 'WP_ENVIRONMENT_TYPE', 'development' );", $workingDirectory . '/../../../wp-config.php');
            }

            $finalCommands = ["cd \"$workingDirectory\""];

            if (PHP_OS_FAMILY == 'Windows') {
                $finalCommands[] = "rmdir /S /Q .git";
            } else {
                $finalCommands[] = "rm -rf .git";
            }

            $finalCommands[] = "npm install --q --no-progress";

            $this->runCommands($finalCommands, $input, $output);

            if ($input->getOption('git')) {
                $this->createRepository($workingDirectory, $input, $output);
            }

            $output->writeln(PHP_EOL . '<info> Your theme is installed here:' . $workingDirectory . '<info>' . PHP_EOL);
            $output->writeln(PHP_EOL . '<comment>Your boilerplate is ready! Go create something beautiful!</comment>' . PHP_EOL);
        }

        return $process->getExitCode();
    }

    protected function runCommands($commands, InputInterface $input, OutputInterface $output, array $env = []): Process
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, $env, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('Warning! ' . $e->getMessage());
            }
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write('    ' . $line);
        });

        return $process;
    }

    protected function replaceInFile(string $search, string $replace, string $file): void
    {
        file_put_contents($file, str_replace($search, $replace, file_get_contents($file)));
    }

    protected function replaceThemeHeader(string $stylesheet, string $header, string $value): void
    {
        $content = file_get_contents($stylesheet);

        $content = preg_replace('/' . $header . ': (.*)/', $header . ': ' . $value, $content);

        file_put_contents($stylesheet, $content);
    }

    protected function replaceFilesWithStubs(string $workingDirectory, string $stubFolder, array $stubs): void
    {
        foreach ($stubs as $stub) {
            file_put_contents($workingDirectory . '/' . $stub, file_get_contents(__DIR__ . '/../../stubs/' . $stubFolder . '/' . '.stub'));
        }
    }

    protected function replacePackageJsonInfo(string $packageJson, string $key, string $value): void
    {
        $content = file_get_contents($packageJson);
        $content = preg_replace('/"' . $key . '": (.*)/', '"' . $key . '": "' . $value . '",', $content);

        file_put_contents($packageJson, $content);
    }

    protected function deleteFiles(string $workingDirectory, array $files): void
    {
        foreach ($files as $file) {
            unlink($workingDirectory . '/' . $file);
        }
    }

    protected function installWordpress(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $commands = [
            "mkdir $directory",
            "cd $directory",
            "curl -O https://wordpress.org/latest.tar.gz --no-progress-meter",
            "tar -zxf latest.tar.gz",
            "rm latest.tar.gz",
            "cd wordpress",
            "cp -rf . ..",
            "cd ..",
            "rm -R wordpress",
            "cp wp-config-sample.php wp-config.php"
        ];

        $this->runCommands($commands, $input, $output);
    }

    protected function createRepository(string $directory, InputInterface $input, OutputInterface $output): void
    {
        chdir($directory);

        $branch = $input->getOption('branch') ?: $this->defaultBranch();

        $commands = [
            "git init -q",
            "git add .",
            'git commit -q -m "Initial Commit"',
            "git branch -M $branch",
        ];

        $this->runCommands($commands, $input, $output);
    }

    protected function defaultBranch(): string
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);
        $process->run();
        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'main';
    }

    protected function determineSlug($folder, $sanitize = false)
    {
        $folder = explode('/', $folder);

        if (!$sanitize) {
            return end($folder);
        }

        return str_replace('-', '_', end($folder));
    }
}