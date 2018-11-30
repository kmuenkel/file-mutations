<?php

return [
    /**
     * Dictates whether to delete temp files.  This includes files copied from non-local disks used in the
     * creation of the zip files, and the zip files themselves upon destruction after they've been returned.
     */
    'clean_temps' => !env('FILE_MUTATIONS_DEBUG', false)
];
