<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\AbsenceRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbsenceAttachmentController extends Controller
{
    public function __invoke(AbsenceRequest $absenceRequest): StreamedResponse
    {
        $own = $absenceRequest->employee()->where('user_id', auth()->id())->exists();
        abort_unless($own || auth()->user()?->can('people.manage'), 403);
        abort_unless($absenceRequest->attachment_path && Storage::disk('local')->exists($absenceRequest->attachment_path), 404);

        return Storage::disk('local')->download($absenceRequest->attachment_path, $absenceRequest->attachment_name ?: 'justificante');
    }
}
