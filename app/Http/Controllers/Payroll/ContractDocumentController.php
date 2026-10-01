<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\EmploymentContract;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractDocumentController extends Controller
{
    public function __invoke(EmploymentContract $contract): StreamedResponse
    {
        $own = $contract->employee()->where('user_id', auth()->id())->exists();
        abort_unless($own || auth()->user()?->can('payroll.manage'), 403);
        abort_unless($contract->document_path && Storage::disk('local')->exists($contract->document_path), 404);

        return Storage::disk('local')->download($contract->document_path, $contract->document_name ?: 'contrato.pdf');
    }
}
