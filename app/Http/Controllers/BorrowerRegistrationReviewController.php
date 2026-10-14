<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Models\BorrowerRegistration;
use App\Services\BorrowerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BorrowerRegistrationReviewController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', BorrowerRegistration::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:EMPLOYEE,EXTERNAL'],
            'status' => ['nullable', 'in:PENDING,APPROVED,REJECTED'],
        ]);
        $query = BorrowerRegistration::query()->where(function ($query): void {
            $query->where('status', '!=', RegistrationStatus::Pending->value)
                ->orWhereHas('user', fn ($user) => $user->whereNotNull('email_verified_at'));
        })
            ->with(['user.organizationalUnit', 'reviewer']);
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($query) use ($search): void {
                $query->where('company_name', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($user) => $user->where(function ($user) use ($search): void {
                        $user->where('full_name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('employee_no', 'like', '%'.$search.'%');
                    }));
            });
        }
        if ($filters['type'] ?? null) {
            $query->where('registration_type', $filters['type']);
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        return view('registrations.index', [
            'registrations' => $query
                ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")
                ->orderBy('created_at')->orderBy('id')->paginate(10)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function show(BorrowerRegistration $registration): View
    {
        Gate::authorize('view', $registration);

        return view('registrations.show', [
            'registration' => $registration->load(['user.organizationalUnit', 'validIdFile', 'reviewer']),
        ]);
    }

    public function decide(Request $request, BorrowerRegistration $registration, BorrowerRegistrationService $registrations): RedirectResponse
    {
        Gate::authorize('update', $registration);
        $data = $request->validate([
            'decision' => ['required', 'in:APPROVED,REJECTED'],
            'remarks' => ['required_if:decision,REJECTED', 'nullable', 'string', 'max:1000'],
        ]);
        $registrations->decide($registration, $request->user(), RegistrationStatus::from($data['decision']), $data['remarks'] ?? null);

        return redirect()->route('registrations.index')->with('status', 'Registration decision recorded.');
    }
}
