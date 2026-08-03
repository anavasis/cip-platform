<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\ConnectorType;
use Illuminate\Database\Seeder;

class ConnectorTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'type' => 'http_rest',
                'name' => 'HTTP REST',
                'description' => 'Generic HTTP REST connector (metadata only)',
                'metadata' => ['category' => 'integration'],
            ],
            [
                'type' => 'webhook',
                'name' => 'Webhook',
                'description' => 'Generic webhook connector (metadata only)',
                'metadata' => ['category' => 'integration'],
            ],
            [
                'type' => 'custom',
                'name' => 'Custom',
                'description' => 'Custom connector type (metadata only)',
                'metadata' => ['category' => 'custom'],
            ],
        ];

        foreach ($types as $type) {
            ConnectorType::updateOrCreate(
                ['type' => $type['type']],
                $type
            );
        }
    }
}
