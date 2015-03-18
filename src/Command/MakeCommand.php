<?php namespace Consolle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MakeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a command to consolle.';

    /**
     * Execute command
     */
    public function fire()
    {
        $name  = $this->argument('name');
        $class = sprintf('%sCommand', studly_case($name));

        $file_template = __DIR__ . '/../Resources/command.txt';
        $file_target   = app_path('Commands/' . $class . '.php');

        $template = $this->app['files.template'];
        $template->file($file_template, $file_target);
        $template->param('class', $class);

        $this->info(sprintf('Created the command %s', $class));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputOption::VALUE_REQUIRED, 'Name to new command', null],
        ];
    }
}