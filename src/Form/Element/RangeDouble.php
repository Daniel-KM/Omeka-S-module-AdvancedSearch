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
     * @var string
     */
    protected $scaleMode = 'linear';

    /**
     * @var array
     */
    protected $scaleBreakpoints = [];

    /**
     * @var bool
     */
    protected $scaleShowTicks = false;

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
        if (array_key_exists('scale_mode', $this->options)) {
            $this->setScaleMode((string) $this->options['scale_mode']);
        }
        if (array_key_exists('scale_breakpoints', $this->options)) {
            $this->setScaleBreakpoints($this->options['scale_breakpoints']);
        }
        if (array_key_exists('scale_show_ticks', $this->options)) {
            $this->setScaleShowTicks((bool) $this->options['scale_show_ticks']);
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
            'name' => $this->getName(),
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

    public function setScaleMode(string $mode): self
    {
        $this->scaleMode = $mode === 'piecewise' ? 'piecewise' : 'linear';
        return $this;
    }

    public function getScaleMode(): string
    {
        return $this->scaleMode;
    }

    /**
     * Set scale breakpoints. Accepts:
     * - associative array (value => position), where value can be numeric or
     *   the placeholders "min" / "max",
     * - list of [value, position] pairs.
     *
     * Stored as a list of [value, position] where value is float or the literal
     * string "min" / "max". Resolution to actual domain values is deferred to
     * getResolvedScaleBreakpoints().
     */
    public function setScaleBreakpoints($breakpoints): self
    {
        $pairs = [];
        if (is_array($breakpoints)) {
            foreach ($breakpoints as $key => $value) {
                if (is_array($value) && count($value) === 2) {
                    $v = $value[0];
                    if ($v === 'min' || $v === 'max') {
                        $pairs[] = [$v, (float) $value[1]];
                    } elseif (is_numeric($v) && is_numeric($value[1])) {
                        $pairs[] = [(float) $v, (float) $value[1]];
                    }
                } elseif (is_numeric($value)) {
                    if ($key === 'min' || $key === 'max') {
                        $pairs[] = [$key, (float) $value];
                    } elseif (is_numeric($key)) {
                        $pairs[] = [(float) $key, (float) $value];
                    }
                }
            }
        }
        $this->scaleBreakpoints = $pairs;
        return $this;
    }

    /**
     * @return array List of [value, position] pairs (raw, may contain "min" /
     * "max" placeholders).
     */
    public function getScaleBreakpoints(): array
    {
        return $this->scaleBreakpoints;
    }

    /**
     * Return breakpoints with "min" / "max" placeholders resolved to the
     * provided domain extremes, sorted by value.
     *
     * @return array<int,array{0:float,1:float}>
     */
    public function getResolvedScaleBreakpoints(?float $domainMin, ?float $domainMax): array
    {
        $pairs = [];
        foreach ($this->scaleBreakpoints as [$v, $p]) {
            if ($v === 'min') {
                if ($domainMin === null) {
                    continue;
                }
                $pairs[] = [(float) $domainMin, $p];
            } elseif ($v === 'max') {
                if ($domainMax === null) {
                    continue;
                }
                $pairs[] = [(float) $domainMax, $p];
            } else {
                $pairs[] = [(float) $v, $p];
            }
        }
        usort($pairs, fn ($a, $b) => $a[0] <=> $b[0]);
        return $pairs;
    }

    public function setScaleShowTicks(bool $showTicks): self
    {
        $this->scaleShowTicks = $showTicks;
        return $this;
    }

    public function getScaleShowTicks(): bool
    {
        return $this->scaleShowTicks;
    }
}
