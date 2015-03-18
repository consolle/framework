<?php namespace Consolle\Command;

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