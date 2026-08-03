<?php

namespace App\Modules\Editorial\Domain\GenerationRequest;


/**
 * Persistence port for Generation Requests.
 * BUILD-004 provides the interface only — no database adapter.
 */
interface GenerationRequestRepositoryInterface
{
    /**
     * @param GenerationRequest $request
     * @return bool
     */
    public function save(GenerationRequest $request);

    /**
     * @param string $requestId
     * @return GenerationRequest|null
     */
    public function findById($requestId);

    /**
     * @param string $requestHash
     * @return GenerationRequest|null
     */
    public function findByRequestHash($requestHash);

    /**
     * Latest non-superseded/cancelled request for an announcement, if any.
     *
     * @param string $announcementId
     * @return GenerationRequest|null
     */
    public function findLatestForAnnouncement($announcementId);

    /**
     * Latest request bound to a Prompt Package id, if any.
     *
     * @param string $packageId
     * @return GenerationRequest|null
     */
    public function findLatestForPackage($packageId);
}
