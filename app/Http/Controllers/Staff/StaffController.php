<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Nomination;
use App\Models\Partner;
use App\Models\Task;
use App\Services\TaskWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index()
    {
        return view('staff.index', [
            'pendingNominations' => Nomination::where('status', 'pending')->count(),
            'fraudAlerts' => DB::table('votes')->where('fraud_score', '>=', 50)->count(),
            'openTasks' => Task::whereNot('status', 'completed')->count(),
            'partnerWarnings' => Partner::where('status', 'warning')->count(),
            'nominations' => Nomination::with(['category', 'user'])->where('status', 'pending')->latest()->limit(6)->get(),
            'tasks' => Task::with('assignees')->orderBy('position')->get()->groupBy('status'),
        ]);
    }

    public function reviewNomination(Request $request, Nomination $nomination)
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);
        $nomination->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('success', 'Nominatie bijgewerkt.');
    }

    public function moveTask(Request $request, Task $task)
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,waiting,testing,completed,rejected'],
            'position' => ['required', 'integer', 'min:0'],
        ]);
        $task->update($data);

        return response()->json(['ok' => true]);
    }

    public function claimTask(Request $request, Task $task, TaskWorkflowService $workflow)
    {
        $workflow->claim($task, $request->user());

        return back()->with('success', 'De taak staat nu op jouw naam.');
    }

    public function completeTask(Request $request, Task $task, TaskWorkflowService $workflow)
    {
        $data = $request->validate(['completion_note' => ['nullable', 'string', 'max:2000']]);
        $workflow->complete($task, $request->user(), $data['completion_note'] ?? null);

        return back()->with('success', 'De taak is voltooid.');
    }
}
