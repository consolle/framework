<?php namespace Consolle\Command;

class RestartCommand extends Command
{

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'restart';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Restart process daemons after their current job";

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
        $this->app['cache']->forever(Daemon::restartID, time());

		$this->info('Broadcasting restart signal.');
	}
}
