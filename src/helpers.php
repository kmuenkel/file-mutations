<?php

namespace FileMutations;

if (!function_exists('size_conversion')) {
    /**
     * @param float $size
     * @param string $to
     * @param string $format
     * @return int
     */
    function size_conversion($size, $to = 'megabytes', $format = 'bytes')
    {
        $conversions = [
            'bytes' => 1099511627776,
            'kilobytes' => 1073741824,
            'megabytes' => 1048576,
            'gigabytes' => 1024,
            'terabytes' => 1
        ];
        
        if (!array_key_exists($to, $conversions) || !array_key_exists($format, $conversions)) {
            $options = implode(', ', array_keys($conversions));
            $error = 'Arguments 2 and 3 must be one of the following: '.$options;
            throw new \InvalidArgumentException($error);
        }
        
        $conversion = $size / $conversions[$format] * $conversions[$to];
        
        return $conversion;
    }
}

if (!function_exists('base64_to_upload')) {
    /**
     * @param string $content
     * @param string|null $name
     * @return \Illuminate\Http\UploadedFile
     */
    function base64_to_upload($content, $name = null)
    {
        //"data:image/png;base64,iVBORw0K...";
        $content = array_last(explode(',', $content));
        $content = base64_decode($content);
        $mimeType = \File::streamMimeType($content);
        $extension = \File::mimeExtension($mimeType);
        $size = \File::streamSize($content);
        $name = $name ?: uniqid('decoded_').'.'.$extension;
        //Algorithm derived from \Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory::getTemporaryPath()
        $path = tempnam(sys_get_temp_dir(), uniqid('symfony', true));
        \File::put($path, $content);
        
        $file = app(\Illuminate\Http\UploadedFile::class, [
            'path' => $path,
            'originalName' => $name,
            'mimeType' => $mimeType,
            'size' => $size,
            'error' => null,
            'test' => true
        ]);
        
        return $file;
    }
}

if (!function_exists('upload_to_base64')) {
    /**
     * @param string $content
     * @return \Illuminate\Http\UploadedFile
     */
    function upload_to_base64(\Illuminate\Http\UploadedFile $content)
    {
        $mimeType = $content->getMimeType();
        $content = base64_encode(file_get_contents($content->path()));
        $file = "data:$mimeType;base64, $content";
        
        return $file;
    }
}
