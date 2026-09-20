<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicantRequest;
use App\Http\Requests\ApplicantImportRequest;
use App\Models\Applicant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicantController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));

        $applicants = Applicant::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery->where('application_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->paginate(10)
            ->withQueryString();

        return view('staff.applicants.index', [
            'applicants' => $applicants,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('staff.applicants.form', [
            'applicant' => new Applicant(),
            'formAction' => route(auth()->user()->role.'.applicants.store'),
            'method' => 'POST',
        ]);
    }

    public function store(ApplicantRequest $request): RedirectResponse
    {
        Applicant::create($request->validated());

        return redirect()
            ->route(auth()->user()->role.'.applicants.index')
            ->with('success', 'Applicant added successfully.');
    }

    public function edit(Applicant $applicant): View
    {
        return view('staff.applicants.form', [
            'applicant' => $applicant,
            'formAction' => route(auth()->user()->role.'.applicants.update', $applicant),
            'method' => 'PUT',
        ]);
    }

    public function update(ApplicantRequest $request, Applicant $applicant): RedirectResponse
    {
        $applicant->update($request->validated());

        return redirect()
            ->route(auth()->user()->role.'.applicants.index')
            ->with('success', 'Applicant updated successfully.');
    }

    public function import(ApplicantImportRequest $request): RedirectResponse
    {
        $rows = collect(array_map('str_getcsv', file($request->file('import_file')->getRealPath())));

        if ($rows->isEmpty()) {
            return back()->withErrors(['import_file' => 'The uploaded CSV file is empty.']);
        }

        $header = collect($rows->shift())
            ->map(fn (?string $value) => strtolower(trim((string) $value)));

        $requiredColumns = collect([
            'application_number',
            'first_name',
            'middle_name',
            'last_name',
            'gender',
            'email',
            'contact_number',
            'status',
        ]);

        if ($requiredColumns->diff($header)->isNotEmpty()) {
            return back()->withErrors([
                'import_file' => 'CSV must contain these columns: '.$requiredColumns->implode(', '),
            ]);
        }

        $imported = 0;

        $rows->filter(fn (array $row) => count(array_filter($row, fn ($value) => trim((string) $value) !== '')) > 0)
            ->each(function (array $row) use ($header, &$imported): void {
                $data = $this->normalizeCsvRow($header, $row);

                Applicant::updateOrCreate(
                    ['application_number' => $data['application_number']],
                    $data
                );

                $imported++;
            });

        $redirectTo = $request->validated()['redirect_to'] ?? route(auth()->user()->role.'.applicants.index');

        return redirect()
            ->to($redirectTo)
            ->with('success', "Applicant import complete. {$imported} record(s) processed.");
    }

    private function normalizeCsvRow(Collection $header, array $row): array
    {
        $data = $header->combine(array_pad($row, $header->count(), null))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();

        return [
            'application_number' => $data['application_number'],
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?: null,
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'email' => $data['email'] ?: null,
            'contact_number' => $data['contact_number'] ?: null,
            'status' => $data['status'] ?: 'approved',
        ];
    }
}
