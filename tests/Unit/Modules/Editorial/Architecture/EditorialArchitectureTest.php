<?php

namespace Tests\Unit\Modules\Editorial\Architecture;

use App\Modules\Editorial\Application\GenerationOrchestrator;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Infrastructure\Generation\StubAiProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

class EditorialArchitectureTest extends TestCase
{
    public function test_domain_has_no_laravel_facades_eloquent_or_http(): void
    {
        foreach ($this->phpFiles(base_path('app/Modules/Editorial/Domain')) as $file) {
            $contents = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/Illuminate\\\\(Support\\\\Facades|Database\\\\Eloquent|Http)/', $contents, $file);
            $this->assertStringNotContainsString('Eloquent\\Model', $contents, $file);
        }
    }

    public function test_no_delivery_wordpress_or_vendor_ai_imports(): void
    {
        foreach ($this->phpFiles(base_path('app/Modules/Editorial')) as $file) {
            $contents = file_get_contents($file);
            foreach ([
                'ABSPATH',
                'wpdb',
                'wp_insert_post',
                'add_action',
                'StudyMentor',
                'SMCE',
                'plugin_version',
                'OpenAI',
                'Anthropic',
                'Gemini',
                'GuzzleHttp\\Client',
                'App\\Modules\\Delivery',
                'Migration\\Delivery',
            ] as $needle) {
                $this->assertStringNotContainsString($needle, $contents, $file.' contains '.$needle);
            }
            $this->assertDoesNotMatchRegularExpression('/\\bwp_[a-zA-Z0-9_]+\\s*\\(/', $contents, $file);
        }
    }

    public function test_orchestrator_depends_on_ai_provider_interface_only(): void
    {
        $ref = new ReflectionClass(GenerationOrchestrator::class);
        $provider = null;
        foreach ($ref->getConstructor()->getParameters() as $param) {
            if ($param->getName() === 'aiProvider') {
                $provider = $param;
            }
        }
        $this->assertNotNull($provider);
        $this->assertSame(AiProviderInterface::class, $provider->getType()->getName());
        $source = file_get_contents($ref->getFileName());
        $this->assertDoesNotMatchRegularExpression('/^use .+StubAiProvider/m', $source);
        $this->assertStringNotContainsString('Infrastructure\\Generation\\StubAiProvider', $source);
    }

    public function test_stub_provider_is_offline_and_deterministic_class(): void
    {
        $source = file_get_contents((new ReflectionClass(StubAiProvider::class))->getFileName());
        $this->assertStringNotContainsString('GuzzleHttp', $source);
        $this->assertStringNotContainsString('Http::', $source);
        $this->assertStringNotContainsString('curl_', $source);
        $this->assertStringContainsString('PROVIDER_CODE', $source);
    }

    public function test_builders_and_validators_present(): void
    {
        foreach ([
            'Blueprint/ContentBlueprintBuilder.php',
            'Blueprint/ContentBlueprintValidator.php',
            'PromptContext/PromptContextBuilder.php',
            'PromptContext/PromptContextValidator.php',
            'PromptPackage/PromptPackageBuilder.php',
            'PromptPackage/PromptPackageValidator.php',
            'GenerationRequest/GenerationRequestBuilder.php',
            'GenerationRequest/GenerationRequestValidator.php',
            'GenerationResult/GenerationResultBuilder.php',
            'GenerationResult/GenerationResultValidator.php',
        ] as $relative) {
            $this->assertFileExists(base_path('app/Modules/Editorial/Domain/'.$relative));
        }
    }

    public function test_no_recurring_schedule_registration_in_editorial_module(): void
    {
        foreach ($this->phpFiles(base_path('app/Modules/Editorial')) as $file) {
            $contents = file_get_contents($file);
            $this->assertStringNotContainsString('Schedule::', $contents, $file);
            $this->assertStringNotContainsString('->daily(', $contents, $file);
            $this->assertStringNotContainsString('->hourly(', $contents, $file);
        }
        $provider = file_get_contents(base_path('app/Providers/EditorialServiceProvider.php'));
        $this->assertStringNotContainsString('Schedule::', $provider);
    }

    public function test_announcement_and_acquisition_modules_untouched_markers(): void
    {
        $this->assertDirectoryExists(base_path('app/Modules/Announcement'));
        $this->assertDirectoryExists(base_path('app/Modules/Acquisition'));
        foreach ($this->phpFiles(base_path('app/Modules/Editorial')) as $file) {
            $contents = file_get_contents($file);
            $this->assertStringNotContainsString('Announcement::query()->update', $contents, $file);
            $this->assertStringNotContainsString('Source::query()->update', $contents, $file);
        }
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
