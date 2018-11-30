<?php

namespace FileMutations\Helpers;

use File;
use Storage;
use Validator;
use League\Flysystem\MountManager;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Validation\ValidationException;
use FileMutations\Providers\FileMutatorProvider;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

/**
 * TODO: Consider making this a trait for Illuminate\Mail\Mailable that includes something like the PayFeedGenerated::compress() method
 *
 * Class TempZip
 * @package FileMutations\Helpers
 */
class TempZip
{
    /**
     * @var string
     */
    protected $root = '';
    
    /**
     * @var string
     */
    protected $tempDir = '';
    
    /**
     * @var FilesystemAdapter
     */
    protected $storage;
    
    /**
     * @var bool
     */
    protected $sleeping = false;
    
    /**
     * TempZip constructor.
     * @param null $tempDir
     */
    public function __construct($tempDir = null)
    {
        if ($tempDir && !File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 755, true);
        }
        $this->root = $tempDir ?: sys_get_temp_dir();
        $this->storage = $this->makeStorage($this->root);
        
        $tempPath = tempnam($this->root, 'tempZip_');
        $this->tempDir = basename($tempPath);
        $this->storage->delete($this->tempDir);
        if (!File::isDirectory($this->root.'/'.$this->tempDir)) {
            $this->storage->makeDirectory($this->tempDir);
        }
    }
    
    /**
     * @return array
     */
    public function __sleep()
    {
        $this->sleeping = true;
        return ['root', 'tempDir', 'sleeping'];
    }
    
    /**
     * @void
     */
    public function __wakeup()
    {
        $this->sleeping = false;
        $this->storage = $this->makeStorage($this->root);
    }
    
    /**
     * @param string $root
     * @return FilesystemAdapter
     */
    protected function makeStorage($root)
    {
        $storage = Storage::createLocalDriver(['root' => $root]);
        $storage = FileMutatorProvider::addPluginToAdapter($storage);
        Storage::set('_temp', $storage);
//        config(['filesystems.disks._temp' => [
//            'driver' => 'local',
//            'root' => $this->root
//        ]]);
//        //Instantiating the FilesystemManager object like this is necessary for it to extend the custom plugin
//        $storage = Storage::disk('_temp');
        
        return $storage;
    }
    
    /**
     * @param array $paths
     * @param string $filename
     * @param bool $getFull
     * @param bool $deleteOriginals
     * @return string
     * @throws FileNotFoundException
     */
    public function create(array $paths = [], $filename = 'temp.zip', $getFull = false, $deleteOriginals = false)
    {
        $files = [];
        foreach ($paths as $file) {
            if (is_string($file)) {
                $files[] = $file;
                continue;
            }
            
            Validator::validate($file, [
                'file' => 'required:string',
                'options' => 'array',
                'options.as' => 'string',
                'options.mime' => 'string'
            ]);
            
            $files[] = $file['file'];
        }
    
        $files = $this->stageFiles($files, $deleteOriginals);
        $target = "$this->tempDir/$filename";
        $fullPath = "$this->root/$target";
        
        foreach ($paths as $index => $file) {
            if (is_string($file)) {
                continue;
            }
            $paths[$index]['file'] = $files[$index];
        }
        
        if (!$this->storage->compressTo($target, $paths, $deleteOriginals)) {
            throw new FileNotFoundException("Failed to create file: '$fullPath'");
        }
        
        return $getFull ? $fullPath : $target;
    }
    
    /**
     * @param array|string $paths
     * @param string $filename
     * @param bool $getFull
     * @return string
     */
    public static function make($paths = [], $filename = 'temp.zip', $getFull = true)
    {
        return (new self())->create((array)$paths, $filename, $getFull);
    }
    
    /**
     * @param $directory
     * @return SplFileInfo[]
     */
    protected function getFiles($directory)
    {
        $directory = rtrim($directory, '/');
        $files = File::allFiles($directory);
        /** @var SplFileInfo $file */
        foreach ($files as $index => $file) {
            $files[$index] = "$directory/".$file->getRelativePath();
        }
        
        return $files;
    }
    
    /**
     * @param array $paths
     * @param bool $deleteOriginals
     * @return array
     */
    public function stageFiles(array $paths = [], $deleteOriginals = false)
    {
        /** @var Filesystem $tempDriver */
        $tempDriver = $this->storage->getDriver();
        $mountManager = app(MountManager::class, ['filesystems' => [
            'temp' => $tempDriver
        ]]);
        
        foreach ($paths as $index => $target) {
            if (is_string($target)) {
                continue;
            }
            
            $this->specifiesDisk($target, true);
            
            $driver = Storage::disk($target['disk'])->getDriver();
            $mountManager->mountFilesystem($target['disk'], $driver);
            
            $from = $target['disk'].'://'.$target['path'];
            $newPath = "$this->tempDir/".$target['path'];
            $to = "temp://$newPath";
            $mountManager->copy($from, $to);
            $paths[$index] = "$this->root/$newPath";
            
            if ($deleteOriginals) {
                if (config('zip-fly.clean_temps')) {
                    Storage::disk($target['disk'])->delete($target['path']);
                } else {
                    $root = get_root($target['disk']);
                    el("[ZIP_FLY] Prevented deleting '$root/".$target['path'].'\'');
                }
            }
        }
        
        foreach ($paths as $index => $path) {
            if (File::isDirectory($path)) {
                $files = $this->getFiles($path);
                $paths[$index] = array_merge($paths, $files);
            }
        }
        
        return $paths;
    }
    
    /**
     * @param $target
     * @param bool $strict
     * @return bool
     * @throws ValidationException
     */
    protected function specifiesDisk($target, $strict = false)
    {
        $validator = Validator::make($target, [
            'path' => 'required|string',
            'disk' => 'required|string'
        ]);
        
        if (!$strict) {
            return $validator->passes();
        }
        $validator->validate();
        
        return true;
    }
    
    /**
     * @void
     */
    public function cleanup()
    {
        if (!in_array($this->tempDir, [null, '', '/'])) {
            if (config('zip-fly.clean_temps')) {
                $this->storage->deleteDirectory($this->tempDir);
            } else {
                el("[ZIP_FLY] Prevented deleting '$this->root/$this->tempDir'");
            }
        }
    }
    
//    /**
//     * @void
//     */
//    public function __destruct()
//    {
//        if (!$this->sleeping) {
//            $this->cleanup();
//        }
//    }
}
