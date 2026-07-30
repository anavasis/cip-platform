<?php

namespace StudyMentor\ContentEngine\Acquisition;

defined('ABSPATH') || exit;

/**
 * Public facade for the Acquisition Engine V1 subsystem.
 */
final class AcquisitionEngine
{
    public const VERSION = '1.0.0';

    private $manager;
    private $diagnostics;

    public function __construct(AcquisitionManager $manager, AcquisitionDiagnostics $diagnostics)
    {
        $this->manager = $manager;
        $this->diagnostics = $diagnostics;
    }

    /**
     * @param array<string, mixed> $request
     * @return AcquisitionResult
     */
    public function acquire(array $request)
    {
        $result = $this->manager->acquire($request);
        $this->diagnostics->recordResult($result);

        return $result;
    }

    /**
     * @return AcquisitionDiagnostics
     */
    public function diagnostics()
    {
        return $this->diagnostics;
    }

    /**
     * @return string
     */
    public function version()
    {
        return self::VERSION;
    }
}
