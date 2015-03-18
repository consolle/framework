<?php namespace Consolle\IO;

class Filesystem extends \Illuminate\Filesystem\Filesystem
{
    /**
     * Get base path of file or directory
     * @return string
     */
    public function path($filename)
    {
        $info = pathinfo($filename);
        return $info['dirname'];
    }

    /**
     * Remove path base of filename
     * @param $filename
     * @return string
     */
    public function pathInBase($filename)
    {
        $filename  = str_replace('\\', '/', $filename);
        $path_root = str_replace('\\', '/', base_path() . '/');

        return str_replace($path_root, '', $filename);
    }

    /**
     * Alias of makeDirectory
     * @return boolean
     */
    public function force($path, $mode = 0777, $recursive = true)
    {
        if ($this->exists($path))
            return true;
        return $this->makeDirectory($path, $mode, $recursive);
    }

    /**
     * Combine two paths
     * @return string
     */
    public function combine($path1, $path2, $div = '/')
    {
        $path1 .= (($path1[strlen($path1) - 1] != $div) ? $div : '');
        return $path1 . $path2;
    }

    /**
     * Get filename with or not extension
     * @return string
     */
    public function fileName($filename, $withExt = true)
    {
        return ($withExt ? pathinfo($filename, PATHINFO_BASENAME) : pathinfo($filename, PATHINFO_FILENAME));
    }

    /**
     * Rename file with option of make new filename
     * @return string
     */
    public function rename($filename, $newname, $try_another_name = false)
    {
        // Verificar se deve tentar outro nome caso ja exista
        if ($try_another_name)
        {
            $file_mask = preg_replace('/(.[a-zA-Z0-9]+)$/', '_%s\1', $filename);
            $contador = 1;
            while ($this->exists($newname))
            {
                $newname = sprintf($file_mask, $contador);
                $contador += 1;
            }
        }

        $this->copy($filename, $newname);
        $this->delete($filename);

        return $newname;
    }

    /**
     * Synchronize from path with to path
     * @param $fromPath
     * @param $toPath
     * @return boolean
     */
    public function synchronize($fromPath, $toPath, $renames = array())
    {
        // Verificar se fromPath e um diertorio
        if (!$this->isDirectory($fromPath))
            return false;

        // Verificar se deve criar o toPath
        if (!$this->isDirectory($toPath))
            $this->makeDirectory($toPath, 0777, true);

        // Copiar sincronizar estrutura
        $items = new \FilesystemIterator($fromPath, \FilesystemIterator::SKIP_DOTS);
        foreach ($items as $item)
        {
            $target = $toPath . '/' . $item->getBasename();

            if ($item->isDir())
            {
                $path = $item->getPathname();
                if (!$this->synchronize($path, $target, $renames))
                    return false;
            } else {
                // verificar se deve renomear
                foreach ($renames as $old => $new)
                    $target = str_replace($old, $new, $target);

                // Verificar se arquivo existe
                if ($this->exists($target))
                {
                    $hash_file = md5_file($item->getPathname());
                    $hash_dest = md5_file($target);
                    if ($hash_file != $hash_dest) {
                        if (!$this->copy($item->getPathname(), $target))
                            return false;
                    }
                } else {
                    if (!$this->copy($item->getPathname(), $target))
                        return false;
                }
            }
        }
        return true;
    }
}