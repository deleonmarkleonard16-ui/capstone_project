<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class GuidanceAnalyticsExportTest extends TestCase
{
    use RefreshDatabase;

    private function record(): ServiceRequest
    {
        return ServiceRequest::create([
            'service' => 'good-moral', 'reference' => ServiceRequest::newReference('good-moral'),
            'first_name' => 'Ana <Student>', 'last_name' => 'Santos', 'student_number' => '2026-100',
            'student_status' => 'student', 'course' => 'BSIT', 'purpose' => 'Employment', 'status' => 'completed',
        ]);
    }

    public function test_both_roles_receive_official_summary_and_record_layouts_and_printable_pdfs(): void
    {
        $record = $this->record();
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['summary', 'records'] as $type) {
                $url = route($role.'.analytics.export', ['type' => $type]);
                $html = $this->get($url)->assertOk()->assertSee('class="assessment-letterhead"', false)
                    ->assertSee('data:image/png;base64,', false)->assertSee('A4 portrait', false)
                    ->assertSee('Academic Year')->assertSee('Date Generated');
                if ($type === 'summary') {
                    $html->assertSee('Overview')->assertSee('Good Moral Total')->assertSee('Program Testing Volume');
                } else {
                    $html->assertSee($record->reference)->assertSee('Student ID')->assertSee('Field')->assertSee('Details')
                        ->assertSee('Ana &lt;Student&gt;', false)->assertDontSee('Ana <Student>', false);
                }
                $pdf = $this->get($url.'&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')
                    ->assertHeader('Cache-Control', 'no-store, private');
                $this->assertStringStartsWith('%PDF-', $pdf->getContent());
                $this->assertStringContainsString('inline;', $pdf->headers->get('Content-Disposition'));
                $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0(?:\.0+)? 0(?:\.0+)? 595\.\d+ 841\.\d+\]/', $pdf->getContent());
                $this->assertStringContainsString('/Subtype /Image', $pdf->getContent());
            }
            $csv = $this->get(route($role.'.analytics.export.excel'))->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $csv->assertSee($record->reference)->assertDontSee('<img', false);
        }
    }

    public function test_analytics_previews_offer_exports_without_automatic_printing_for_both_roles(): void
    {
        $this->record();
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['summary', 'records'] as $type) {
                $this->get(route($role.'.analytics.export', ['type' => $type, 'format' => 'html', 'preview' => 1, 'auto_print' => 1]))
                    ->assertOk()->assertViewIs('exports.preview')->assertSee('Preview export actions')
                    ->assertSee('Download CSV')->assertSee('Download DOCX')->assertSee('Active Filters')
                    ->assertDontSee('<iframe id="report-preview"', false)->assertDontSee('Official PSU San Carlos City Campus letterhead')->assertSee('Report data')
                    ->assertViewHas('document', fn ($document) => !$document['autoPrint'] && $document['reportType'] === $type);
            }
        }
    }

    public function test_docx_uses_official_header_and_footer_and_keeps_summary_and_record_data(): void
    {
        $record = $this->record();
        foreach (['admin', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['summary', 'records'] as $type) {
                $response = $this->get(route($role.'.analytics.export', ['format' => 'docx', 'type' => $type]))
                    ->assertOk()->assertDownload();
                $path = tempnam(sys_get_temp_dir(), 'analytics_docx_');
                $zip = new ZipArchive();
                try {
                    file_put_contents($path, $response->getContent());
                    $this->assertTrue($zip->open($path));
                    $xml = $zip->getFromName('word/document.xml');
                    $this->assertStringContainsString($type === 'summary' ? 'Good Moral Total' : $record->reference, $xml);
                    $this->assertStringContainsString('w:top="2835"', $xml);
                    $this->assertStringContainsString('<w:tblW w:w="5000" w:type="pct"', $xml);
                    $this->assertStringNotContainsString('<w:tblW w:w="100" w:type="pct"', $xml);
                    $this->assertStringContainsString('<v:imagedata', $zip->getFromName('word/header1.xml'));
                    $this->assertStringContainsString('<v:imagedata', $zip->getFromName('word/footer1.xml'));
                    $images = [];
                    for ($index = 0; $index < $zip->numFiles; $index++) {
                        if (str_starts_with($zip->getNameIndex($index), 'word/media/')) {
                            $images[] = hash('sha256', $zip->getFromIndex($index));
                        }
                    }
                    foreach (['header', 'footer'] as $part) {
                        $this->assertContains(hash_file('sha256', public_path('images/admission/download-letterhead-'.$part.'.png')), $images);
                    }
                } finally {
                    $zip->close();
                    unlink($path);
                }
            }
        }
    }
}
