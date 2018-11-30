<?php

namespace FileMutations\Traits;

use Illuminate\Http\Request;

/**
 * Trait RequestFileFileNormalizer
 * @package FileMutator\Traits
 * @mixin Request
 */
trait RequestFileFileNormalizer
{
    /**
     * For use in the Request::prepareForValidation() method.
     * Any validation rule specifying that the given field must be a file will be taken as a
     * queue to convert that input parameter to an UploadedFile from a base64 string, if it's
     * not already in that form.
     *
     * @param array|null $rules
     */
    public function normalizeFiles(array $rules = null)
    {
        $rulesProperty = property_exists($this, 'rules') ? $this->rules : [];
        $rulesMethod = method_exists($this, 'rules') ? $this->rules() : $rulesProperty;
        $rules = $rules ?: $rulesMethod;
        
        foreach ($rules as $field => $ruleSet) {
            $ruleSet = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            
            if (in_array('file', $ruleSet) && $this->has($field)) {
                $this->normalizeBase64Files($field);
            }
        }
    }
    
    /**
     * @param string $field
     */
    public function normalizeBase64Files($field)
    {
        if (!$this->file($field) && ($content = $this->input($field)) && is_string($content)) {
            $file = base64_to_upload($content);
            $this->files->add([$field => $file]);
            $this->offsetUnset($field);
        }
    }
}
