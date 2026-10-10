<?php

namespace App\Http\Controllers;

use App\Models\ClassMessage;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The class board — the shared channel where a coordinator and their
 * assigned interns talk. Every member sees every message; any member can
 * post. Membership is derived, never configured: the coordinator plus the
 * interns whose coordinator_id points at them.
 */
class ClassBoardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $coordinatorId = $user->isCoordinator() ? $user->id : $user->coordinator_id;

        abort_if($coordinatorId === null, 404, 'You have no class yet — you need a coordinator first.');

        $coordinator = User::findOrFail($coordinatorId);

        $messages = ClassMessage::where('coordinator_id', $coordinatorId)
            ->with('author:id,first_name,last_name,role,avatar_path')
            ->orderBy('created_at')
            ->take(200)
            ->get();

        return view('class-board', [
            'coordinator' => $coordinator,
            'messages' => $messages,
            'classmates' => $user->isCoordinator()
                ? User::where('role', 'intern')->where('coordinator_id', $user->id)->where('is_active', true)->orderBy('last_name')->get(['id', 'first_name', 'last_name'])
                : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        // Interns post to their own coordinator's board; a coordinator
        // posts to their own.
        $coordinatorId = $user->isCoordinator() ? $user->id : $user->coordinator_id;

        abort_if($coordinatorId === null, 404, 'You have no class yet — you need a coordinator first.');

        ClassMessage::create([
            'coordinator_id' => $coordinatorId,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        return redirect()->route('class-board.index');
    }
}
