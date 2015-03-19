<?php namespace Consolle\Compiler;

use Symfony\Component\Finder\Finder;

class Compiler
{
    /**
     * @var \Consolle\Command\Command
     */
    protected $cmd;

    /**
     * @param $pathBase
     */
    public function __construct(\Consolle\Command\Command $cmd)
    {
        $this->cmd = $cmd;
    }

    /**
     * Compilar aplicativo
     * @param string $pharFile
     */
    public function compile($pharFile = 'teste.phar')
    {
        if (file_exists($pharFile))
            unlink($pharFile);

        $phar = new \Phar($pharFile, 0, 'teste.phar');
        $phar->setSignatureAlgorithm(\Phar::SHA1);

        $phar->startBuffering();

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
            ->in($this->pathBase);

        foreach ($finder as $file)
            $this->addFile($phar, $file, false);


        $this->addComposerBin($phar);
        $phar->setStub($this->getStub());
        //$phar->setStub($phar->createDefaultStub('artisan'));

        $this->addFile($phar, new \SplFileInfo($this->pathBase . 'LICENSE'), false);

        $phar->stopBuffering();

        unset($phar);
    }

    private function addFile($phar, $file, $strip = true)
    {
        $path = str_replace($this->pathBase, '', $file->getRealPath());

        $content = file_get_contents($file);
        if ($strip) {
            $content = $this->stripWhitespace($content);
        } elseif ('LICENSE' === basename($file)) {
            $content = "\n".$content."\n";
        }

        if ($path === 'src/Composer/Composer.php') {
            $content = str_replace('@package_version@', $this->version, $content);
            $content = str_replace('@package_branch_alias_version@', $this->branchAliasVersion, $content);
            $content = str_replace('@release_date@', $this->versionDate, $content);
        }

        $phar->addFromString($path, $content);
    }

    private function addComposerBin($phar)
    {
        $content = file_get_contents($this->pathBase . 'artisan');
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
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0]) {
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

    private function getStub()
    {
        $stub = <<<'EOF'
#!/usr/bin/env php
<?php
/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view
 * the license that is located at the bottom of this file.
 */

// Avoid APC causing random fatal errors per https://github.com/composer/composer/issues/264
if (extension_loaded('apc') && ini_get('apc.enable_cli') && ini_get('apc.cache_by_default')) {
    if (version_compare(phpversion('apc'), '3.0.12', '>=')) {
        ini_set('apc.cache_by_default', 0);
    } else {
        fwrite(STDERR, 'Warning: APC <= 3.0.12 may cause fatal errors when running composer commands.'.PHP_EOL);
        fwrite(STDERR, 'Update APC, or set apc.enable_cli or apc.cache_by_default to 0 in your php.ini.'.PHP_EOL);
    }
}

Phar::mapPhar('teste.phar');

EOF;

        /*
        // add warning once the phar is older than 30 days
        if (preg_match('{^[a-f0-9]+$}', $this->version)) {
            $warningTime = time() + 30*86400;
            $stub .= "define('COMPOSER_DEV_WARNING_TIME', $warningTime);\n";
        }/**/

        return $stub . <<<'EOF'
require 'phar://teste.phar/artisan';

__HALT_COMPILER();
EOF;
    }
}
