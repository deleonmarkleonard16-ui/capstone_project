<?php

namespace App\Http\Controllers;

use App\Models\GuidanceTestBatch;
use App\Models\ServiceRequest;
use App\Services\GuidanceCategories;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminArchiveController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'year' => 'nullable|integer|min:1900|max:9999', 'month' => 'nullable|integer|between:1,12',
            'request_type' => 'nullable|in:individual,batch', 'test_type' => 'nullable|in:psychological,personality,career',
            'search' => 'nullable|string|max:100',
            'testing_page' => 'nullable|integer|min:1', 'testing_batches_page' => 'nullable|integer|min:1',
            'good_moral_page' => 'nullable|integer|min:1', 'exit_form_page' => 'nullable|integer|min:1',
        ]);
        $archiveYears = $this->archiveYears();
        $year = $request->query('year');
        $month = $request->query('month');
        $search = trim((string) $request->query('search', ''));
        $testType = $request->query('test_type');
        $requestType = $request->query('request_type');

        // Section 1: Testing Request Archive
        $testingQuery = ServiceRequest::where('service', 'testing')
            ->where(function ($q) {
                $q->whereNotNull('archived_at')
                  ->orWhereIn('status', ['completed', 'declined', 'cancelled', 'void']);
            });

        if ($year) {
            $testingQuery->whereYear('created_at', $year);
        }
        if ($month) {
            $testingQuery->whereMonth('created_at', $month);
        }
        if ($search !== '') {
            $testingQuery->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }
        if ($testType) {
            $testingQuery->whereJsonContains('tests', $testType);
        }
        $this->filterRequestType($testingQuery, $requestType);
        $testingRequests = $testingQuery->latest('updated_at')->orderByDesc('id')->paginate(20, ['*'], 'testing_page')->withQueryString();

        // Testing batch rosters use appointments rather than service requests.
        $batchQuery = GuidanceTestBatch::query()
            ->whereIn('test_type', array_values(GuidanceCategories::LABELS))
            ->where(fn ($q) => $q->whereNotNull('archived_at')->orWhere('status', 'Completed'))
            ->withCount(['appointments', 'makeupAppointments']);
        if ($requestType === 'individual') {
            $batchQuery->whereRaw('1 = 0');
        }
        if ($testType) {
            $batchQuery->where('test_type', GuidanceCategories::LABELS[$testType] ?? $testType);
        }
        if ($year) {
            $batchQuery->whereYear('created_at', $year);
        }
        if ($month) {
            $batchQuery->whereMonth('created_at', $month);
        }
        if ($search !== '') {
            $batchQuery->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('batch_name', 'like', $like)
                  ->orWhere('course', 'like', $like)
                  ->orWhere('year_section', 'like', $like)
                  ->orWhere('status', 'like', $like);
                foreach (['appointments', 'makeupAppointments'] as $relation) {
                    $q->orWhereHas($relation, fn ($a) => $a
                        ->where('student_id_number', 'like', $like)
                        ->orWhere('request_code', 'like', $like)
                        ->orWhereHas('applicant', fn ($student) => $student
                            ->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)));
                }
            });
        }
        $testingBatches = $batchQuery->latest('updated_at')->orderByDesc('batch_id')->paginate(20, ['*'], 'testing_batches_page')->withQueryString();

        // Section 2: Good Moral Archive
        $goodMoralQuery = ServiceRequest::where('service', 'good-moral')
            ->where(function ($q) {
                $q->whereNotNull('archived_at')
                  ->orWhereIn('status', ['completed', 'declined', 'cancelled', 'void']);
            });

        if ($year) {
            $goodMoralQuery->whereYear('created_at', $year);
        }
        if ($month) {
            $goodMoralQuery->whereMonth('created_at', $month);
        }
        if ($search !== '') {
            $goodMoralQuery->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }
        $this->filterRequestType($goodMoralQuery, $requestType);
        $goodMoralRequests = $goodMoralQuery->latest('updated_at')->orderByDesc('id')->paginate(20, ['*'], 'good_moral_page')->withQueryString();

        // Section 3: Exit Form Archive
        $exitFormQuery = ServiceRequest::where('service', 'exit-form')
            ->where(function ($q) {
                $q->whereNotNull('archived_at')
                  ->orWhereIn('status', ['completed', 'declined', 'cancelled', 'void']);
            });

        if ($year) {
            $exitFormQuery->whereYear('created_at', $year);
        }
        if ($month) {
            $exitFormQuery->whereMonth('created_at', $month);
        }
        if ($search !== '') {
            $exitFormQuery->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }
        $this->filterRequestType($exitFormQuery, $requestType);
        $exitFormRequests = $exitFormQuery->latest('updated_at')->orderByDesc('id')->paginate(20, ['*'], 'exit_form_page')->withQueryString();

        return view('admin.archive', compact(
            'archiveYears',
            'testingRequests',
            'testingBatches',
            'goodMoralRequests',
            'exitFormRequests',
            'year',
            'month',
            'search',
            'testType',
            'requestType'
        ));
    }

    private function archiveYears(): array
    {
        $expression = match (\Illuminate\Support\Facades\DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', created_at) AS INTEGER)",
            'pgsql' => 'CAST(EXTRACT(YEAR FROM created_at) AS INTEGER)',
            default => 'YEAR(created_at)',
        };
        $requestYears = ServiceRequest::whereIn('service', ['testing', 'good-moral', 'exit-form'])
            ->where(fn ($query) => $query->whereNotNull('archived_at')->orWhereIn('status', ['completed', 'declined', 'cancelled', 'void']))
            ->selectRaw($expression.' as archive_year')->distinct()->pluck('archive_year');
        $batchYears = GuidanceTestBatch::whereIn('test_type', array_values(GuidanceCategories::LABELS))
            ->where(fn ($query) => $query->whereNotNull('archived_at')->orWhere('status', 'Completed'))
            ->selectRaw($expression.' as archive_year')->distinct()->pluck('archive_year');

        return $requestYears->merge($batchYears)->filter()->map(fn ($year) => (int) $year)->unique()->sortDesc()->values()->all();
    }

    private function filterRequestType(Builder $query, ?string $requestType): void
    {
        if ($requestType === 'batch') {
            $query->whereNotNull('batch_id');
        } elseif ($requestType === 'individual') {
            $query->whereNull('batch_id');
        }
    }
}
