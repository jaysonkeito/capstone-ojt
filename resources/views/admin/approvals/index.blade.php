@extends('layouts.app')

@section('title', 'Account Approvals')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Account Approvals</h1>
        <p class="text-sm text-gray-500 mt-0.5">Coordinator and Supervisor sign-ups waiting for your go-ahead.</p>
    </div>
</div>

<div class="mb-6 rounded-lg bg-white border border-gray-200 px-4 py-3 text-xs text-gray-500 leading-relaxed">
    These are staff accounts registered through the public sign-up page. They can't sign in until you approve them —
    approving activates the account and moves it into the <a href="{{ route('admin.staff.index') }}" class="font-medium text-brand-600 hover:underline">Staff</a>
    section, where you can assign its office and details. Rejecting removes the request so the person can re-apply.
    Interns register instantly and never appear here.
</div>

<div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                <th class="px-5 py-3">Applicant</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Requested</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($pending as $applicant)
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            @include('partials.avatar', ['user' => $applicant, 'class' => 'w-8 h-8 text-[10px]'])
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $applicant->full_name }}</p>
                                <p class="text-xs text-gray-400">{{ $applicant->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $applicant->isCoordinator() ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $applicant->role_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500" title="{{ $applicant->created_at?->format('M j, Y g:i A') }}">
                        {{ $applicant->created_at?->diffForHumans() }}
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        @if(auth()->user()->mayApprove($applicant))
                        <form method="POST" action="{{ route('admin.approvals.approve', $applicant) }}" class="inline">
                            @csrf
                            <button class="text-xs font-medium text-emerald-600 hover:underline">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.approvals.reject', $applicant) }}" class="inline"
                              data-confirm-title="Reject sign-up"
                              data-confirm-message="Reject {{ $applicant->full_name }}'s {{ strtolower($applicant->role_label) }} sign-up? This removes the request permanently and frees the email for a new application."
                              data-confirm-action="Reject"
                              onsubmit="return askConfirm(this);">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-medium text-red-500 hover:underline ml-3">Reject</button>
                        </form>
                        @else
                        <span class="text-[11px] text-gray-400">Awaiting review</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-5 py-12 text-center">
                        <p class="text-sm font-medium text-gray-500">No pending sign-ups</p>
                        <p class="text-xs text-gray-400 mt-1">Everything is up to date — new coordinator and supervisor applications will land here.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $pending->links() }}
</div>

@include('partials.confirm-modal')
@endsection
