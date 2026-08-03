<?php

namespace App\Modules\Editorial\Domain\PromptPackage;


/**
 * Persistence port for Prompt Packages.
 * BUILD-003 provides the interface only — no database adapter.
 */
interface PromptPackageRepositoryInterface
{
    /**
     * @param PromptPackage $package
     * @return bool
     */
    public function save(PromptPackage $package);

    /**
     * @param string $packageId
     * @return PromptPackage|null
     */
    public function findById($packageId);

    /**
     * @param string $packageHash
     * @return PromptPackage|null
     */
    public function findByPackageHash($packageHash);

    /**
     * Latest non-superseded package for an announcement, if any.
     *
     * @param string $announcementId
     * @return PromptPackage|null
     */
    public function findLatestForAnnouncement($announcementId);

    /**
     * Latest package bound to a Prompt Context id, if any.
     *
     * @param string $contextId
     * @return PromptPackage|null
     */
    public function findLatestForContext($contextId);
}
