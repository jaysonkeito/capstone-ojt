<?php

namespace App\Http\Controllers;

use App\Models\ClassMember;
use App\Models\ClassMessage;
use App\Models\User;
use App\Notifications\ChatReported;
use App\Notifications\ClassMessagePosted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The class board — the shared channel where a coordinator and their
 * assigned interns talk, in a Messenger-style layout. Every member sees
 * every message; any member can post; members can mute, report, and
 * leave (the coordinator can add them back). Membership is derived, never
 * configured: the coordinator plus the interns whose coordinator_id
 * points at them, minus anyone who left.
 */
class ClassBoardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $coordinatorId = $user->isCoordinator() ? $user->id : $user->coordinator_id;

        abort_if($coordinatorId === null, 404, 'You have no class yet — you need a coordinator first.');

        $coordinator = User::with('staffProfile')->findOrFail($coordinatorId);

        $messages = ClassMessage::where('coordinator_id', $coordinatorId)
            ->with('author:id,first_name,last_name,role,avatar_path')
            ->orderBy('created_at')
            ->take(200)
            ->get();

        // The member list: derived from coordinator assignments. Mute/leave
        // states come from the preference rows (created on demand).
        $members = User::where('role', 'intern')
            ->where('coordinator_id', $coordinatorId)
            ->where('is_active', true)
            ->with('staffProfile')
            ->orderBy('last_name')
            ->get();

        $memberPrefs = ClassMember::where('coordinator_id', $coordinatorId)
            ->whereIn('user_id', $members->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $prefs = ClassMember::for($coordinatorId, $user->id);

        // The conversation list — the class chat, plus per-user preview.
        $last = $messages->last();
        $chatName = ClassMember::chatNameFor($coordinatorId)
            ?? ($user->isCoordinator() ? 'Your class' : $coordinator->full_name."'s class");
        $conversations = [[
            'coordinator_id' => $coordinatorId,
            'name' => $chatName,
            'member_count' => $members->count() + 1,
            'preview' => $last ? \Illuminate\Support\Str::limit($last->body, 42) : 'No messages yet',
            'preview_at' => $last?->created_at?->format('g:i A'),
            'unread' => $messages->whereNull('read_at')->isNotEmpty() && $last && $last->user_id !== $user->id,
        ]];

        return view('class-board', [
            'coordinator' => $coordinator,
            'messages' => $messages,
            'members' => $members,
            'memberPrefs' => $memberPrefs,
            'prefs' => $prefs,
            'conversations' => $conversations,
            'chatName' => $chatName,
        ]);
    }

    /**
     * Post a message. Regular submits reload the board; the chat's own
     * composer submits with Accept: application/json — the optimistic
     * bubble on the phone/desktop replaces its pending state with the
     * confirmed copy, and the class (minus the author and anyone who
     * muted or left) gets the notification.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $coordinatorId = $user->isCoordinator() ? $user->id : $user->coordinator_id;

        abort_if($coordinatorId === null, 404, 'You have no class yet — you need a coordinator first.');

        // Someone who left can't post until the coordinator adds them back.
        $prefs = ClassMember::for($coordinatorId, $user->id);
        abort_if($prefs->left_at !== null, 403, 'You left this class — ask your coordinator to add you back.');

        $message = ClassMessage::create([
            'coordinator_id' => $coordinatorId,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        // Everyone else in the class, minus the muted and the departed.
        $recipients = User::where('role', 'intern')
            ->where('coordinator_id', $coordinatorId)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->get()
            ->filter(fn (User $intern) => ClassMember::for($coordinatorId, $intern->id)->left_at === null
                && ClassMember::for($coordinatorId, $intern->id)->muted_at === null);

        foreach ($recipients as $recipient) {
            $recipient->notify(new ClassMessagePosted($message, $user->full_name));
        }

        if ($request->wantsJson()) {
            return new JsonResponse([
                'ok' => true,
                'message' => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'mine' => true,
                    'at' => $message->created_at->format('M d, g:i A'),
                    'iso' => $message->created_at->toIso8601String(),
                ],
            ]);
        }

        return redirect()->route('class-board.index');
    }

    /**
     * Messages newer than the given id — the chat polls this every few
     * seconds so conversation partners appear without a reload.
     */
    public function feed(Request $request)
    {
        $user = $request->user();
        $coordinatorId = $user->isCoordinator() ? $user->id : $user->coordinator_id;

        abort_if($coordinatorId === null, 404);

        $after = (int) $request->input('after', 0);

        $messages = ClassMessage::where('coordinator_id', $coordinatorId)
            ->where('id', '>', $after)
            ->with('author:id,first_name,last_name,role,avatar_path')
            ->orderBy('created_at')
            ->take(50)
            ->get()
            ->map(fn (ClassMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'mine' => $m->user_id === $user->id,
                'author' => $m->author->full_name,
                'authorRole' => $m->author->role_label,
                'avatarUrl' => $m->author->avatar_url,
                'initials' => $m->author->initials,
                'at' => $m->created_at->format('M d, g:i A'),
                'iso' => $m->created_at->toIso8601String(),
            ]);

        return new JsonResponse(['messages' => $messages]);
    }

    /**
     * Mute or unmute the class — silenced members get no message
     * notifications (the board itself stays fully readable).
     */
    public function toggleMute(Request $request)
    {
        $user = $request->user();
        $coordinatorId = $user->isCoordinator() ? $user->id : $user->coordinator_id;

        abort_if($coordinatorId === null, 404);

        $prefs = ClassMember::for($coordinatorId, $user->id);
        $prefs->muted_at = $prefs->muted_at === null ? now() : null;
        $prefs->save();

        return back()->with('status', $prefs->muted_at !== null
            ? 'Class muted — you will not be notified of new messages.'
            : 'Class unmuted — new messages will notify you again.');
    }

    /**
     * Leave the class: hidden from the member list and the conversation,
     * no notifications. Only the coordinator can add a leaver back.
     */
    public function leave(Request $request)
    {
        $user = $request->user();
        $coordinatorId = $user->coordinator_id;

        abort_if($coordinatorId === null, 404, 'You have no class to leave.');
        abort_if($user->isCoordinator(), 403, 'The coordinator cannot leave their own class.');

        ClassMember::for($coordinatorId, $user->id)->update(['left_at' => now()]);

        return redirect()->route('class-board.index')
            ->with('status', 'You left the class — your coordinator can add you back.');
    }

    /**
     * Undo a leave — the coordinator adds the member back from the
     * member list.
     */
    public function addBack(Request $request, User $member)
    {
        $user = $request->user();
        abort_unless($user->isCoordinator(), 403);
        abort_unless($member->coordinator_id === $user->id, 404);

        ClassMember::for($user->id, $member->id)->update(['left_at' => null]);

        return back()->with('status', "{$member->full_name} was added back to the class.");
    }

    /**
     * The coordinator renames the class chat ("Customize chat").
     */
    public function rename(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isCoordinator(), 403, 'Only the coordinator can rename the class.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        ClassMember::for($user->id, $user->id)->update(['name' => $validated['name']]);

        return back()->with('status', "The class chat is now called \"{$validated['name']}\".");
    }

    /**
     * Report the chat — a note goes straight to the System Admin's inbox.
     */
    public function report(Request $request)
    {
        $user = $request->user();
        $coordinatorId = $user->isCoordinator() ? $user->id : $user->coordinator_id;

        abort_if($coordinatorId === null, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        $chatName = ClassMember::chatNameFor($coordinatorId) ?? 'the class chat';

        foreach ($admins as $admin) {
            $admin->notify(new ChatReported($chatName, $user->full_name, $validated['reason']));
        }

        return back()->with('status', 'The chat was reported to the System Admin. Thank you.');
    }
}
