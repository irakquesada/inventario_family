<?php

namespace App\Services\People;

use App\Models\AbsenceRequest;
use App\Models\EmployeeAbsenceBalance;
use App\Models\User;
use App\Notifications\AbsenceRequestNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewAbsenceRequest
{
    public function handle(AbsenceRequest $request, User $reviewer, string $decision, ?string $comment): AbsenceRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $decision, $comment): AbsenceRequest {
            $request = AbsenceRequest::query()->with(['type', 'employee.user'])->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['review' => 'Solo se pueden revisar solicitudes pendientes.']);
            }
            if ($decision === 'approved' && $request->type->deducts_balance) {
                $year = $request->starts_on->year;
                $balance = EmployeeAbsenceBalance::query()->where('employee_id', $request->employee_id)->where('year', $year)->lockForUpdate()->first();
                $entitled = (float) ($balance?->entitled_days ?? 0);
                $used = (float) AbsenceRequest::query()->where('employee_id', $request->employee_id)->where('status', 'approved')->whereYear('starts_on', $year)->whereHas('type', fn ($query) => $query->where('deducts_balance', true))->sum('requested_days');
                if ($used + (float) $request->requested_days > $entitled) {
                    throw ValidationException::withMessages(['review' => 'La solicitud supera el saldo disponible del empleado.']);
                }
            }
            $request->update(['status' => $decision, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_comment' => $comment]);
            if ($request->employee->user) {
                $request->employee->user->notify(new AbsenceRequestNotification($request, $decision));
            }

            return $request;
        });
    }
}
