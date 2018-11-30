<?php

namespace FileMutations\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * Class AbstractZip
 * @package FileMutations\Plugins
 */
abstract class AbstractZip extends AbstractPlugin
{
    /**
     * @param $zipEntryName
     * @return bool
     */
    protected function isDirectory($zipEntryName)
    {
        return substr($zipEntryName, -1) ===  '/';
    }
    
    /**
     * @param $path
     * @return mixed
     */
    protected function cleanPath($path)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
