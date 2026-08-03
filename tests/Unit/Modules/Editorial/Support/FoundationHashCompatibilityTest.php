<?php

namespace Tests\Unit\Modules\Editorial\Support;

use App\Modules\Editorial\Domain\Support\FoundationCanonicalHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationHashCompatibilityTest extends TestCase
{
    /**
     * Fixed vectors computed from Foundation canonicalize + json_encode (no wp_json_encode).
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function vectors(): array
    {
        return [
            'nested_assoc_list_unicode' => [
                [
                    'z' => 1,
                    'a' => ['c' => 3, 'b' => 2],
                    'list' => [10, 20, 30],
                    'empty' => [],
                    'str' => 'hello/world',
                    'unicode' => 'ελ',
                ],
                'ee1ad30c1efb15942f569ae0a3978edbd99ca2c07d2dcc2c996a17a49d4d55f7',
            ],
            'request_binding_int_announcement' => [
                [
                    'announcement_id' => 42,
                    'package_id' => 'pp_42_abcdef123456',
                    'package_hash' => str_repeat('c', 64),
                    'model' => ['model_id' => 'smce.stub.deterministic', 'model_version' => '1'],
                    'parameters' => [
                        'temperature' => 0,
                        'max_tokens' => 2048,
                        'response_format' => 'text',
                        'seed' => 1,
                    ],
                ],
                'a67bf820eef8fb4f82503bf0de51336ab17725130415ff02ff25c4eca341c7ec',
            ],
            'request_binding_uuid_announcement' => [
                [
                    'announcement_id' => '11111111-2222-3333-4444-555555555555',
                    'package_id' => 'pp_42_abcdef123456',
                    'package_hash' => str_repeat('c', 64),
                    'model' => ['model_id' => 'smce.stub.deterministic', 'model_version' => '1'],
                    'parameters' => [
                        'temperature' => 0,
                        'max_tokens' => 2048,
                        'response_format' => 'text',
                        'seed' => 1,
                    ],
                ],
                '060bd0208b0ad82e806c1884fd1c28cbb96f13fe536fde0fee74408823bab140',
            ],
        ];
    }

    #[DataProvider('vectors')]
    public function test_fixed_vectors_match_foundation_json_encode_path(array $payload, string $expected): void
    {
        $this->assertSame($expected, FoundationCanonicalHasher::hash($payload));
        $encoded = json_encode(FoundationCanonicalHasher::canonicalize($payload));
        $this->assertIsString($encoded);
        $this->assertSame($expected, hash('sha256', $encoded));
    }

    public function test_key_order_does_not_affect_hash(): void
    {
        $a = ['b' => 2, 'a' => 1];
        $b = ['a' => 1, 'b' => 2];
        $this->assertSame(FoundationCanonicalHasher::hash($a), FoundationCanonicalHasher::hash($b));
    }
}
