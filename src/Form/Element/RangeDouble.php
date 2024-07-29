<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Element;

use Laminas\Form\Element;
use Laminas\InputFilter\InputProviderInterface;

/**
 * A form element with two input ranges.
 *
 * Similar to a double input range and a double input number for accessibility.
 * Options are available to display only the double slider or only the numbers.
 *
 * @see \Laminas\Form\Element\Number
 * @see \Laminas\Form\Element\Range
 */
class RangeDouble extends Element implements InputProviderInterface
{
    /**
     * @var string
     */
    protected $keyFrom = 'from';

    /**
     * @var string
     */
    protected $keyTo = 'to';

    /**
     * @var string
     */
    protected $labelFrom = 'From'; // @translate

    /**
     * @var string
     */
    protected $labelTo = 'To'; // @translate

    /**
     * @var bool
     */
    protected $useInputRange = true;

    /**
     * @var bool
     */
    protected $useInputNumber = true;

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        parent::setOptions($options);
        if (array_key_exists('key_from', $this->options)) {
            $this->setKeyFrom($this->options['key_from']);
        }
        if (array_key_exists('key_to', $this->options)) {
            $this->setKeyTo($this->options['key_to']);
        }
        if (array_key_exists('label_from', $this->options)) {
            $this->setLabelFrom($this->options['label_from']);
        }
        if (array_key_exists('label_to', $this->options)) {
            $this->setLabelTo($this->options['label_to']);
        }
        if (array_key_exists('use_input_range', $this->options)) {
            $this->setUseInputRange($this->options['use_input_range']);
        }
        if (array_key_exists('use_input_number', $this->options)) {
            $this->setUseInputNumber($this->options['use_input_number']);
        }
        return $this;
    }

    /**
     * TraitOptionalElement cannot be used because it is derivated directly from
     * element.
     *
     * @see https://github.com/zendframework/zendframework/issues/2761#issuecomment-14488216
     *
     * {@inheritDoc}
     * @see \Laminas\Form\Element\Select::getInputSpecification()
     */
    public function getInputSpecification(): array
    {
        return [
            'name'     => $this->getName(),
            'required' => !empty($this->attributes['required']),
        ];
    }

    /**
     * With range double, the input is an array using keys "from" and "to" or
     * the ones passed as options.
     *
     * {@inheritDoc}
     * @see \Laminas\Form\Element::setValue()
     */
    public function setValue($value)
    {
        // Set the two keys to allow empty values and to avoid future checks.
        if (is_array($value)) {
            $this->value = [
                $this->keyFrom => $value[$this->keyFrom] ?? null,
                $this->keyTo => $value[$this->keyTo] ?? null,
            ];
        } else {
            $this->value = null;
        }
        return $this;
    }

    /**
     * Set the value for range start.
     */
    public function setValueFrom($valueFrom): self
    {
        $this->value[$this->keyFrom] = $valueFrom;
        return $this;
    }

    /**
     * Get the value for range start.
     */
    public function getValueFrom(): self
    {
        return is_array($this->value) ? $this->value[$this->keyFrom] ?? null : null;
    }

    /**
     * Set the value for range end.
     */
    public function setValueTo($valueTo): self
    {
        if ($this->value === null) {
            $this->value = [];
        }
        $this->value[$this->keyTo] = $valueTo;
        return $this;
    }

    /**
     * Get the value for range end.
     */
    public function getValueTo(): self
    {
        return is_array($this->value) ? $this->value[$this->keyTo] ?? null : null;
    }

    /**
     * Set the key for range start.
     */
    public function setKeyFrom(?string $keyFrom): self
    {
        $this->keyFrom = $keyFrom ?? 'from';
        return $this;
    }

    /**
     * Get the key for range start.
     */
    public function getKeyFrom(): string
    {
        return $this->keyFrom;
    }

    /**
     * Set the key for range end.
     */
    public function setKeyTo(?string $keyTo): self
    {
        $this->keyTo = $keyTo ?? 'to';
        return $this;
    }

    /**
     * Get the key for range end.
     */
    public function getKeyTo(): string
    {
        return $this->keyTo;
    }

    /**
     * Set the label for range start.
     */
    public function setLabelFrom(?string $labelFrom): self
    {
        $this->labelFrom = $labelFrom;
        return $this;
    }

    /**
     * Get the label for range start.
     */
    public function getLabelFrom(): string
    {
        return $this->labelFrom;
    }

    /**
     * Set the label for range end.
     */
    public function setLabelTo(?string $labelTo): self
    {
        $this->labelTo = $labelTo;
        return $this;
    }

    /**
     * Get the label for range end.
     */
    public function getLabelTo(): string
    {
        return $this->labelTo;
    }

    /**
     * Set the flag to use the input range.
     */
    public function setUseInputRange(bool $useInputRange): self
    {
        $this->useInputRange = $useInputRange;
        return $this;
    }

    /**
     * Get the flag to use the input range.
     */
    public function getUseInputRange(): bool
    {
        return $this->useInputRange;
    }

    /**
     * Set the flag to use the input number.
     */
    public function setUseInputNumber(bool $useInputNumber): self
    {
        $this->useInputNumber = $useInputNumber;
        return $this;
    }

    /**
     * Get the flag to use the input number.
     */
    public function getUseInputNumber(): bool
    {
        return $this->useInputNumber;
    }
}
