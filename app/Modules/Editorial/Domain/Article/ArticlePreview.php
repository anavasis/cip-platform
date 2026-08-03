<?php

namespace App\Modules\Editorial\Domain\Article;

/**
 * Article preview produced by Editorial generate path.
 */
final class ArticlePreview
{
    private string $previewId;
    private string $organizationId;
    private string $projectId;
    private string $announcementId;
    private string $requestId;
    private string $resultId;
    private string $resultHash;
    private string $title;
    private string $body;
    private string $createdAtUtc;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->previewId = isset($data['preview_id']) ? (string) $data['preview_id'] : '';
        $this->organizationId = isset($data['organization_id']) ? trim((string) $data['organization_id']) : '';
        $this->projectId = isset($data['project_id']) ? trim((string) $data['project_id']) : '';
        $this->announcementId = isset($data['announcement_id']) ? trim((string) $data['announcement_id']) : '';
        $this->requestId = isset($data['request_id']) ? (string) $data['request_id'] : '';
        $this->resultId = isset($data['result_id']) ? (string) $data['result_id'] : '';
        $this->resultHash = isset($data['result_hash']) ? (string) $data['result_hash'] : '';
        $this->title = isset($data['title']) ? (string) $data['title'] : '';
        $this->body = isset($data['body']) ? (string) $data['body'] : '';
        $this->createdAtUtc = isset($data['created_at_utc']) ? (string) $data['created_at_utc'] : '';
    }

    public function previewId(): string
    {
        return $this->previewId;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function projectId(): string
    {
        return $this->projectId;
    }

    public function announcementId(): string
    {
        return $this->announcementId;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function resultId(): string
    {
        return $this->resultId;
    }

    public function resultHash(): string
    {
        return $this->resultHash;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function createdAtUtc(): string
    {
        return $this->createdAtUtc;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'preview_id' => $this->previewId,
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'announcement_id' => $this->announcementId,
            'request_id' => $this->requestId,
            'result_id' => $this->resultId,
            'result_hash' => $this->resultHash,
            'title' => $this->title,
            'body' => $this->body,
            'created_at_utc' => $this->createdAtUtc,
        ];
    }
}
