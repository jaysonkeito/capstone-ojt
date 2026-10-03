<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * A finished, ready-to-stream document — either a filled Word file (when an
 * admin template is active for the form) or the built-in PDF fallback. Bundles
 * the bytes with the metadata a controller needs to return the right response.
 */
final readonly class RenderedDocument
{
    public function __construct(
        public string $binary,
        public string $filename,
        public string $mime,
        public string $extension,
    ) {}

    /**
     * A .docx filled from an admin template.
     */
    public static function word(string $binary, string $filename): self
    {
        return new self(
            $binary,
            $filename,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'docx',
        );
    }

    /**
     * The built-in dompdf fallback.
     */
    public static function pdf(string $binary, string $filename): self
    {
        return new self($binary, $filename, 'application/pdf', 'pdf');
    }

    /**
     * Only a PDF can be previewed inline in the browser; a Word file is always
     * downloaded (browsers can't render it in a tab). So force a download for
     * .docx, and for a PDF honour the caller's preference.
     */
    public function contentDisposition(bool $preferDownload): string
    {
        $type = ($preferDownload || $this->extension !== 'pdf') ? 'attachment' : 'inline';

        return HeaderUtils::makeDisposition($type, $this->filename, $this->filename);
    }
}
