<?php

declare(strict_types=1);

namespace App\Mail;

use App\Reports\ExportFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email delivering a due scheduled/subscription report to its recipients
 * (R29.6).
 *
 * When a scheduled or subscription report is due, the Report_Engine runs it
 * (defaulting to the previous calendar month) and emails the result to the
 * subscribed recipients (R29.6). This mailable attaches the rendered export
 * file (Excel/CSV/PDF) and summarises the report in the body. It is queued
 * ({@see ShouldQueue}) so delivery happens off the dispatcher's critical path,
 * consistent with the design's "all email delivered via queued jobs" rule
 * (design: Notifications).
 */
class ScheduledReportMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $reportLabel  A human label for the report (e.g. "Monthly sales").
     * @param  string  $reportType  The report type value.
     * @param  string  $filePath  Absolute path to the rendered export file to attach.
     * @param  ExportFormat  $format  The attachment's format.
     * @param  string|null  $periodFrom  Resolved period start (ISO8601) for the body.
     * @param  string|null  $periodTo  Resolved period end (ISO8601) for the body.
     * @param  int  $rowCount  Number of rows in the report.
     */
    public function __construct(
        public readonly string $reportLabel,
        public readonly string $reportType,
        public readonly string $filePath,
        public readonly ExportFormat $format,
        public readonly ?string $periodFrom = null,
        public readonly ?string $periodTo = null,
        public readonly int $rowCount = 0,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Scheduled report: %s', $this->reportLabel),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.scheduled-report',
            with: [
                'reportLabel' => $this->reportLabel,
                'reportType' => $this->reportType,
                'periodFrom' => $this->periodFrom,
                'periodTo' => $this->periodTo,
                'rowCount' => $this->rowCount,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath)
                ->as(sprintf('%s.%s', $this->reportType, $this->format->extension()))
                ->withMime($this->format->mimeType()),
        ];
    }
}
