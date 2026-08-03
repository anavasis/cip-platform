<?php

namespace App\Modules\Editorial\Domain\Blueprint;


/**
 * Target length band for a blueprint.
 */
final class LengthTarget
{
    public const UNIT_WORDS = 'words';
    public const UNIT_CHARS = 'chars';

    private $unit;
    private $min;
    private $max;
    private $ideal;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->unit = isset($data['unit']) ? (string) $data['unit'] : self::UNIT_WORDS;
        $this->min = isset($data['min']) ? (int) $data['min'] : 0;
        $this->max = isset($data['max']) ? (int) $data['max'] : 0;
        $this->ideal = isset($data['ideal']) ? (int) $data['ideal'] : 0;
    }

    /** @return string */
    public function unit()
    {
        return $this->unit;
    }

    /** @return string */
    public function min()
    {
        return $this->min;
    }

    /** @return string */
    public function max()
    {
        return $this->max;
    }

    /** @return string */
    public function ideal()
    {
        return $this->ideal;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'unit' => $this->unit,
            'min' => $this->min,
            'max' => $this->max,
            'ideal' => $this->ideal,
        );
    }
}
