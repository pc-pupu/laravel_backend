<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Admin-only: run cache/view/optimize clear on the backend app.
 * Called by frontend admin panel so cache clear applies to both frontend and backend.
 */
class CacheClearController extends Controller
{
    /**
     * Run clear commands. POST with action: cache|view|optimize|config|route|all.
     */
    public function clear(Request $request)
    {
        $action = $request->input('action', 'all');
        $allowed = ['cache', 'view', 'optimize', 'config', 'route', 'all'];
        if (!in_array($action, $allowed, true)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid action.'], 422);
        }

        $results = [];
        $commands = [
            'cache'    => ['cache:clear', 'Application cache cleared'],
            'view'     => ['view:clear', 'Compiled views cleared'],
            'config'   => ['config:clear', 'Configuration cache cleared'],
            'route'    => ['route:clear', 'Route cache cleared'],
            'optimize' => ['optimize:clear', 'Optimize (bootstrap) cache cleared'],
        ];

        if ($action === 'all') {
            foreach ($commands as $key => [$cmd, $label]) {
                try {
                    Artisan::call($cmd);
                    $results[$key] = ['ok' => true, 'message' => $label];
                } catch (\Throwable $e) {
                    $results[$key] = ['ok' => false, 'message' => $e->getMessage()];
                }
            }
        } else {
            [$cmd, $label] = $commands[$action];
            try {
                Artisan::call($cmd);
                $results[$action] = ['ok' => true, 'message' => $label];
            } catch (\Throwable $e) {
                $results[$action] = ['ok' => false, 'message' => $e->getMessage()];
            }
        }

        $allOk = !in_array(false, array_column($results, 'ok'));
        return response()->json([
            'status'  => $allOk ? 'success' : 'error',
            'message' => $allOk ? 'Done.' : 'Some commands failed.',
            'results' => $results,
        ], $allOk ? 200 : 500);
    }
}
