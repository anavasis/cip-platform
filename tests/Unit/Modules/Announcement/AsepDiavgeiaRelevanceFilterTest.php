<?php

namespace Tests\Unit\Modules\Announcement;

use App\Modules\Announcement\Domain\AsepDiavgeiaRelevanceFilter;
use PHPUnit\Framework\TestCase;

class AsepDiavgeiaRelevanceFilterTest extends TestCase
{
    private AsepDiavgeiaRelevanceFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new AsepDiavgeiaRelevanceFilter;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function relevantTitleProvider(): array
    {
        return [
            'proclamation 3K/2026' => ['Προκήρυξη 3Κ/2026'],
            'proclamation 6K/2026 for positions' => ['Προκήρυξη 6Κ/2026 για πλήρωση θέσεων'],
            'application submission' => ['Υποβολή αιτήσεων για την προκήρυξη'],
            'application submission start' => ['Έναρξη υποβολής αιτήσεων'],
            'supporting documents invitation' => ['Πρόσκληση υποβολής δικαιολογητικών'],
            'provisional results' => ['Προσωρινά αποτελέσματα'],
            'provisional lists' => ['Προσωρινοί πίνακες'],
            'objections submission' => ['Υποβολή ενστάσεων'],
            'final results' => ['Οριστικά αποτελέσματα'],
            'final lists' => ['Οριστικοί πίνακες'],
            'appointment lists' => ['Πίνακες διοριστέων'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function irrelevantTitleProvider(): array
    {
        return [
            'payment order' => ['ΕΝΤΑΛΜΑ ΠΛΗΡΩΜΗΣ'],
            'direct award decision' => ['ΑΠΟΦΑΣΗ ΑΠΕΥΘΕΙΑΣ ΑΝΑΘΕΣΗΣ'],
            'committee appointment' => ['Ορισμός μελών Επιτροπής Επιλογής Στελεχών του Δημοσίου'],
            'decision forwarding on replacements' => ['ΑΠΟΣΤΟΛΗ ΑΠΟΦΑΣΗΣ ΕΠΙ ΑΝΑΠΛΗΡΩΣΕΩΝ'],
            'org chart amendment' => ['Τροποποίηση οργανογράμματος'],
            'unrelated administrative title' => ['Ανακοίνωση γενικών διοικητικών θεμάτων'],
        ];
    }

    /**
     * @dataProvider relevantTitleProvider
     */
    public function test_accepts_recruitment_lifecycle_titles(string $title): void
    {
        $this->assertTrue($this->filter->isRelevantTitle($title));
    }

    /**
     * @dataProvider irrelevantTitleProvider
     */
    public function test_rejects_administrative_noise_titles(string $title): void
    {
        $this->assertFalse($this->filter->isRelevantTitle($title));
    }

    public function test_rejects_empty_title(): void
    {
        $this->assertFalse($this->filter->isRelevantTitle(''));
        $this->assertFalse($this->filter->isRelevantTitle('   '));
    }

    public function test_normalizes_case_and_whitespace(): void
    {
        $this->assertTrue($this->filter->isRelevantTitle('  ΠΡΟΚΉΡΥΞΗ   3Κ/2026  '));
        $this->assertFalse($this->filter->isRelevantTitle('  ΕΝΤΑΛΜΑ   ΠΛΗΡΩΜΗΣ  '));
    }
}
