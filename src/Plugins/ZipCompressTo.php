<?php

namespace FileMutations\Plugins;

use File;
use Validator;
use ZipArchive;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

/**
 * Class ZipCompressTo
 * @package ZipFly\Plugins
 */
class ZipCompressTo extends AbstractZip
{
    /**
     * @inheritdoc
     */
    public function getMethod()
    {
        return 'compressTo';
    }
    
    /**
     * Compress zip file into destination directory.
     *
     * @param string $zipFilePath The path to the zip file.
     * @param array|string $paths Destination directory
     * @param bool $deleteOriginals
     * @return bool True on success, false on failure.
     */
    public function handle($zipFilePath, $paths = [], $deleteOriginals = false)
    {
        $zipFilePath = $this->getRoot()."/$zipFilePath";
        $paths = (array)$paths;
        
        $zipArchive = app(ZipArchive::class);
        if ($zipArchive->open($zipFilePath, ZipArchive::CREATE) !== true) {
            return false;
        }
        
        $files = [];
        foreach ($paths as $file) {
            $name = null;
            if (is_array($file)) {
                Validator::validate($file, [
                    'file' => 'required:string',
                    'options' => 'array',
                    'options.as' => 'string',
                    'options.mime' => 'string'
                ]);
                
                $name = array_get($file, 'options.as');
                //TODO: Find a package that can resolve a mime-type to a file extension and apply that to the name
                $file = $file['file'];
            }
            $name = $name ?: basename($file);
            
            $file = $this->cleanPath($file);
            $files[] = $file;
            $zipArchive->addFile($file, $name);
        }
        #$filePath = $zipArchive->filename;
        $zipArchive->close();
        
        if ($deleteOriginals) {
            foreach ($files as $file) {
                if (config('zip-fly.clean_temps')) {
                    File::delete($file);
                } else {
                    el("[ZIP_FLY] Prevented deleting '$file'");
                }
            }
        }
        
        return true;
    }
    
    /**
     * @return string
     */
    protected function getRoot()
    {
        $root = '';
        if ($this->filesystem instanceof Filesystem) {
            $adapter = $this->filesystem->getAdapter();
            if ($adapter instanceof Local) {
                $root = $adapter->getPathPrefix();
                $root = '/'.trim($root, '/');
            }
        }
        
        return $root;
    }
}
