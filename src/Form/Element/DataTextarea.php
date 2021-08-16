<?php declare(strict_types=1);

namespace Search\Form\Element;

use Omeka\Form\Element\ArrayTextarea;

class DataTextarea extends ArrayTextarea
{
    /**
     * @var array
     */
    protected $dataKeys = [];

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        parent::setOptions($options);
        if (array_key_exists('data_keys', $this->options)) {
            $this->setDataKeys($this->options['data_keys']);
        }
        return $this;
    }

    public function arrayToString($array): string
    {
        if (is_string($array)) {
            return $array;
        }
        $string = '';
        foreach ($array as $values) {
            // Reorder values according to specified keys and fill empty values.
            $data = count($this->dataKeys)
                // Associative array.
                ? array_replace($this->dataKeys, $values)
                // Simple list.
                : array_values($values);
            $string .= implode($this->keyValueSeparator, array_map('strval', $data)) . "\n";
        }
        return $string;
    }

    public function stringToArray($string): array
    {
        if (is_array($string)) {
            return $string;
        }
        $array = [];
        $count = count($this->dataKeys);
        foreach ($this->stringToList($string) as $values) {
            if ($count) {
                // Set keys to each part of the line.
                $keys = array_keys($this->dataKeys);
                $values = array_map('trim', explode($this->keyValueSeparator, $values, count($this->dataKeys)));
                // Add empty missing values. The number cannot be higher.
                // TODO Use substr_count() if quicker.
                $missing = count($this->dataKeys) - count($values);
                if ($missing) {
                    $values = array_merge($values, array_fill(0, $missing, ''));
                }
                $data = array_combine($keys, $values);
            } else {
                // No keys: a simple list.
                $data = array_map('trim', explode($this->keyValueSeparator, $values));
            }
            $this->asKeyValue
                ? $array[reset($data)] = $data
                : $array[] = $data;
        }
        return $array;
    }

    /**
     * Set the ordered list of keys to use for each line.
     *
     * Each specified key will be used as the keys of each part of each line.
     * There is no default keys: in that case, the values are a simple array of
     * array.
     * With option "as_key_value", the first value will be the used as key for
     * the main array too.
     */
    public function setDataKeys(array $dataKeys): self
    {
        $this->dataKeys = array_fill_keys($dataKeys, null);
        return $this;
    }

    /**
     * Get the list of data keys.
     */
    public function getDataKeys(): array
    {
        return aray_keys($this->dataKeys);
    }
}
