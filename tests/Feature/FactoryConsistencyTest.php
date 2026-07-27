<?php

namespace Tests\Feature;

use App\Models\Digital;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FactoryConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('documents');
    }

    public function test_letter_factories_support_with_and_without_berkas()
    {
        $incomingWithout = Incoming::factory()->withoutBerkas()->create();
        $incomingWith = Incoming::factory()->withBerkas()->create();
        $outcomingWithout = Outcoming::factory()->withoutBerkas()->create();
        $outcomingWith = Outcoming::factory()->withBerkas()->create();

        $this->assertNull($incomingWithout->filelist_id);
        $this->assertNotNull($incomingWith->filelist_id);
        $this->assertNotNull($incomingWith->filelist->classification_id);
        $this->assertNull($outcomingWithout->filelist_id);
        $this->assertNotNull($outcomingWith->filelist_id);
        $this->assertNotNull($outcomingWith->filelist->classification_id);
    }

    public function test_srikandi_factory_cannot_be_combined_with_berkas()
    {
        $incoming = Incoming::factory()->srikandi()->withBerkas()->create();
        $outcoming = Outcoming::factory()->srikandi()->withBerkas()->create();

        $this->assertNull($incoming->nomor_agenda);
        $this->assertNull($incoming->filelist_id);
        $this->assertTrue($incoming->is_srikandi);
        $this->assertNull($outcoming->filelist_id);
        $this->assertTrue($outcoming->is_digital);
        $this->assertTrue($outcoming->is_srikandi);
    }

    public function test_document_factories_create_unique_valid_pdf_files()
    {
        $documents = [
            Incoming::factory()->create(),
            Outcoming::factory()->create(),
            Digital::factory()->create(),
        ];

        $paths = [];

        foreach ($documents as $document) {
            $paths[] = $document->url;
            Storage::disk('documents')->assertExists($document->url);
            $this->assertStringStartsWith(
                '%PDF-1.4',
                Storage::disk('documents')->get($document->url)
            );
        }

        $this->assertCount(count($paths), array_unique($paths));
    }

    public function test_user_factory_uses_configured_start_year_through_current_year()
    {
        Carbon::setTestNow('2026-07-26 12:00:00');
        config(['app.start_year' => 2025]);

        try {
            $users = User::factory()->count(50)->make();

            foreach ($users as $user) {
                $this->assertGreaterThanOrEqual(2025, $user->tahun);
                $this->assertLessThanOrEqual(2026, $user->tahun);
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
