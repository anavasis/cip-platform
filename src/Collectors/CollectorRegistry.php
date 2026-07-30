<?php

namespace StudyMentor\ContentEngine\Collectors;

defined('ABSPATH') || exit;

final class CollectorRegistry
{
    /** @var array<string, CollectorInterface> */
    private $collectors = array();

    /** @var array<string, string> */
    private $sourceTypeMap = array();

    /**
     * @return void
     */
    public function register(CollectorInterface $collector)
    {
        $this->collectors[$collector->id()] = $collector;
    }

    /**
     * @param string $sourceType
     * @param string $collectorId
     * @return void
     */
    public function mapSourceType($sourceType, $collectorId)
    {
        $type = strtolower(trim((string) $sourceType));
        $id = trim((string) $collectorId);

        if ($type === '' || $id === '') {
            return;
        }

        $this->sourceTypeMap[$type] = $id;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return isset($this->collectors[(string) $id]);
    }

    /**
     * @param string $id
     * @return CollectorInterface|null
     */
    public function get($id)
    {
        $key = (string) $id;

        return isset($this->collectors[$key]) ? $this->collectors[$key] : null;
    }

    /**
     * @param string $sourceType
     * @return CollectorInterface|null
     */
    public function resolveForSourceType($sourceType)
    {
        $type = strtolower(trim((string) $sourceType));

        if ($type !== '' && isset($this->sourceTypeMap[$type])) {
            return $this->get($this->sourceTypeMap[$type]);
        }

        return $this->defaultCollector();
    }

    /**
     * @return CollectorInterface|null
     */
    public function defaultCollector()
    {
        return $this->get('safe_feed');
    }

    /**
     * @return array<string, CollectorInterface>
     */
    public function all()
    {
        return $this->collectors;
    }

    /**
     * @return array<string, string>
     */
    public function sourceTypeMap()
    {
        return $this->sourceTypeMap;
    }
}
