<?php

namespace StudyMentor\ContentEngine\Article;

defined('ABSPATH') || exit;

/**
 * In-memory article preview produced by Editorial Slice A generate path.
 */
final class ArticlePreview
{
    private $previewId;
    private $announcementId;
    private $requestId;
    private $resultId;
    private $resultHash;
    private $title;
    private $body;
    private $createdAtUtc;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->previewId = isset($data['preview_id']) ? (string) $data['preview_id'] : '';
        $this->announcementId = isset($data['announcement_id']) ? (int) $data['announcement_id'] : 0;
        $this->requestId = isset($data['request_id']) ? (string) $data['request_id'] : '';
        $this->resultId = isset($data['result_id']) ? (string) $data['result_id'] : '';
        $this->resultHash = isset($data['result_hash']) ? (string) $data['result_hash'] : '';
        $this->title = isset($data['title']) ? (string) $data['title'] : '';
        $this->body = isset($data['body']) ? (string) $data['body'] : '';
        $this->createdAtUtc = isset($data['created_at_utc']) ? (string) $data['created_at_utc'] : '';
    }

    /** @return string */
    public function previewId()
    {
        return $this->previewId;
    }

    /** @return int */
    public function announcementId()
    {
        return $this->announcementId;
    }

    /** @return string */
    public function requestId()
    {
        return $this->requestId;
    }

    /** @return string */
    public function resultId()
    {
        return $this->resultId;
    }

    /** @return string */
    public function resultHash()
    {
        return $this->resultHash;
    }

    /** @return string */
    public function title()
    {
        return $this->title;
    }

    /** @return string */
    public function body()
    {
        return $this->body;
    }

    /** @return string */
    public function createdAtUtc()
    {
        return $this->createdAtUtc;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'preview_id' => $this->previewId,
            'announcement_id' => $this->announcementId,
            'request_id' => $this->requestId,
            'result_id' => $this->resultId,
            'result_hash' => $this->resultHash,
            'title' => $this->title,
            'body' => $this->body,
            'created_at_utc' => $this->createdAtUtc,
        );
    }
}
