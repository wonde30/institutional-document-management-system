<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\FileObject;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoFileGenerator
{
    /**
     * Generate a synthetic standards-compliant PDF document file and create FileObject record.
     */
    public static function createPdf(
        string $originalFilename,
        string $title,
        string $refNumber,
        string $body,
        int $uploadedBy = 1,
        string $year = '2026'
    ): FileObject {
        $uuid = (string) Str::uuid();
        $storageKey = "documents/{$year}/{$uuid}.pdf";

        // Build valid PDF-1.4 stream
        $notice = 'DEMONSTRATION DATA - NOT AN OFFICIAL WOLLO UNIVERSITY RECORD';
        $content = self::buildPdfContent($title, $refNumber, $notice, $body);

        $disk = Storage::disk('local');
        $disk->put($storageKey, $content);
        $fullPath = $disk->path($storageKey);

        $sha256 = hash_file('sha256', $fullPath);
        $size = filesize($fullPath);

        return FileObject::firstOrCreate(
            ['storage_key' => $storageKey],
            [
                'storage_disk' => 'local',
                'original_filename' => $originalFilename,
                'detected_mime' => 'application/pdf',
                'byte_size' => $size,
                'sha256' => $sha256,
                'uploaded_by' => $uploadedBy,
                'uploaded_at' => Carbon::now(),
                'scan_status' => 'clean',
                'scanned_at' => Carbon::now(),
                'storage_status' => 'ready',
            ]
        );
    }

    /**
     * Generate a synthetic CSV file and create FileObject record.
     */
    public static function createCsv(
        string $originalFilename,
        string $title,
        array $headers,
        array $rows,
        int $uploadedBy = 1,
        string $year = '2026'
    ): FileObject {
        $uuid = (string) Str::uuid();
        $storageKey = "documents/{$year}/{$uuid}.csv";

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, ['# DEMONSTRATION DATA - NOT AN OFFICIAL WOLLO UNIVERSITY RECORD']);
        fputcsv($fp, ["# Title: {$title}"]);
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        $disk = Storage::disk('local');
        $disk->put($storageKey, $content);
        $fullPath = $disk->path($storageKey);

        $sha256 = hash_file('sha256', $fullPath);
        $size = filesize($fullPath);

        return FileObject::firstOrCreate(
            ['storage_key' => $storageKey],
            [
                'storage_disk' => 'local',
                'original_filename' => $originalFilename,
                'detected_mime' => 'text/csv',
                'byte_size' => $size,
                'sha256' => $sha256,
                'uploaded_by' => $uploadedBy,
                'uploaded_at' => Carbon::now(),
                'scan_status' => 'clean',
                'scanned_at' => Carbon::now(),
                'storage_status' => 'ready',
            ]
        );
    }

    /**
     * Generate a synthetic JSON file and create FileObject record.
     */
    public static function createJson(
        string $originalFilename,
        string $title,
        array $payload,
        int $uploadedBy = 1,
        string $year = '2026'
    ): FileObject {
        $uuid = (string) Str::uuid();
        $storageKey = "documents/{$year}/{$uuid}.json";

        $fullPayload = [
            '_disclaimer' => 'DEMONSTRATION DATA - NOT AN OFFICIAL WOLLO UNIVERSITY RECORD',
            '_title' => $title,
            '_generated_at' => Carbon::now()->toIso8601String(),
            'data' => $payload,
        ];

        $content = json_encode($fullPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $disk = Storage::disk('local');
        $disk->put($storageKey, $content);
        $fullPath = $disk->path($storageKey);

        $sha256 = hash_file('sha256', $fullPath);
        $size = filesize($fullPath);

        return FileObject::firstOrCreate(
            ['storage_key' => $storageKey],
            [
                'storage_disk' => 'local',
                'original_filename' => $originalFilename,
                'detected_mime' => 'application/json',
                'byte_size' => $size,
                'sha256' => $sha256,
                'uploaded_by' => $uploadedBy,
                'uploaded_at' => Carbon::now(),
                'scan_status' => 'clean',
                'scanned_at' => Carbon::now(),
                'storage_status' => 'ready',
            ]
        );
    }

    /**
     * Build minimal standards-compliant PDF-1.4 string.
     */
    private static function buildPdfContent(string $title, string $refNumber, string $notice, string $body): string
    {
        $sanTitle = self::escapePdfString($title);
        $sanRef = self::escapePdfString("Ref: {$refNumber}");
        $sanNotice = self::escapePdfString($notice);
        $sanBody = self::escapePdfString($body);

        $stream = "BT\n" .
            "/F1 14 Tf 50 740 Td ({$sanTitle}) Tj\n" .
            "/F1 10 Tf 0 -20 Td ({$sanRef}) Tj\n" .
            "/F1 9 Tf 0 -25 Td ({$sanNotice}) Tj\n" .
            "/F1 11 Tf 0 -35 Td ({$sanBody}) Tj\n" .
            "ET";

        $streamLength = strlen($stream);

        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Length {$streamLength} >>\nstream\n{$stream}\nendstream\nendobj\n";
        $pdf .= "xref\n0 5\n";
        $pdf .= "0000000000 65535 f \n";
        $pdf .= "0000000009 00000 n \n";
        $pdf .= "0000000058 00000 n \n";
        $pdf .= "0000000115 00000 n \n";
        $pdf .= "0000000300 00000 n \n";
        $pdf .= "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n450\n%%EOF";

        return $pdf;
    }

    private static function escapePdfString(string $str): string
    {
        return addcslashes($str, "()\\");
    }
}
