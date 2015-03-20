<?php namespace Consolle;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Consolle\Command;
use Consolle\Utils\ErrorHandler;

class Application extends BaseApplication implements \Consolle\Contracts\Kernel
{
    /**
     * Name Application
     * @var string
     */
    public $name   = 'consolle';

    /**
     * Title Application
     * @var string
     */
    public $title   = 'Consolle';

    /**
     * Version Application
     * @var string
     */
    public $version = '1.0.0';

    /**
     * Application
     * @var \Illuminate\Contracts\Foundation\Application
     */
    public $app;

    /**
     * The bootstrap classes for the application.
     *
     * @var array
     */
    protected $bootstrappers = [
        'Consolle\Foundation\Bootstrap\DetectEnvironment',
        'Consolle\Foundation\Bootstrap\LoadConfiguration',
        'Consolle\Foundation\Bootstrap\RegisterFacades',
        'Consolle\Foundation\Bootstrap\RegisterProviders',
        'Consolle\Foundation\Bootstrap\BootProviders',
    ];

    /**
     * Constructor
     */
    public function __construct(\Illuminate\Contracts\Foundation\Application $app)
    {
        $this->app = $app;
        $app->instance('application', $this);

        if (function_exists('ini_set') && extension_loaded('xdebug'))
        {
            ini_set('xdebug.show_exception_trace', false);
            ini_set('xdebug.scream', false);
        }

        // Config TimeZone
        if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get'))
            date_default_timezone_set(@date_default_timezone_get());

        ErrorHandler::register();
        $this->bootstrap();

        parent::__construct($this->title, $this->version);
    }

    /**
     * Bootstrap the application for HTTP requests.
     *
     * @return void
     */
    public function bootstrap()
    {
        if ( ! $this->app->hasBeenBootstrapped())
            $this->app->bootstrapWith($this->bootstrappers);
        $this->app->loadDeferredProviders();
    }

    /**
     * {@inheritDoc}
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $output)
        {
            $formatter = new OutputFormatter(true, []);
            $output    = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, null, $formatter);
        }

        return parent::run($input, $output);
    }

    /**
     * {@inheritDoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        if (version_compare(PHP_VERSION, '5.3.2', '<'))
            $output->writeln('<warning>Consolle only officially supports PHP 5.3.2 and above, you will most likely encounter problems with your PHP '.PHP_VERSION.', upgrading is strongly recommended.</warning>');

        // switch working dir
        if ($newWorkDir = $this->getNewWorkingDir($input))
        {
            $oldWorkingDir = getcwd();
            chdir($newWorkDir);
        }

        $result = parent::doRun($input, $output);

        if (isset($oldWorkingDir))
            chdir($oldWorkingDir);

        return $result;
    }

    /**
     * @param  InputInterface    $input
     * @return string
     * @throws \RuntimeException
     */
    private function getNewWorkingDir(InputInterface $input)
    {
        $workingDir = $input->getParameterOption(array('--working-dir', '-d'));
        if (false !== $workingDir && !is_dir($workingDir))
            throw new \RuntimeException('Invalid working directory specified.');

        return $workingDir;
    }

    /**
     * {@inheritDoc}
     */
    public function renderException($exception, $output)
    {
        if ($output instanceof ConsoleOutputInterface)
            parent::renderException($exception, $output->getErrorOutput());
        else
            parent::renderException($exception, $output);
    }

    /**
     * Return info Help
     * @return string
     */
    public function getHelp()
    {
        return self::getLogo() . PHP_EOL . parent::getHelp();
    }

    /**
     * Rerurn logo ASCII
     * @return string
     */
    public static function getLogo()
    {
        // Verificar se foi definido a logo do app
        $file_logo_app = app_path('logo.txt');
        if (file_exists($file_logo_app))
            return file_get_contents($file_logo_app);

        // Logo do framework
        $file_logo = __DIR__ . '/Resources/logo.txt';
        if (file_exists($file_logo))
            return file_get_contents($file_logo);
        return '';
    }

    /**
     * Initializes all the commands default
     */
    protected function getDefaultCommands()
    {
        $commands   = parent::getDefaultCommands();
        $commands[] = new Command\AboutCommand($this->app);
        $commands[] = new Command\StopCommand($this->app);

        // Comandos da aplicacao
        $cmds = config('app.commands', []);
        foreach ($cmds as $cid => $cClass)
        {
            $cid = sprintf('command.%s', $cid);
            $this->app->bind($cid, $cClass);
            $commands[] = $this->app[$cid];
        }

        // verificar se deve incluir o comando make:command
        if (('phar:' !== substr(__FILE__, 0, 5)) && (class_exists('\Consolle\Command\MakeCommand')))
            $commands[] = new \Consolle\Command\MakeCommand($this->app);

        // verificar se deve incluir o comando optimize
        if (('phar:' !== substr(__FILE__, 0, 5)) && (class_exists('\Consolle\Command\OptimizeCommand')))
            $commands[] = new \Consolle\Command\OptimizeCommand($this->app);

        // verificar se deve incluir o comando self-compiler
        if (('phar:' !== substr(__FILE__, 0, 5)) && (class_exists('\Consolle\Command\SelfCompilerCommand')))
            $commands[] = new \Consolle\Command\SelfCompilerCommand($this->app);

        // Verificar se deve incluir o comando self-update
        if (('phar:' === substr(__FILE__, 0, 5)) && (class_exists('\Consolle\Command\SelfCompilerCommand')) && (file_exists(base_path('update.json'))))
            $commands[] = new \Consolle\Command\SelfUpdateCommand($this->app);

        return $commands;
    }

    /**
     * {@inheritDoc}
     */
    public function getLongVersion()
    {
        return parent::getLongVersion();
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();
        //$definition->addOption(new InputOption('--profile', null, InputOption::VALUE_NONE, 'Display timing and memory usage information'));
        $definition->addOption(new InputOption('--working-dir', '-d', InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'));

        return $definition;
    }

    /**
     * {@inheritDoc}
     *
    protected function getDefaultHelperSet()
    {
        $helperSet = parent::getDefaultHelperSet();
        $helperSet->set(new DialogHelper());

        return $helperSet;
    }/**/
}