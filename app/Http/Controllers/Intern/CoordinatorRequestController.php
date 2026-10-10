<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\InternRequest;
use App\Models\Office;
use App\Models\User;
use App\Notifications\InternRequestSubmitted;
use Illuminate\Http\Request;

/**
 * The intern's requests to their coordinator beyond attendance: applying
 * to another office (a transfer, decided by the coordinator with the
 * placement moving on approval) and consultation requests — online or
 * face-to-face, aimed at their coordinator or, for consultations, at a
 * supervisor of their office.
 */
class CoordinatorRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = InternRequest::where('intern_id', $request->user()->id)
            ->with(['office:id,name', 'recipient:id,first_name,last_name,role', 'decider:id,first_name,last_name'])
            ->latest()
            ->take(30)
            ->get();

        $offices = Office::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']);
        $supervisors = User::query()
            ->where('role', 'supervisor')
            ->where('office_id', $request->user()->office_id)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('intern.coordinator-requests', [
            'requests' => $requests,
            'offices' => $offices,
            'supervisors' => $supervisors,
        ]);
    }

    public function store(Request $request)
    {
        $intern = $request->user();

        $validated = $request->validate([
            'type' => ['required', 'in:office_transfer,consultation'],
            'office_id' => [
                'required_if:type,office_transfer', 'nullable', 'exists:offices,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($intern) {
                    if ($value && (int) $value === (int) $intern->office_id) {
                        $fail('That is already your current office.');
                    }
                },
            ],
            'mode' => ['required_if:type,consultation', 'nullable', 'in:online,f2f'],
            'recipient_id' => ['nullable', 'exists:users,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // A consultation may name a supervisor of the intern's office; an
        // office transfer always goes to the coordinator. No named recipient
        // means the intern's coordinator.
        $recipientId = null;
        if ($validated['type'] === 'consultation' && ! empty($validated['recipient_id'])) {
            $recipient = User::find($validated['recipient_id']);
            abort_if($recipient && $recipient->office_id !== $intern->office_id, 422, 'Consultations go to a supervisor at your own office.');
            $recipientId = $recipient?->id;
        }

        $internRequest = InternRequest::create([
            'intern_id' => $intern->id,
            'type' => $validated['type'],
            'office_id' => $validated['type'] === 'office_transfer' ? $validated['office_id'] : null,
            'mode' => $validated['type'] === 'consultation' ? $validated['mode'] : null,
            'recipient_id' => $recipientId,
            'message' => $validated['message'],
            'status' => 'pending',
        ]);

        $coordinator = $intern->coordinator;
        if ($coordinator) {
            $coordinator->notify(new InternRequestSubmitted($internRequest, $intern->full_name));
        }
        if ($recipientId) {
            $recipient->notify(new InternRequestSubmitted($internRequest, $intern->full_name));
        }

        return redirect()->route('intern.coordinator-requests.index')
            ->with('status', 'Your '.$internRequest->typeLabel().' request was sent — you\'ll be notified when it is decided.');
    }

    /**
     * Withdraw a pending request — the intern's own filing, and only
     * before a decision lands on it.
     */
    public function destroy(Request $request, InternRequest $internRequest)
    {
        abort_unless($internRequest->intern_id === $request->user()->id, 404);
        abort_unless($internRequest->status === 'pending', 422, 'A decided request can no longer be withdrawn.');

        $internRequest->delete();

        return redirect()->route('intern.coordinator-requests.index')
            ->with('status', 'Your request was withdrawn.');
    }
}
