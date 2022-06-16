<?php

namespace Framework\Model;

abstract class autofill
{
    /**
     * Tries to fill a key-value pair array into public variables of a class
     * @param $values
     * @param bool $overwrite
     */
    public function tryFill($values, bool $overwrite = false)
    {
        foreach ($values as $key => $value) {
            $hasProperty = property_exists($this, $key);
            // Check if the property exists and is not set, we do not want to overwrite
            if ($hasProperty && ($overwrite || !isset($this->$key))) {
                $this->$key = $value;
            }
        }
    }
}