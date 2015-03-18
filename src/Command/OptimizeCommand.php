<?php namespace Consolle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class OptimizeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'optimize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize the framework for better performance';

    /**
     * Execute command
     */
    public function fire()
    {
        $composer  = new \Consolle\Foundation\Composer($this->app['files']);

        $this->info('Generating optimized class loader');

        if ($this->option('psr'))
            $composer->dumpAutoloads();
        else
            $composer->dumpOptimized();

        /*
        if ($this->option('force') || ! $this->laravel['config']['app.debug'])
        {
            $this->info('Compiling common classes');

            $this->compileClasses();
        }
        else
        {
            $this->call('clear-compiled');
        }
        /**/
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            //['force', null, InputOption::VALUE_NONE, 'Force the compiled class file to be written.'],

            ['psr', null, InputOption::VALUE_NONE, 'Do not optimize Composer dump-autoload.'],
        );
    }
}