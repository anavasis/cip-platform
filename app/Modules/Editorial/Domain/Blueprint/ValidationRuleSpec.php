<?php

namespace App\Modules\Editorial\Domain\Blueprint;


/**
 * Explicit machine-checkable validation rule declared on a blueprint.
 */
final class ValidationRuleSpec
{
    private $ruleId;
    private $blocking;
    /** @var array<string, mixed> */
    private $params;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->ruleId = isset($data['rule_id']) ? (string) $data['rule_id'] : '';
        $this->blocking = !isset($data['blocking']) || $data['blocking'] === true;
        $this->params = isset($data['params']) && is_array($data['params'])
            ? $data['params']
            : array();
    }

    /** @return string */
    public function ruleId()
    {
        return $this->ruleId;
    }

    /** @return bool */
    public function blocking()
    {
        return $this->blocking;
    }

    /**
     * @return array<string, mixed>
     */
    public function params()
    {
        return $this->params;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'rule_id' => $this->ruleId,
            'blocking' => $this->blocking,
            'params' => $this->params,
        );
    }
}
