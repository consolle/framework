<?php namespace Consolle\Command;

use Consolle\Application;

class AboutCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'about';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Short information about Consolle.';

    /**
     * Execute command
     */
    public function fire()
    {
        // Logo
        $this->output->writeln(Application::getLogo());

        // File about
        $file_about = __DIR__ . '/../Resources/about.txt';
        if (file_exists($file_about))
        {
            $this->output->writeln(file_get_contents($file_about));
            return true;
        }

        $this->error('File about not found');
        return false;
    }
}