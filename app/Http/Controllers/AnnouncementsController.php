<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Http\Request;

/**
 * Staff announcements to interns. Who an announcement reaches is decided
 * by the author's role — the System Admin reaches every intern, deans and
 * Program Chairs their college, supervisors their office, coordinators
 * their class — and every targeted intern gets it on their dashboard
 * banner and in their notification inbox.
 */
class AnnouncementsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('author_id', $user->id))
            ->with('author:id,first_name,last_name,role')
            ->latest()
            ->take(30)
            ->get();

        return view('admin.announcements', [
            'announcements' => $announcements,
            'audienceLabel' => self::audienceFor($user)['label'],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $scope = self::audienceFor($user);

        abort_if($scope === null, 403, 'Your account has no announcement audience — set your office or college first.');

        $announcement = Announcement::create([
            'author_id' => $user->id,
            'audience' => $scope['audience'],
            'college_code' => $scope['college_code'] ?? null,
            'office_id' => $scope['office_id'] ?? null,
            'coordinator_id' => $scope['coordinator_id'] ?? null,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        $interns = $announcement->interns()->get();

        if ($interns->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send(
                $interns,
                new AnnouncementPublished($announcement, $user->full_name),
            );
        }

        return redirect()->route('admin.announcements.index')
            ->with('status', "Announcement published to {$interns->count()} intern".($interns->count() === 1 ? '' : 's').'.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        abort_unless($request->user()->isAdmin() || $announcement->author_id === $request->user()->id, 403);

        $announcement->delete();

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement deleted.');
    }

    /**
     * The audience this staff member's announcements reach, snapshotted
     * from their role and account links. Null when they can't announce
     * (no office / college on file).
     *
     * @return array{audience: string, label: string, college_code?: string, office_id?: int, coordinator_id?: int}|null
     */
    private static function audienceFor(User $user): ?array
    {
        if ($user->isAdmin()) {
            return ['audience' => 'all', 'label' => 'every intern'];
        }

        if ($user->isDean() || $user->isChair()) {
            $college = $user->collegeCode();

            return $college === null ? null : [
                'audience' => 'college', 'label' => 'their college ('.strtoupper($college).')', 'college_code' => $college,
            ];
        }

        if ($user->isSupervisor()) {
            return $user->office_id === null ? null : [
                'audience' => 'office', 'label' => $user->office?->name ?? 'their office', 'office_id' => $user->office_id,
            ];
        }

        if ($user->isCoordinator()) {
            return [
                'audience' => 'class', 'label' => 'the interns assigned to them', 'coordinator_id' => $user->id,
            ];
        }

        return null;
    }
}
