<?php namespace Consolle\Command;

class StopCommand extends Command
{

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'stop';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Stop process daemons after their current job";

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
        $this->app['cache']->forever(Daemon::restartID, time());

		$this->info('Broadcasting stop signal.');
	}
}
