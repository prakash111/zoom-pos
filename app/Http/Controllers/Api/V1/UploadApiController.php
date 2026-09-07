<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Secure tenant file uploads for SDUI `file_picker` inputs.
 *
 * Every endpoint here accepts a single multipart `file` and returns a public
 * storage URL. Only non-executable image / PDF documents are ever stored:
 * scripts, binaries, archives and markup are rejected by an allow-list, a
 * hard deny-list (covering double extensions), and a MIME re-check.
 */
class UploadApiController extends Controller
{
    use ResolvesTenantSyncContext;

    /** Non-executable document types a tenant may attach to a record. */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic', 'heif'];

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/heic-sequence',
        'image/heif-sequence',
        'application/pdf',
    ];

    /**
     * Extensions refused outright, even if they appear only as a secondary
     * segment of the filename (e.g. `rx.pdf.php`, `scan.jpg.sh`).
     */
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht', 'inc',
        'sh', 'bash', 'zsh', 'ksh', 'exe', 'bin', 'com', 'msi', 'bat', 'cmd', 'ps1',
        'js', 'mjs', 'cjs', 'jse', 'vbs', 'vbe', 'wsf', 'wsh', 'jar', 'apk', 'aab',
        'app', 'deb', 'rpm', 'dmg', 'pkg', 'run', 'out', 'elf', 'so', 'dll', 'dylib',
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'xml', 'py', 'pyc', 'rb', 'pl', 'cgi',
        'asp', 'aspx', 'jsp', 'jspx', 'scr', 'lnk', 'reg', 'gadget', 'hta',
    ];

    /**
     * POST /api/tenant/uploads/prescription-doc
     *
     * Prescription photo or PDF from the New Prescription Intake form.
     */
    public function uploadRxAttachment(Request $request): JsonResponse
    {
        return $this->storeSecureUpload(
            $request,
            folder: 'prescriptions',
            auditEvent: 'pharmacy.rx_attachment_uploaded',
        );
    }

    private function storeSecureUpload(Request $request, string $folder, string $auditEvent): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimes:jpg,jpeg,png,webp,pdf,heic,heif',
            ],
        ], [
            'file.required' => 'Choose a photo or PDF to upload.',
            'file.mimes' => 'Only photos (JPG, PNG, WEBP, HEIC) and PDF documents are allowed.',
            'file.max' => 'The file may not be larger than 10 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first('file'),
                'details' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $originalName = (string) $file->getClientOriginalName();

        // Reject script / binary disguises anywhere in the filename, so a
        // double extension such as `rx.pdf.php` never reaches disk.
        foreach (preg_split('/[.\s]+/', strtolower($originalName), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $segment) {
            if (in_array($segment, self::BLOCKED_EXTENSIONS, true)) {
                return $this->rejected('Executable, script and archive files are strictly prohibited.');
            }
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->rejected('Unsupported file type. Upload a photo (JPG, PNG, WEBP, HEIC) or a PDF.');
        }

        // Content-sniffed MIME must line up with the allow-list. HEIC/HEIF is
        // often reported as octet-stream by libmagic, so allow that pairing
        // only when the extension itself is heic/heif.
        $mime = strtolower((string) $file->getMimeType());
        $mimeOk = in_array($mime, self::ALLOWED_MIME_TYPES, true)
            || (in_array($extension, ['heic', 'heif'], true) && in_array($mime, ['application/octet-stream', ''], true));
        if (! $mimeOk) {
            return $this->rejected('The file content does not match an allowed image or PDF type.');
        }

        $fileName = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs("tenant-uploads/{$company->id}/{$folder}", $fileName, 'public');

        if ($path === false || $path === '') {
            return response()->json([
                'success' => false,
                'error' => 'The file could not be stored. Please try again.',
            ], 500);
        }

        AuditLog::record($auditEvent, $company->id, $user?->id, [
            'path' => $path,
            'mime' => $mime,
            'size' => $file->getSize(),
        ]);

        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'url' => $url,
            'file_url' => $url,
            'path' => $path,
            'file_name' => $originalName,
            'mime_type' => $mime,
            'size' => $file->getSize(),
        ]);
    }

    private function rejected(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message], 422);
    }
}
