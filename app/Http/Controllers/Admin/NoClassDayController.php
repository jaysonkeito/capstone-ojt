<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NoClassDay;
use Illuminate\Http\Request;

class NoClassDayController extends Controller
{
    /**
     * Toggle a single date on/off the no-class calendar (AJAX or form post).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = NoClassDay::where('date', $validated['date'])->first();

        if ($existing) {
            $existing->delete();
            $status = 'removed';
        } else {
            NoClassDay::create([
                'date' => $validated['date'],
                'label' => $validated['label'] ?? 'No Class',
                'created_by' => $request->user()->id,
            ]);
            $status = 'added';
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => $status]);
        }

        return back()->with('status', $status === 'added' ? 'Marked as a no-class day.' : 'Removed from the no-class calendar.');
    }
}
