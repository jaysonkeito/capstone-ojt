<?php

namespace App\Http\Controllers;

use App\Models\OjtLog;
use App\Services\DailyReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Access to an intern's Daily Report PDF — inline preview (used inside the
 * history-page modal) or direct download. The admin may open any intern's
 * report; coordinators and supervisors only for interns in their scope
 * (OjtLogPolicy::viewDailyReport). The route's role middleware lists all
 * three; the policy enforces the per-intern scoping.
 */
class ReportController extends Controller
{
    /**
     * Stream the report as an inline PDF (browser preview, used inside the
     * history-page modal). Pass `?download=1` to force a direct download.
     */
    public function show(Request $request, OjtLog $log): Response
    {
        abort_unless($request->user()->can('viewDailyReport', $log), 403, 'This entry does not belong to one of your interns.');

        $service = new DailyReportService;
        $binary = $service->binary($log);
        $filename = $service->filename($log);

        $disposition = $request->boolean('download')
            ? HeaderUtils::makeDisposition('attachment', $filename, $service->filename($log))
            : HeaderUtils::makeDisposition('inline', $filename, $service->filename($log));

        return new Response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Content-Length' => strlen($binary),
        ]);
    }
}
