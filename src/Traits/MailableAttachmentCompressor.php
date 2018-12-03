<?php

namespace FileMutations\Providers\Traits;

use Log;
use File;
use Illuminate\Mail\Mailable;
use FileMutations\Helpers\TempZip;
use FileMutations\Exceptions\FileTooLarge;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

/**
 * Trait MailableAttachmentCompressor
 * @package FileMutations\Providers\Traits
 * @mixin Mailable
 */
trait MailableAttachmentCompressor
{
    /**
     * @var int In Megabytes
     */
    public $maxSize = 5;
    
    /**
     * @var string
     */
    protected $zipName = 'Attachments.zip';
    
    /**
     * @var bool
     */
    protected $clearAttachments = false;
    
    /**
     * @var TempZip
     */
    protected $tempZip;
    
    /**
     * @var string
     */
    protected $tempZipDirectory = null;
    
    /**
     * @var bool
     */
    protected $fixExtensions = true;
    
    /**
     * @var bool
     */
    protected $madeZip = false;
    
    /**
     * Intended for use before Mailable::send()
     *
     * @void
     */
    protected function cleanup()
    {
        if ($this->madeZip) {
            $this->tempZip->cleanup();
        }
        
        if ($this->clearAttachments) {
            foreach ($this->attachments as $attachment) {
                if (File::exists($attachment['file'])) {
                    if (!config('file-mutations.clean_temps')) {
                        Log::debug('[ZIP_FLY] Prevented deleting \'' . $attachment['file'] . '\'');
                        
                        continue;
                    }
                    
                    File::delete($attachment['file']);
                }
            }
        }
    }
    
    /**
     * @void
     */
    protected function correctExtensions()
    {
        //TODO: Add support for rawAttachments as well
        $files = array_pluck($this->attachments, 'file');
        
        $files = $this->tempZip->stageFiles($files, $this->clearAttachments);
        foreach ($this->attachments as $index => $attachment) {
            $this->attachments[$index]['file'] = $files[$index];
            $name = array_get($this->attachments[$index], 'options.as') ?: basename($files[$index]);
            $mimeType = array_get($this->attachments[$index], 'options.mime') ?:
                mime_content_type($this->attachments[$index]['file']);
            $extension = File::mimeExtension($mimeType);
            $name = $extension ? File::name($name).'.'.$extension : $name;
            $this->attachments[$index]['options'] = array_merge($this->attachments[$index]['options'], [
                'as' => $name,
                'mime' => $mimeType
            ]);
        }
    }
    
    /**
     * Intended for use after Mailable::send()
     *
     * @return string
     * @throws FileTooLarge
     * @throws FileNotFoundException
     */
    protected function compress()
    {
        if ($this->maxSize === false) {
            return '';
        }
        
        $this->tempZip = $this->tempZip ?: app(TempZip::class, ['tempDir' => $this->tempZipDirectory]);
        
        if ($this->fixExtensions) {
            $this->correctExtensions();
        }
        
        $totalSizeInBytes = 0;
        foreach ($this->attachments as $attachment) {
            $totalSizeInBytes += File::size($attachment['file']);
        }
        
        $zipPath = '';
        $totalSizeInMegs = size_conversion($totalSizeInBytes);
        if ($totalSizeInMegs > $this->maxSize) {
            $attachments = $this->attachments;
            $this->attachments = [];
            $zipPath = $this->tempZip->create($attachments, $this->zipName, true, true);
            $this->madeZip = true;
            
            $zipSize = File::size($zipPath);
            $zipSize = size_conversion($zipSize);
            
            if ($zipSize > $this->maxSize) {
                $this->cleanup();
                
                throw new FileTooLarge('Size limit of '.$this->maxSize.'MB for the compressed file exceeded to '.$zipSize.'MB.');
            }
            
            $this->attachments = [
                [
                    'file' => $zipPath,
                    'options' => [
                        'as' => $this->zipName,
                        'mime' => 'application/zip'
                    ]
                ]
            ];
        }
        
        return $zipPath;
    }
}
