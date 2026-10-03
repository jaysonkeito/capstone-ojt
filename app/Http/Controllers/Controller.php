<?php

namespace App\Http\Controllers;

use App\Support\RenderedDocument;
use Illuminate\Http\Response;

abstract class Controller
{
    /**
     * Stream a rendered report — a Word file filled from an admin template — to
     * the browser. Word files always download (a browser can't render .docx in
     * a tab), so the download preference only matters for other formats. Never
     * cached, so a freshly uploaded template or a new duty entry always shows.
     */
    protected function streamDocument(RenderedDocument $document, bool $preferDownload): Response
    {
        return new Response($document->binary, 200, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => $document->contentDisposition($preferDownload),
            'Content-Length' => strlen($document->binary),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
