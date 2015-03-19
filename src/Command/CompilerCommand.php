<?php namespace Consolle\Command;

use Consolle\Application;
use Symfony\Component\Finder\Finder;

class CompilerCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'self:compiler';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compiler application in PHAR.';

    /**
     * @var \Consolle\Application
     */
    protected $application;

    /**
     * Alias do projeto PHAR
     * @var string
     */
    protected $alias    = '';

    /**
     * Arquivo output do projeto PHAR
     * @var string
     */
    protected $pharFile = '';

    /**
     * lista de parametros para ser substituido nos arquivos
     * @var array
     */
    protected $params = [];

    /**
     * Execute command
     */
    public function fire()
    {
        $this->application = $this->app['application'];
        $this->alias       = $this->application->name . '.phar';
        $this->pharFile    = base_path($this->alias);

        $this->info('Compiling Consolle Application');
        $this->info('Application:');
        $this->info('Name.....: ' . $this->application->name);
        $this->info('Title....: ' . $this->application->title);
        $this->info('version..: ' . $this->application->version);
        $this->info('output...: ' . $this->pharFile);
        $this->info('-----------------------------------------------------------');

        if (file_exists($this->pharFile))
            unlink($this->pharFile);

        $phar = new \Phar($this->pharFile, 0, $this->alias);
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        $phar->startBuffering();

        // Adicionar arquivos
        $this->add_files($phar);

        // Adiconar arquivo bin
        $this->addComposerBin($phar);

        // Adicionar stub
        $phar->setStub($this->getStub());

        // Adicionar arquivo de licenca
        $this->addFile($phar, new \SplFileInfo(base_path('LICENSE')), false);

        $phar->stopBuffering();
        unset($phar);

        $this->info('-----------------------------------------------------------');
        $this->info('+ Compiled');
    }

    /**
     * Adicionar arquivos
     * @param \Phar $phar
     */
    public function add_files(\Phar $phar)
    {
        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->name('logo.txt')
            ->name('about.txt')
            ->exclude('Tests')
            ->exclude('tests')
            ->exclude('docs')
            ->exclude('Autoload')
            ->notName('Compiler.php')
            ->in(base_path());

        foreach ($finder as $file)
            $this->addFile($phar, $file, true);
    }

    /**
     * Adicionar arquivo do phar
     * @param $phar
     * @param \SplFileInfo $file
     * @param bool $strip
     */
    private function addFile(\Phar $phar, \SplFileInfo $file, $strip = true)
    {
        $this->info('add file: ' . $file->getPathname());

        $path    = str_replace(base_path(), '', $file->getRealPath());
        $content = file_get_contents($file);

        // Tratar espacos?
        if ($strip)
            $content = $this->stripWhitespace($content);

        // Eh arquivo de licenca?
        if ('LICENSE' === basename($file))
            $content = "\n" . $content . "\n";

        // Tratar parametros
        foreach ($this->params as $pk => $pv)
        {
            $pk = sprintf('@%s@', $pk);
            $content = str_replace($pk, $pv, $content);
        }

        $phar->addFromString($path, $content);
    }

    /**
     * Adicionar arquivo bin
     * @param $phar
     */
    private function addComposerBin(\Phar $phar)
    {
        $this->info('add file BIN');

        $content = file_get_contents(base_path('artisan'));
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $phar->addFromString('artisan', $content);
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * @param  string $source A PHP string
     * @return string The PHP string with the whitespace removed
     */
    private function stripWhitespace($source)
    {
        // Verificar se token_get_all existe
        if (!function_exists('token_get_all'))
            return $source;

        $output = '';
        foreach (token_get_all($source) as $token)
        {
            if (is_string($token))
            {
                $output .= $token;
            } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT)))
            {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0])
            {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }

    /**
     * Montar arquivo Stub (arquivo de configuracao do phar)
     * @return string
     */
    private function getStub()
    {
        $this->info('add file STUB');

        $content = file_get_contents(__DIR__ . '/../Resources/compiler_stub.txt');
        $content = str_replace('{{alias}}', $this->alias, $content);

        return $content;
    }
}