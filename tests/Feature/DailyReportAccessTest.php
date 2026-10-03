<?php

use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeStaff(array $attributes = []): User
{
    return User::create([
        'first_name' => 'Staff',
        'last_name' => 'User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
        ...$attributes,
    ]);
}

function attachReportPhoto(OjtLog $log, User $intern): void
{
    // A real, decodable image — DomPDF skips bytes that aren't a valid picture.
    $img = imagecreatetruecolor(60, 40);
    imagefill($img, 0, 0, imagecolorallocate($img, 37, 99, 235));
    ob_start();
    imagepng($img);
    $png = ob_get_clean();

    // Note: put() returns bool in this Laravel version — use an explicit path.
    $path = "ojt-photos/{$intern->id}/report.png";
    Storage::disk('public')->put($path, $png);
    $log->update(['photo_path' => $path]);
}

beforeEach(function () {
    Storage::fake('public');
});

test('admin can preview and download any intern\'s daily report', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());
    attachReportPhoto($log, $intern);

    $admin = makeStaff(['role' => 'admin']);
    $filename = 'DailyReport_'.$intern->student_id.'_'.today()->format('Y-m-d').'.pdf';

    $preview = $this->actingAs($admin)->get(route('reports.show', $log));
    $preview->assertOk();
    $preview->assertHeader('Content-Type', 'application/pdf');
    $preview->assertHeader('Content-Disposition', "inline; filename=$filename");
    expect($preview->getContent())->toStartWith('%PDF')->toContain('/Image');

    $download = $this->actingAs($admin)->get(route('reports.show', ['log' => $log, 'download' => 1]));
    $download->assertOk();
    $download->assertDownload($filename);
});

test('report route is not accessible to interns', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());
    attachReportPhoto($log, $intern);

    $this->actingAs($intern)->get(route('reports.show', $log))->assertForbidden();
});
