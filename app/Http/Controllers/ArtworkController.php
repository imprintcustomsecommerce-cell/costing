<?php

namespace App\Http\Controllers;

use App\Models\Artwork;
use App\Models\Quotation;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Artwork attached to a quotation.
 *
 * Files live on the private artwork disk and are only ever served back through
 * this controller, so they stay behind the login rather than being reachable by
 * guessing a URL.
 */
class ArtworkController extends Controller
{
    /** Extension => the mime types a genuine file of that type reports. */
    private const ALLOWED = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'svg' => ['image/svg+xml', 'text/plain', 'text/xml', 'application/xml'],
        'pdf' => ['application/pdf'],
        'ai' => ['application/pdf', 'application/postscript', 'application/illustrator'],
        'eps' => ['application/postscript', 'image/x-eps', 'application/eps'],
        'psd' => ['image/vnd.adobe.photoshop', 'application/x-photoshop', 'application/octet-stream'],
        'cdr' => ['application/octet-stream', 'application/cdr', 'application/x-coreldraw'],
    ];

    public function index(Request $request)
    {
        return view('artwork.index', [
            'artworks' => Artwork::with('quotation', 'uploader')
                ->when($request->q, fn ($query, $term) => $query
                    ->where('original_name', 'like', "%{$term}%")
                    ->orWhereHas('quotation', fn ($q) => $q
                        ->where('number', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, Quotation $quotation, AuditService $audit)
    {
        // Keep accepting the original one-file request for existing clients,
        // while the quotation page can submit several artwork rows at once.
        $multiple = $request->hasFile('files');
        $request->validate($multiple ? [
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|max:51200|extensions:'.implode(',', array_keys(self::ALLOWED)),
            'print_locations' => 'required|array',
            'print_locations.*' => 'required|string|max:100',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:255',
        ] : [
            'file' => 'required|file|max:51200|extensions:'.implode(',', array_keys(self::ALLOWED)),
            // Legacy single-file integrations did not send a location. The
            // quotation form always uses the multi-file request above, where
            // each location is required.
            'print_location' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $files = $multiple ? $request->file('files') : [$request->file('file')];
        $locations = $multiple ? $request->input('print_locations', []) : [$request->input('print_location')];
        $notes = $multiple ? $request->input('notes', []) : [$request->input('notes')];
        $uploaded = [];

        foreach ($files as $index => $file) {
            $extension = strtolower($file->getClientOriginalExtension());

            // An extension the file's contents do not support is either a mistake
            // or a disguise. Either way it is not what it claims to be.
            if (! in_array($file->getMimeType(), self::ALLOWED[$extension] ?? [], true)) {
                throw ValidationException::withMessages([
                    $multiple ? 'files.'.$index : 'file' => 'That file is not a genuine .'.$extension.' file.',
                ]);
            }

            $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'artwork';
            $path = $file->storeAs('quotations/'.$quotation->id, $name.'-'.Str::random(8).'.'.$extension, 'artwork');

            if (! $path) {
                throw ValidationException::withMessages(['file' => 'The artwork could not be stored. Please try again.']);
            }

            $artwork = $quotation->artworks()->create([
                'original_name' => $file->getClientOriginalName(),
                'print_location' => $locations[$index],
                'stored_path' => $path,
                'disk' => 'artwork',
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'notes' => $notes[$index] ?? null,
                'uploaded_by' => auth()->id(),
            ]);

            $uploaded[] = $artwork;
            $audit->log('artwork_uploaded', $artwork, [], [
                'quotation' => $quotation->number, 'file' => $artwork->original_name,
            ]);
        }

        return back()->with('success', count($uploaded).' artwork '.(count($uploaded) === 1 ? 'file was' : 'files were').' uploaded.');
    }

    public function download(Artwork $artwork)
    {
        abort_unless($artwork->exists(), 404, 'That artwork file is no longer on disk.');

        return Storage::disk($artwork->disk)->download($artwork->stored_path, $artwork->original_name);
    }

    public function destroy(Artwork $artwork, AuditService $audit)
    {
        $quotation = $artwork->quotation;
        $audit->log('artwork_deleted', $artwork, [
            'quotation' => $quotation?->number, 'file' => $artwork->original_name,
        ], []);

        // The row goes either way: a file already missing must not leave a
        // record pointing at nothing.
        Storage::disk($artwork->disk)->delete($artwork->stored_path);
        $artwork->delete();

        return back()->with('success', 'Artwork removed.');
    }
}
