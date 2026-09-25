<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Support\CourseCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuidanceSettingsController extends Controller
{
    public function index()
    {
        // Seed standard courses if table is empty
        if (Course::count() === 0) {
            foreach (CourseCatalog::OPTIONS as $code => $name) {
                Course::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
            }
        }

        return view('staff.guidance-settings', [
            'courses' => Course::orderBy('code')->get(),
            'users' => User::whereHas('roleLookup', fn ($query) => $query->where('slug', 'staff'))->orderBy('name')->get(),
        ]);
    }

    public function course(Request $request, ?Course $course = null)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('courses', 'code')->ignore($course?->id)],
            'name' => 'required|string|max:255',
            'is_active' => 'nullable',
        ]);

        $code = strtoupper(trim($data['code']));
        $isActive = $request->boolean('is_active', true);

        // Official campus programs cannot be disabled
        if ($course && array_key_exists($course->code, CourseCatalog::OPTIONS) && ! $isActive) {
            abort(422, 'Official campus programs cannot be disabled.');
        }

        // Only official campus programs can be added
        if (! $course && ! array_key_exists($code, CourseCatalog::OPTIONS)) {
            return back()->with('error', 'Only official campus programs may be configured.');
        }

        if ($course) {
            $course->update([
                'code' => $code,
                'name' => trim($data['name']),
                'is_active' => $isActive,
            ]);
            $msg = "Program '{$course->code}' updated successfully.";
        } else {
            Course::create([
                'code' => $code,
                'name' => trim($data['name']),
                'is_active' => $isActive,
            ]);
            $msg = "New program '{$code}' added successfully.";
        }

        return back()->with('success', $msg);
    }

    public function toggleCourse(Request $request, Course $course)
    {
        $course->update(['is_active' => !$course->is_active]);

        $statusStr = $course->is_active ? 'activated' : 'deactivated';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $course->is_active,
                'message' => "Program {$course->code} has been {$statusStr}.",
            ]);
        }

        return back()->with('success', "Program {$course->code} has been {$statusStr}.");
    }

    public function destroyCourse(Course $course)
    {
        return back()->with('error', 'Hard deletion is disabled to preserve historical records in Archives and Analytics. Please use the Active / Inactive toggle switch instead.');
    }

    public function user(Request $request, ?User $user = null)
    {
        if ($user) abort_unless($user->role === 'staff', 404);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'is_active' => 'required|boolean',
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
        if (empty($data['password'])) unset($data['password']);
        if ($user) $user->update($data); else User::create($data + ['role' => 'staff']);
        return back()->with('success', 'Staff account updated.');
    }
}
