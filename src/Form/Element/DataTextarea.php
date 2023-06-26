<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Element;

use Omeka\Form\Element\ArrayTextarea;

class DataTextarea extends ArrayTextarea
{
    /**
     * @var array
     */
    protected $dataKeys = [];

    /**
     * @var array
     */
    protected $dataArrayKeys = [];

    /**
     * @var string
     *
     * May be "by_line" (one line by data, default) or "last_is_list".
     */
    protected $dataTextMode = '';

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        parent::setOptions($options);
        if (array_key_exists('data_keys', $this->options)) {
            $this->setDataKeys($this->options['data_keys']);
        }
        if (array_key_exists('data_array_keys', $this->options)) {
            $this->setDataArrayKeys($this->options['data_array_keys']);
        }
        if (array_key_exists('data_text_mode', $this->options)) {
            $this->setDataTextMode($this->options['data_text_mode']);
        }
        return $this;
    }

    public function arrayToString($array): string
    {
        if (is_string($array)) {
            return $array;
        }
        if (is_null($array)) {
            return '';
        }
        $textMode = $this->getDataTextMode();
        if ($textMode === 'last_is_list') {
            return $this->arrayToStringLastIsList($array);
        }
        return $this->arrayToStringByLine($array);
    }

    public function stringToArray($string): array
    {
        if (is_array($string)) {
            return $string;
        }
        if (is_null($string)) {
            return [];
        }
        $textMode = $this->getDataTextMode();
        if ($textMode === 'last_is_list') {
            return $this->stringToArrayLastIsList((string) $string);
        }
        return $this->stringToArrayByLine((string) $string);
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
    public function setDataKeys(array $dataKeys)
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

    /**
     * Set the option to separate values into multiple values.
     *
     * It should be a subset of the data keys.
     *
     * It is not recommended to set the first key when option "as_key_value" is
     *  set. In that case, the whole value is used as key before to be splitted.
     */
    public function setDataArrayKeys(array $dataArrayKeys)
    {
        $this->dataArrayKeys = $dataArrayKeys;
        return $this;
    }

    /**
     * Get the option to separate values into multiple values.
     */
    public function getDataArrayKeys(): array
    {
        return $this->dataArrayKeys;
    }

    /**
     * Set the mode to display the text inside the textarea input.
     *
     * - "by_line" (default: all the data are on the same line):
     * ```
     * x = y = z = a, b, c
     * ```
     *
     * - "last_is_list" (the last field is exploded and an empty line is added):
     * ```
     * x = y = z
     * a
     * b
     * c
     *
     * ```
     */
    public function setDataTextMode(?string $dataTextMode)
    {
        $this->dataTextMode = (string) $dataTextMode;
        return $this;
    }

    /**
     * Get the text mode of the data.
     */
    public function getDataTextMode(): string
    {
        return $this->dataTextMode;
    }

    protected function arrayToStringByLine(array $array): string
    {
        // Reorder values according to specified keys and fill empty values.
        $string = '';
        $countDataKeys = count($this->dataKeys);
        // Associative array.
        if ($countDataKeys) {
            $arrayKeys = array_intersect_key($this->dataArrayKeys, $this->dataKeys);
            foreach ($array as $values) {
                $data = array_replace($this->dataKeys, $values);
                // Manage sub-values.
                foreach ($arrayKeys as $arrayKey => $arraySeparator) {
                    $separator = ' ' . $arraySeparator;
                    $data[$arrayKey] = implode($separator, array_map('strval', isset($data[$arrayKey]) ? (array) $data[$arrayKey] : []));
                }
                $string .= implode(' ' . $this->keyValueSeparator . ' ', array_map('strval', $data)) . "\n";
            }
        }
        // Simple list.
        else {
            foreach ($array as $values) {
                $data = array_values($values);
                $string .= implode(' ' . $this->keyValueSeparator . ' ', array_map('strval', $data)) . "\n";
            }
        }
        $string = rtrim($string, "\n");
        return strlen($string) ? $string . "\n" : '';
    }

    protected function arrayToStringLastIsList(array $array): string
    {
        // Reorder values according to specified keys and fill empty values.
        $string = '';
        $countDataKeys = count($this->dataKeys);
        // Associative array.
        if ($countDataKeys) {
            // Without last key, the result is the same than by line.
            $lastKey = key(array_slice($this->dataKeys, -1));
            $arrayKeys = array_intersect_key($this->dataArrayKeys, $this->dataKeys);
            if (!isset($arrayKeys[$lastKey])) {
                return $this->arrayToStringByLine($array);
            }
            foreach ($array as $values) {
                $data = array_replace($this->dataKeys, $values);
                // Manage sub-values.
                foreach ($arrayKeys as $arrayKey => $arraySeparator) {
                    $isLastKey = $arrayKey === $lastKey;
                    $separator = $isLastKey ? "\n" : ' ' . $arraySeparator . ' ';
                    $data[$arrayKey] = implode($separator, array_map('strval', isset($data[$arrayKey]) ? (array) $data[$arrayKey] : []));
                }
                // Don't add the key value separator for the last field, and
                // append a line break to add an empty line.
                $string .= implode(' ' . $this->keyValueSeparator . ' ', array_map('strval', array_slice($data, 0, -1))) . "\n"
                    . $data[$lastKey] . "\n\n";
            }
        }
        // Simple list.
        else {
            foreach ($array as $values) {
                $data = array_values($values);
                $string .= implode("\n", array_map('strval', $data)) . "\n\n";
            }
        }
        $string = rtrim($string, "\n");
        return strlen($string) ? $string . "\n" : '';
    }

    protected function stringToArrayByLine(string $string): array
    {
        $array = [];
        $countDataKeys = count($this->dataKeys);
        if ($countDataKeys) {
            $arrayKeys = array_intersect_key($this->dataArrayKeys, $this->dataKeys);
            $list = $this->stringToList($string);
            foreach ($list as $values) {
                $values = array_map('trim', explode($this->keyValueSeparator, $values, $countDataKeys));
                // Add empty missing values. The number cannot be higher.
                // TODO Use substr_count() if quicker.
                $missing = $countDataKeys - count($values);
                if ($missing) {
                    $values = array_merge($values, array_fill(0, $missing, ''));
                }
                $data = array_combine(array_keys($this->dataKeys), $values);
                // Manage sub-values.
                foreach ($arrayKeys as $arrayKey => $arraySeparator) {
                    $data[$arrayKey] = $data[$arrayKey] === ''
                        ? []
                        : array_map('trim', explode($arraySeparator, $data[$arrayKey]));
                }
                $this->asKeyValue
                    ? $array[reset($data)] = $data
                    : $array[] = $data;
            }
        } else {
            $list = $this->stringToList($string);
            foreach ($list as $values) {
                // No keys: a simple list.
                $data = array_map('trim', explode($this->keyValueSeparator, $values));
                $this->asKeyValue
                    ? $array[reset($data)] = $data
                    : $array[] = $data;
            }
        }
        return $array;
    }

    protected function stringToArrayLastIsList(string $string): array
    {
        $array = [];
        $countDataKeys = count($this->dataKeys);
        if ($countDataKeys) {
            // Without last key, the result is the same than by line.
            $lastKey = key(array_slice($this->dataKeys, -1));
            $arrayKeys = array_intersect_key($this->dataArrayKeys, $this->dataKeys);
            if (!isset($arrayKeys[$lastKey])) {
                return $this->stringToArrayByLine($array);
            }
            // Create groups from empty lines, namely a double line break.
            $groups = array_filter(array_map('trim', explode("\n\n", $this->fixEndOfLine($string))));
            foreach ($groups as $group) {
                $values = array_map('trim', explode("\n", $group));
                $firstFieldsValues = array_map('trim', explode($this->keyValueSeparator, reset($values), $countDataKeys - 1));
                $lastFieldValues = array_slice($values, 1);
                // Add empty missing values. The number cannot be higher.
                // TODO Use substr_count() if quicker.
                $missing = $countDataKeys - 1 - count($firstFieldsValues);
                if ($missing) {
                    $firstFieldsValues = array_merge($firstFieldsValues, array_fill(0, $missing, ''));
                }
                $values = $firstFieldsValues;
                $values[] = $lastFieldValues;
                $data = array_combine(array_keys($this->dataKeys), $values);
                // Manage sub-values.
                foreach ($arrayKeys as $arrayKey => $arraySeparator) {
                    $isLastKey = $arrayKey === $lastKey;
                    if ($isLastKey) {
                        continue;
                    }
                    $data[$arrayKey] = $data[$arrayKey] === ''
                        ? []
                        : array_map('trim', explode($arraySeparator, $data[$arrayKey]));
                }
                $this->asKeyValue
                    ? $array[reset($data)] = $data
                    : $array[] = $data;
            }
        } else {
            // Create groups from empty lines, namely a double line break.
            $groups = array_filter(array_map('trim', explode("\n\n", $this->fixEndOfLine($string))));
            foreach ($groups as $group) {
                // No keys: a simple list.
                $data = array_map('trim', explode("\n", $group));
                $this->asKeyValue
                    ? $array[reset($data)] = $data
                    : $array[] = $data;
            }
        }
        return $array;
    }
}
