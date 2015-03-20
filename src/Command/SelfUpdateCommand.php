<?php namespace Consolle\Command;

use Consolle\Utils\RemoteFilesystem;
use Consolle\PharInfo;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SelfUpdateCommand extends Command
{
    const OLD_INSTALL_EXT = '-old.phar';

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'self-update';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Updates PHAR to the latest version";

	/**
	 * Execute the console command.
	 *
	 * @return int|bool
	 */
	public function fire()
	{
        $error        = $this->app['error'];
        $files        = $this->app['files'];
        $json_file    = base_path('update.json');
        $application  = $this->app['application'];
        $bkpDir       = root_path('backups');
        $project_file = root_path($application->name . '.phar');
        $remote       = new RemoteFilesystem();

        // Check file json update
        if (file_exists($json_file) != true)
            $error->make('%s update configuration not found', $application->title);
        $json = json_decode(file_get_contents($json_file));

        // Forcar criacao
        $files->force($bkpDir);

        // Check for permissions in local backup
        if (!is_writable($bkpDir))
            $error->make('%s update failed: the "%s" directory used to download the temp file could not be written', $application->title, $bkpDir);
        if (!is_writable($project_file)) {
            $error->make('%s update failed: the "%s" file could not be written', $application->title, $project_file);
        }

        // Check version
        $version_url    = trim($json->url->version);
        $last_version   = trim($remote->getContents($version_url, false));
        $update_version = $this->argument('version') ?: $last_version;

        // Validate version sintaxe
        if (isset($json->versionMask))
        {
            if (preg_match($json->versionMask, $update_version) && $update_version !== $last_version)
            {
                $this->error('You can not update to a specific SHA-1 as those phars are not available for download');
                return true;
            }
        }

        // Check has updated
        if (PharInfo::VERSION === $update_version)
        {
            $this->info(sprintf('You are already using %s version %s', $application->title, $update_version));
            return false;
        }

        $temp_file  = root_path($application->name . '-temp.phar');
        $bkp_file   = sprintf('%s/%s-%s%s',
            $bkpDir,
            strtr(PharInfo::RELEASE_DATE, ' :', '_-'),
            preg_replace('{^([0-9a-f]{7})[0-9a-f]{33}$}', '$1', PharInfo::VERSION),
            self::OLD_INSTALL_EXT
        );

        // Atualizar
        $this->info(sprintf("Updating to version %s.", $update_version));
        $file_remote = sprintf('%s/%s/%s', trim($json->url->download), $update_version, basename($project_file));
        $remote->copy($file_remote, $temp_file, true);
        if (!file_exists($temp_file))
        {
            $this->error(sprintf('The download of the new %s version failed for an unexpected reason', $application->title));
            return true;
        }

        // Trocar arquivo
        if ($err = $this->setLocalPhar($project_file, $temp_file, $bkp_file))
        {
            $this->error(sprintf('The file is corrupted (%a).', $err->getMessage()));
            $this->error('Please re-run the self-update command to try again');
            return true;
        }

        return false;
	}

    /**
     * Trocar arquivo
     * @param $localFilename
     * @param $newFilename
     * @param null $backupTarget
     * @return \PharException|\UnexpectedValueException
     * @throws \Exception
     */
    protected function setLocalPhar($localFilename, $newFilename, $backupTarget = null)
    {
        try
        {
            @chmod($newFilename, fileperms($localFilename));
            if (!ini_get('phar.readonly'))
            {
                // test the phar validity
                $phar = new \Phar($newFilename);
                // free the variable to unlock the file
                unset($phar);
            }

            // copy current file into installations dir
            if ($backupTarget && file_exists($localFilename))
            {
                @copy($localFilename, $backupTarget);
            }

            rename($newFilename, $localFilename);

            return true;
        }
        catch (\Exception $e)
        {
            if ($backupTarget) {
                @unlink($newFilename);
            }
            if (!$e instanceof \UnexpectedValueException && !$e instanceof \PharException)
            {
                throw $e;
            }

            return $e;
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['version', InputOption::VALUE_OPTIONAL, 'Version to update', null],
        ];
    }
}
