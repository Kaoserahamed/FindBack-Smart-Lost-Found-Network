<?php

namespace App\Http\Controllers;

use App\Models\ReportPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams private report photos.
 *
 * Report photos and claim evidence live on the private `local` disk, which is
 * intentionally NOT web-served. Without this controller the files would be
 * unreachable; with it, every read passes through the ReportPolicy first, so a
 * photo can only be fetched by the report's author or an active member of the
 * owning community.
 */
class PhotoController extends Controller
{
    public function show(Request $request, ReportPhoto $photo): StreamedResponse
    {
        // Object-level authorization on the photo's owning report.
        $this->authorize('view', $photo->report);

        abort_unless(
            $photo->visibility !== 'public',
            403,
            'This photo is not available.'
        );

        abort_unless(
            Storage::disk('local')->exists($photo->storage_path),
            404
        );

        return Storage::disk('local')->response(
            $photo->storage_path,
            null,
            [
                // Never allow the browser to sniff a different content type -
                // an uploaded SVG could otherwise execute script in-page.
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'inline; filename="'.$photo->id.'"',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }
}
