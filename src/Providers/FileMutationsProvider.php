<?php

namespace FileMutations\Providers;

use File;
use Storage;
use League\Flysystem;
use FileMutations\Plugins;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesser;

/**
 * Class FileMutatorProvider
 * @package FileMutations\Providers
 */
class FileMutationsProvider extends ServiceProvider
{
    /**
     * @var array
     */
    protected static $adapterMap = [
        Flysystem\Adapter\Local::class => 'local',
        Flysystem\Adapter\Ftp::class => 'ftp',
        Flysystem\Sftp\SftpAdapter::class => 'sftp',
        Flysystem\AwsS3v3\AwsS3Adapter::class => 's3',
        Flysystem\Rackspace\RackspaceAdapter::class => 'rackspace'
    ];
    
    /**
     * @var array
     */
    protected static $plugins = [
        Plugins\ZipExtractTo::class,
        Plugins\ZipCompressTo::class
    ];
    
    /**
     * @void
     */
    public function boot()
    {
        $this->bootConfig();
        $this->addPluginsToAdapters();
        $this->fileMacros();
    }
    
    /**
     * @void
     */
    protected function bootConfig()
    {
        $source = realpath($raw = __DIR__ . '/../../config/file-mutations.php') ?: $raw;
        $this->publishes([$source => config_path('file-mutations.php')]);
        $this->mergeConfigFrom($source, 'file-mutations');
    }
    
    /**
     * @throws InvalidArgumentException
     */
    public function addPluginsToAdapters()
    {
        foreach (self::$adapterMap as $className => $driverName) {
            /** @var FilesystemManager $adapter */
            $filesSystemFactory = Storage::getFacadeRoot();
            $method = 'create'.ucfirst($driverName).'Driver';
            
            //TODO: Add support for custom adapters as well.  May need to override FilesystemManager to reach otherwise protected callCustomCreator() behavior, and 'swap' it into the Storage Facade
            if (method_exists($filesSystemFactory, $method)) {
                Storage::extend($driverName, function(Container $app, array $config = []) use ($filesSystemFactory, $method) {
                    /** @var FilesystemAdapter $adapter */
                    $adapter = $filesSystemFactory->$method($config);
                    
                    return self::addPluginToAdapter($adapter, $app);
                });
            }
        }
    }
    
    /**
     * @param FilesystemAdapter $adapter
     * @param Container|null $app
     * @return FilesystemAdapter
     */
    public static function addPluginToAdapter(FilesystemAdapter $adapter, Container $app = null)
    {
        $app = $app ?: app();
        
        foreach (self::$plugins as $plugin) {
            $plugin = $app->make($plugin);
            $adapter->addPlugin($plugin);
        }
        
        return $adapter;
    }
    
    /**
     * TODO: This serves too generic a need to be limited to handling zip files.  Either it should be abstracted out, or this package should be expanded to offer different kinds of plugins
     * TODO: This should be leveraged in the domain get_root() helper function.
     * 
     * @param FilesystemAdapter $adapter
     * @return string|null
     */
    public static function getAdapterName(FilesystemAdapter $adapter)
    {
        $adapterName = null;
        
        if ($adapter) {
            $driver = $adapter->getDriver();
            if ($driver instanceof Filesystem) {
                $adapterName = $driver->getAdapter();
            }
        }
        
        if (array_key_exists($adapterName, self::$adapterMap)) {
            $adapterName = self::$adapterMap[$adapterName];
        }
        
        return $adapterName;
    }
    
    /**
     * @void
     */
    protected function fileMacros()
    {
        File::macro('streamMimeType', function ($content) {
            $mimeType = finfo_buffer(finfo_open(), $content, FILEINFO_MIME_TYPE);
            
            return $mimeType;
        });
        
        File::macro('streamSize', function ($content) {
            $size = strlen($content);
            
            return $size;
        });
        
        File::macro('mimeExtension', function ($mimeType) {
            $extension = ExtensionGuesser::getInstance()->guess($mimeType);
            
            return $extension;
        });
        
        File::macro('streamExtension', function ($content) {
            $mimeType = $this->streamMimeType($content);
            $extension = $this->streamMimeExtension($mimeType);
            
            return $extension;
        });
    }
}
