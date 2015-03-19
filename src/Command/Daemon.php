<?php namespace Consolle\Command;


class Daemon
{
    const restartID = 'consolle:command:restart';

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The cache repository implementation.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Memory max limit
     * @var int
     */
    public $memory = 128;

    /**
     * Time sleep in seconds
     * @var int
     */
    public $sleep = 3;

    /**
     * Construtor
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(\Illuminate\Contracts\Foundation\Application $app)
    {
        $this->app   = $app;
        $this->cache = $app['cache']->driver();
    }

    /**
     * Executet command
     * @param callable $closure
     */
    public function run(\Closure $closure)
    {
        $lastRestart = $this->getTimestampRestartCommand();

        while (true)
        {
            try
            {
                // Execute
                $closure();
            }
            catch (\Exception $e)
            {
                $this->error($e);
            }

            $this->sleep();

            // Test memory
            if ($this->memoryExceeded())
                $this->stop();

            // Test Restart
            if ($this->testShouldRestart($lastRestart))
                $this->stop();
        }
    }

    /**
     * Test memory limit
     * @return bool
     */
    protected function memoryExceeded()
    {
        return (memory_get_usage() / 1024 / 1024) >= $this->memory;
    }

    /**
     * Stop process
     *
     * @return void
     */
    public function stop()
    {
        die;
    }

    /**
     * Register erro
     * @param \Exception $e
     */
    protected function error(\Exception $e)
    {
        $str  = sprintf("Message: %s\r\n", $e->getMessage());
        $str .= sprintf("Code: %s\r\n", $e->getCode());
        $str .= sprintf("File: %s (line: %s)\r\n", $e->getFile(), $e->getLine());
        $str .= sprintf("Trade: %s\r\n", print_r($e->getTrace()));

        $this->app['log']->error($str);
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @return void
     */
    protected function sleep()
    {
        sleep($this->sleep);
    }

    /**
     * Get the last restart timestamp, or null.
     *
     * @return int|null
     */
    protected function getTimestampRestartCommand()
    {
        if ($this->cache)
            return $this->cache->get(self::restartID);

        return null;
    }

    /**
     * Determine if the process should restart.
     *
     * @param  int|null  $lastRestart
     * @return bool
     */
    protected function testShouldRestart($lastRestart)
    {
        return $this->getTimestampRestartCommand() != $lastRestart;
    }
}