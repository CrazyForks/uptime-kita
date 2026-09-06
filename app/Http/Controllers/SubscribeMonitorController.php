<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class SubscribeMonitorController extends Controller
{
    public function __invoke(Request $request, $monitorId)
    {
        try {
            $monitor = Monitor::withoutGlobalScopes()->findOrFail($monitorId);

            $errorMessage = null;
            $statusCode = 200;

            // Check if already subscribed
            $isSubscribed = $monitor->users()->where('user_id', auth()->id())->exists();

            // For private monitors, only existing owner/member can re-subscribe
            if (! $monitor->is_public && ! $isSubscribed) {
                $errorMessage = 'Cannot subscribe to private monitor';
                $statusCode = 403;
            }

            // Check for duplicate subscription (for public monitors or if already subscribed to private)
            if (! $errorMessage && $isSubscribed) {
                // If it's a private monitor and they're the owner, allow it (idempotent)
                if (! $monitor->is_public) {
                    // Automatically re-enable monitor if currently disabled
                    if (! $monitor->uptime_check_enabled) {
                        $monitor->update(['uptime_check_enabled' => true]);
                    }

                    $successMessage = 'Subscribed to monitor successfully';
                    if ($request->wantsJson()) {
                        return response()->json(['message' => $successMessage], 200);
                    }

                    return redirect()->back()->with('flash', [
                        'type' => 'success',
                        'message' => 'Berhasil berlangganan monitor: '.$monitor?->url,
                    ]);
                }
                $errorMessage = 'Already subscribed to this monitor';
                $statusCode = 400;
            }

            if ($errorMessage) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => $errorMessage], $statusCode);
                }

                return redirect()->back()->with('flash', [
                    'type' => 'error',
                    'message' => $errorMessage,
                ]);
            }

            $monitor->users()->attach(auth()->id(), ['is_active' => true]);

            // Auto-enable monitor if it was disabled
            if (! $monitor->uptime_check_enabled) {
                $monitor->update(['uptime_check_enabled' => true]);
            }

            // clear monitor cache
            cache()->forget('public_monitors_authenticated_'.auth()->id());
            cache()->forget('public_monitors_status_counts');

            $successMessage = 'Subscribed to monitor successfully';

            if ($request->wantsJson()) {
                return response()->json(['message' => $successMessage], 200);
            }

            return redirect()->back()->with('flash', [
                'type' => 'success',
                'message' => 'Berhasil berlangganan monitor: '.$monitor?->url,
            ]);
        } catch (ModelNotFoundException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Monitor not found'], 404);
            }

            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Monitor tidak ditemukan',
            ]);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to subscribe: '.$e->getMessage()], 500);
            }

            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Gagal berlangganan monitor: '.$e->getMessage(),
            ]);
        }
    }
}
