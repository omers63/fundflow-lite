<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DirectMessageAttachmentController extends Controller
{
    /**
     * Open or download a file attached to a direct message (only for sender or recipient).
     */
    public function show(Request $request, DirectMessage $message, int $index): StreamedResponse
    {
        $userId = (int) $request->user()->id;

        if ((int) $message->from_user_id !== $userId && (int) $message->to_user_id !== $userId) {
            abort(403);
        }

        $attachments = $message->attachments;
        if (! is_array($attachments) || ! array_key_exists($index, $attachments)) {
            abort(404);
        }

        $path = $attachments[$index];
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $filename = basename($path);

        return Storage::disk('public')->response(
            $path,
            $filename,
            [
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ],
        );
    }
}
