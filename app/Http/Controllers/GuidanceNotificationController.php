<?php

namespace App\Http\Controllers;

use App\Models\GuidanceRequestNotification;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuidanceNotificationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['after' => 'nullable|integer|min:0']);
        $userId = $request->user()->getKey();
        $base = GuidanceRequestNotification::query();
        $latest = (int) (clone $base)->max('id');
        $unread = $this->unread($userId);
        $recent = (clone $base)->with('request')->latest('id')->limit(12)->get();
        $new = isset($data['after']) ? (clone $base)->with('request')->where('id', '>', $data['after'])->orderBy('id')->limit(50)->get() : collect();
        $readIds = DB::table('guidance_notification_reads')->where('user_id', $userId)->whereIn('notification_id', $recent->pluck('id'))->pluck('notification_id')->all();
        return response()->json([
            'cursor' => $latest, 'unread' => $unread,
            'recent' => $recent->map(fn ($item) => $this->item($item, in_array($item->id, $readIds, true))),
            'new' => $new->map(fn ($item) => $this->item($item, false)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, GuidanceRequestNotification $notification)
    {
        DB::table('guidance_notification_reads')->insertOrIgnore([
            'notification_id' => $notification->getKey(), 'user_id' => $request->user()->getKey(), 'read_at' => now(),
        ]);
        return response()->json(['ok' => true, 'unread' => $this->unread($request->user()->getKey())]);
    }

    public function clearModule(Request $request)
    {
        $data = $request->validate(['module' => ['required', Rule::in(['psychological', 'personality', 'career', 'good-moral', 'exit-form'])]]);
        $userId = $request->user()->getKey();
        GuidanceRequestNotification::where('module', $data['module'])->whereNotExists(fn ($query) => $query->selectRaw('1')
            ->from('guidance_notification_reads as reads')->whereColumn('reads.notification_id', 'guidance_request_notifications.id')->where('reads.user_id', $userId))
            ->select('id')->chunkById(200, function ($notifications) use ($userId) {
                DB::table('guidance_notification_reads')->insertOrIgnore($notifications->map(fn ($item) => [
                    'notification_id' => $item->id, 'user_id' => $userId, 'read_at' => now(),
                ])->all());
            });
        return response()->json(['ok' => true, 'module' => $data['module'], 'unread' => $this->unread($userId)]);
    }

    public function details(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate(['module_type' => ['required', Rule::in(['psychological', 'personality', 'career', 'good-moral', 'exit-form'])]]);
        $moduleType = $data['module_type'];
        abort_unless($serviceRequest->batch_id === null && ($serviceRequest->service === 'testing'
            ? in_array($moduleType, $serviceRequest->tests ?: [], true)
            : $moduleType === $serviceRequest->service), 404);
        $entry = $serviceRequest;
        $appointment = $entry->service === 'testing'
            ? $entry->guidanceAppointments()->where('test_category', $moduleType)->first()
            : null;
        return response()->view('staff.partials.notification-review', compact('entry', 'moduleType', 'appointment'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function review(GuidanceRequestNotification $notification)
    {
        $entry = $notification->request;
        $appointment = in_array($notification->module, ['psychological', 'personality', 'career'], true)
            ? $entry->guidanceAppointments()->where('test_category', $notification->module)->first()
            : null;
        $moduleType = $notification->module;
        return response()->view('staff.partials.notification-review', compact('entry', 'moduleType', 'appointment'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function item(GuidanceRequestNotification $notification, bool $read): array
    {
        $entry = $notification->request;
        $module = $notification->module;
        $route = match($module) {
            'psychological', 'personality', 'career' => auth()->user()->role.'.'.$module.'.index',
            default => auth()->user()->role.'.'.$module,
        };
        return [
            'id' => $notification->id, 'request_id' => $entry->getKey(),
            'student' => trim($entry->first_name.' '.$entry->last_name),
            'student_name' => trim($entry->first_name.' '.$entry->last_name),
            'module' => match($module) { 'psychological' => 'Psychological Assessment', 'personality' => 'Personality Test', 'career' => 'Career Test', 'good-moral' => 'Good Moral', default => 'Exit Form' },
            'module_key' => $module, 'module_type' => $module, 'reference' => $entry->reference,
            'url' => route($route, ['review_notification' => $notification->getKey()]),
            'review_url' => route(auth()->user()->role.'.requests.details', $entry).'?module_type='.rawurlencode($module),
            'read_url' => route('api.notifications.read', $notification),
            'read' => $read, 'created_at' => $notification->created_at->toIso8601String(),
        ];
    }

    private function unread(int $userId): int
    {
        return GuidanceRequestNotification::whereNotExists(fn ($query) => $query->selectRaw('1')->from('guidance_notification_reads as reads')
            ->whereColumn('reads.notification_id', 'guidance_request_notifications.id')->where('reads.user_id', $userId))->count();
    }
}
