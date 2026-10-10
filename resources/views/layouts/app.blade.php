<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', 'OJT Management System') — NORSU CAS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .nav-link { display: flex; align-items: center; gap: 0.65rem; }
        .nav-link svg { flex-shrink: 0; }
        input, select, textarea { color-scheme: light; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-900 antialiased min-h-screen">

@include('partials.app-install-banner')


@auth
    @php
        $u = auth()->user();
        $icon = function (string $name) {
            $paths = [
                'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
                'users' => '<circle cx="9" cy="8" r="3.25"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16.5 5.8a3 3 0 0 1 0 5.9"/><path d="M18 14.2a5.4 5.4 0 0 1 3.5 5.8"/>',
                'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/>',
                'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .34 1.87l.06.06a2.06 2.06 0 1 1-2.9 2.9l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V20a2.06 2.06 0 1 1-4.12 0v-.09a1.7 1.7 0 0 0-1.1-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2.06 2.06 0 1 1-2.9-2.9l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.55-1H4a2.06 2.06 0 1 1 0-4.12h.09a1.7 1.7 0 0 0 1.55-1.1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2.06 2.06 0 1 1 2.9-2.9l.06.06a1.7 1.7 0 0 0 1.87.34H10a1.7 1.7 0 0 0 1-1.55V4a2.06 2.06 0 1 1 4.12 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2.06 2.06 0 1 1 2.9 2.9l-.06.06a1.7 1.7 0 0 0-.34 1.87V10a1.7 1.7 0 0 0 1.55 1H20a2.06 2.06 0 1 1 0 4.12h-.09a1.7 1.7 0 0 0-1.51 1z"/>',
                'dashboard' => '<rect x="3" y="3" width="7.5" height="9" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="5.5" rx="1.5"/><rect x="13.5" y="11.5" width="7.5" height="9" rx="1.5"/><rect x="3" y="15" width="7.5" height="5.5" rx="1.5"/>',
                'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
                'check' => '<path d="M20 6 9 17l-5-5"/>',
                'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
                'building' => '<rect x="4" y="2" width="16" height="20" rx="1.5"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
                'team' => '<rect x="2.5" y="4" width="19" height="14" rx="2.5"/><circle cx="8.5" cy="10" r="2"/><path d="M5 15c.6-1.6 1.9-2.5 3.5-2.5S11.4 13.4 12 15"/><path d="M15 8.5h4M15 12h4M15 15.5h2.5"/>',
                'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>',
                'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a1.5 1.5 0 0 0-2.12 0L5 20"/>',
                'file' => '<path d="M14 3v5h5"/><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M9 13h6M9 17h6"/>',
                'id-card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5 16c.5-1.3 1.6-2 3-2s2.5.7 3 2"/><path d="M14 9h4M14 12h4M14 15h2.5"/>',
                'qr' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 21v.01M21 17v.01M17 21h.01M14 21v.01"/>',
                'scan' => '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M6 12h12"/>',
                'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            ];
            return $paths[$name] ?? '';
        };

        // Pending self-service staff sign-ups awaiting the admin's approval.
        $pendingApprovals = 0;
        if ($u->isAdmin()) {
            $pendingApprovals = App\Models\User::pendingApproval()->count();
        } elseif ($u->isDean()) {
            $pendingApprovals = App\Models\User::pendingApproval()->where('college_code', $u->collegeCode())->count();
        } elseif ($u->isCoordinator()) {
            $pendingApprovals = App\Models\User::pendingApproval()->where('role', 'supervisor')->where('college_code', $u->collegeCode())->count();
        }

        // Pending request-queue counts, per role: what the admin must decide,
        // what a monitor must decide, what an intern has in flight.
        $pendingAdminRequests = $u->isAdmin()
            ? App\Models\PlacementRequest::pending()->count() + App\Models\CompletionRecommendation::pending()->count()
            : 0;
        $pendingMonitorRequests = $u->isMonitor()
            ? App\Models\LogRequest::query()->where('status', 'pending')->whereHas('intern', fn ($q) => $u->isCoordinator()
                ? $q->where('coordinator_id', $u->id)
                : $q->where('office_id', $u->office_id))->count()
                + ($u->isCoordinator() || $u->isAdmin()
                    ? App\Models\InternRequest::query()->where('status', 'pending')
                        ->where(fn ($q) => $q->where('recipient_id', $u->id)
                            ->orWhereHas('intern', fn ($iq) => $iq->where('coordinator_id', $u->id)))
                        ->count()
                    : 0)
            : 0;
        $pendingInternRequests = $u->isIntern()
            ? App\Models\LogRequest::where('intern_id', $u->id)->where('status', 'pending')->count()
                + App\Models\InternRequest::where('intern_id', $u->id)->where('status', 'pending')->count()
            : 0;

        // Every link carries a 'group' — the sidebar renders a section header
        // whenever the group changes, so related items read as one block.
        $navLinks = match (true) {
            $u->isAdmin() => [
                ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'group' => ''],
                ['route' => 'admin.interns.index', 'match' => 'admin.interns.*', 'icon' => 'users', 'label' => 'Interns', 'group' => 'People'],
                ['route' => 'admin.staff.index', 'match' => 'admin.staff.*', 'icon' => 'team', 'label' => 'Staff', 'group' => 'People'],
                ['route' => 'admin.offices.index', 'match' => 'admin.offices.*', 'icon' => 'building', 'label' => 'Offices', 'group' => 'People'],
                ['route' => 'admin.logs.index', 'match' => 'admin.logs.*', 'icon' => 'clock', 'label' => 'Logbook', 'group' => 'Attendance'],
                ['route' => 'admin.kiosk.index', 'match' => 'admin.kiosk.*', 'icon' => 'scan', 'label' => 'Scanner', 'group' => 'Attendance'],
                ['route' => 'admin.kiosk-captures.index', 'match' => 'admin.kiosk-captures.*', 'icon' => 'image', 'label' => 'Scan Captures', 'group' => 'Attendance'],
                ['route' => 'admin.approvals.index', 'match' => 'admin.approvals.*', 'icon' => 'check', 'label' => 'Approvals', 'group' => 'Workflow', 'badge' => $pendingApprovals ?: null],
                ['route' => 'admin.requests.index', 'match' => 'admin.requests.*', 'icon' => 'file', 'label' => 'Requests', 'group' => 'Workflow', 'badge' => $pendingAdminRequests ?: null],
                ['route' => 'admin.audit-log.index', 'match' => 'admin.audit-log.*', 'icon' => 'file', 'label' => 'Activity Log', 'group' => 'System'],
                ['route' => 'admin.document-templates.index', 'match' => 'admin.document-templates.*', 'icon' => 'file', 'label' => 'Templates', 'group' => 'System'],
                ['route' => 'admin.settings.edit', 'match' => 'admin.settings.*', 'icon' => 'settings', 'label' => 'Settings', 'group' => 'System'],
            ],
            // Supervisors run their office's program: the scoped dashboard,
            // their interns, the logbook and the office scanner. Templates stay
            // with the Coordinator and the System Admin.
            $u->isSupervisor() => [
                ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'group' => ''],
                ['route' => 'admin.interns.index', 'match' => 'admin.interns.*', 'icon' => 'users', 'label' => 'Interns', 'group' => 'People'],
                ['route' => 'admin.logs.index', 'match' => 'admin.logs.*', 'icon' => 'clock', 'label' => 'Logbook', 'group' => 'Attendance'],
                ['route' => 'admin.kiosk.index', 'match' => 'admin.kiosk.*', 'icon' => 'scan', 'label' => 'Scanner', 'group' => 'Attendance'],
                ['route' => 'admin.kiosk-captures.index', 'match' => 'admin.kiosk-captures.*', 'icon' => 'image', 'label' => 'Scan Captures', 'group' => 'Attendance'],
                ['route' => 'monitor.requests.index', 'match' => 'monitor.requests.*', 'icon' => 'file', 'label' => 'Requests', 'group' => 'Workflow', 'badge' => $pendingMonitorRequests ?: null],
                ['route' => 'admin.settings.edit', 'match' => 'admin.settings.*', 'icon' => 'settings', 'label' => 'Settings', 'group' => 'System'],
            ],
            // Coordinators run the school-side program: their interns, the
            // logbook, staff accounts, offices, plus templates and settings.
            $u->isCoordinator() => [
                ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'group' => ''],
                ['route' => 'admin.interns.index', 'match' => 'admin.interns.*', 'icon' => 'users', 'label' => 'Interns', 'group' => 'People'],
                ['route' => 'admin.staff.index', 'match' => 'admin.staff.*', 'icon' => 'team', 'label' => 'Staff', 'group' => 'People'],
                ['route' => 'admin.offices.index', 'match' => 'admin.offices.*', 'icon' => 'building', 'label' => 'Offices', 'group' => 'People'],
                ['route' => 'admin.logs.index', 'match' => 'admin.logs.*', 'icon' => 'clock', 'label' => 'Logbook', 'group' => 'Attendance'],
                ['route' => 'admin.kiosk-captures.index', 'match' => 'admin.kiosk-captures.*', 'icon' => 'image', 'label' => 'Scan Captures', 'group' => 'Attendance'],
                ['route' => 'monitor.requests.index', 'match' => 'monitor.requests.*', 'icon' => 'file', 'label' => 'Requests', 'group' => 'Workflow', 'badge' => $pendingMonitorRequests ?: null],
                ['route' => 'class-board.index', 'match' => 'class-board.*', 'icon' => 'team', 'label' => 'Class', 'group' => 'Workflow'],
                ['route' => 'admin.document-templates.index', 'match' => 'admin.document-templates.*', 'icon' => 'file', 'label' => 'Templates', 'group' => 'System'],
                ['route' => 'admin.settings.edit', 'match' => 'admin.settings.*', 'icon' => 'settings', 'label' => 'Settings', 'group' => 'System'],
            ],
            // Deans: approvals desk, plus the coordinator's monitoring tools
            // when they also coordinate a program's interns.
            $u->isDean() => [
                ['route' => 'monitor.dashboard', 'match' => 'monitor.dashboard', 'icon' => 'dashboard', 'label' => 'My Interns', 'group' => 'People'],
                ['route' => 'admin.logs.index', 'match' => 'admin.logs.*', 'icon' => 'clock', 'label' => 'Logbook', 'group' => 'Attendance'],
                ['route' => 'admin.kiosk-captures.index', 'match' => 'admin.kiosk-captures.*', 'icon' => 'image', 'label' => 'Scan Captures', 'group' => 'Attendance'],
                ['route' => 'admin.approvals.index', 'match' => 'admin.approvals.*', 'icon' => 'check', 'label' => 'Approvals', 'group' => 'Workflow', 'badge' => $pendingApprovals ?: null],
                ['route' => 'monitor.requests.index', 'match' => 'monitor.requests.*', 'icon' => 'file', 'label' => 'Requests', 'group' => 'Workflow'],
            ],
            // Program Chairs: read-only oversight of their college's interns
            // (reached through their coordinators) and the request desk.
            $u->isChair() => [
                ['route' => 'monitor.dashboard', 'match' => 'monitor.dashboard', 'icon' => 'dashboard', 'label' => 'My Interns', 'group' => 'People'],
                ['route' => 'admin.logs.index', 'match' => 'admin.logs.*', 'icon' => 'clock', 'label' => 'Logbook', 'group' => 'Attendance'],
                ['route' => 'admin.kiosk-captures.index', 'match' => 'admin.kiosk-captures.*', 'icon' => 'image', 'label' => 'Scan Captures', 'group' => 'Attendance'],
                ['route' => 'monitor.requests.index', 'match' => 'monitor.requests.*', 'icon' => 'file', 'label' => 'Requests', 'group' => 'Workflow'],
            ],
            // Office scanner accounts: the station is their entire surface —
            // they exist so the kiosk PC's session exposes nothing else.
            $u->isOffice() => [
                ['route' => 'admin.kiosk.index', 'match' => 'admin.kiosk.*', 'icon' => 'scan', 'label' => 'Scanner', 'group' => ''],
            ],
            default => [
                ['route' => 'intern.dashboard', 'match' => 'intern.dashboard', 'icon' => 'home', 'label' => 'My Dashboard', 'group' => 'My OJT'],
                ['route' => 'intern.my-qr', 'match' => 'intern.my-qr', 'icon' => 'qr', 'label' => 'My QR Code', 'group' => 'My OJT'],
                ['route' => 'intern.time-frame', 'match' => 'intern.time-frame', 'icon' => 'clock', 'label' => 'My Time Frame', 'group' => 'My OJT'],
                ['route' => 'intern.documentation', 'match' => 'intern.documentation', 'icon' => 'image', 'label' => 'My Journal', 'group' => 'My OJT'],
                ['route' => 'intern.requests.index', 'match' => 'intern.requests.*', 'icon' => 'file', 'label' => 'My Requests', 'group' => 'Requests', 'badge' => $pendingInternRequests ?: null],
                ['route' => 'intern.coordinator-requests.index', 'match' => 'intern.coordinator-requests.*', 'icon' => 'file', 'label' => 'Coordinator Requests', 'group' => 'Requests'],
                ['route' => 'class-board.index', 'match' => 'class-board.*', 'icon' => 'team', 'label' => 'My Class', 'group' => 'Class'],
                ['route' => 'intern.personal-information.edit', 'match' => 'intern.personal-information.*', 'icon' => 'id-card', 'label' => 'Personal Info', 'group' => 'My Records'],
                ['route' => 'intern.requirements.index', 'match' => 'intern.requirements.*', 'icon' => 'file', 'label' => 'Requirements', 'group' => 'My Records'],
            ],
        };

        // Everyone can manage their own profile (photo, info, password).
        $navLinks[] = ['route' => 'profile', 'match' => 'profile', 'icon' => 'user', 'label' => 'Profile', 'group' => 'Account'];

        // The notification inbox — every role, with the unread count as a badge.
        $navLinks[] = ['route' => 'notifications.index', 'match' => 'notifications.*', 'icon' => 'bell', 'label' => 'Notifications', 'group' => 'Account', 'badge' => $unreadNotificationCount ?: null];
    @endphp

    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="hidden md:flex w-64 shrink-0 bg-white border-r border-gray-200 flex-col sticky top-0 h-screen">
            <div class="px-5 py-5 flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-gray-900 flex items-center justify-center text-white text-sm font-bold">N</div>
                <div class="leading-tight">
                    <p class="font-semibold text-sm tracking-tight">NORSU OJT</p>
                    <p class="text-[10px] text-gray-400 uppercase tracking-widest">CAS</p>
                </div>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
                @php $navGroup = null; @endphp
                @foreach($navLinks as $link)
                    @if(($link['group'] ?? '') !== $navGroup)
                        @php $navGroup = $link['group'] ?? ''; @endphp
                        @if($navGroup !== '')
                            <p class="px-3 pt-4 pb-2 text-[10px] font-medium uppercase tracking-widest text-gray-400">{{ $navGroup }}</p>
                        @endif
                    @endif
                    @php $active = request()->routeIs($link['match']); @endphp
                    <a href="{{ route($link['route']) }}"
                       class="nav-link px-3 py-2 rounded-lg text-sm transition
                              {{ $active ? 'bg-brand-50 text-brand-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icon($link['icon']) !!}</svg>
                        <span>{{ $link['label'] }}</span>
                        @if(! empty($link['badge']))
                            <span class="ml-auto text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ $active ? 'bg-brand-600 text-white' : 'bg-amber-100 text-amber-700' }}">{{ $link['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="px-3 py-4 border-t border-gray-100">
                <a href="{{ route('profile') }}" class="flex items-center gap-2.5 px-2 mb-2 group">
                    @include('partials.avatar', ['user' => $u, 'class' => 'w-9 h-9'])
                    <div class="leading-tight min-w-0">
                        <p class="text-sm text-gray-900 truncate group-hover:text-brand-700 transition">{{ $u->display_name }}</p>
                        <p class="text-[11px] text-gray-400 truncate">{{ $u->role_label }}</p>
                    </div>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 hover:text-red-700 transition">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icon('logout') !!}</svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 min-w-0">
            {{-- Mobile top bar (sidebar collapses below md). Sticky inside the
                 content column — NOT fixed — so the install banner above the
                 flex wrapper stays visible in flow and content never needs a
                 hardcoded top margin to dodge the bar. --}}
            <div class="md:hidden no-print sticky top-0 z-40 bg-white border-b border-gray-200 flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3">
                    <button type="button" onclick="document.getElementById('mobileSidebar').classList.remove('hidden')" class="text-gray-500 hover:text-gray-900" aria-label="Open menu">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <a href="{{ url('/') }}" class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-md bg-gray-900 flex items-center justify-center text-white text-[11px] font-bold">N</span>
                        <span class="font-semibold text-sm tracking-tight">NORSU OJT</span>
                    </a>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-xs font-semibold text-red-600 hover:text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg transition">Logout</button>
                </form>
            </div>

            {{-- Mobile slide-over sidebar --}}
            <div id="mobileSidebar" class="hidden md:hidden fixed inset-0 z-50" role="dialog">
                <div class="absolute inset-0 bg-gray-900/20" onclick="document.getElementById('mobileSidebar').classList.add('hidden')"></div>
                <div class="absolute inset-y-0 left-0 w-64 bg-white border-r border-gray-200 flex flex-col">
                    <div class="px-5 py-5 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-gray-900 flex items-center justify-center text-white text-sm font-bold">N</div>
                            <p class="font-semibold text-sm tracking-tight">NORSU OJT</p>
                        </div>
                        <button type="button" onclick="document.getElementById('mobileSidebar').classList.add('hidden')" class="text-gray-400 hover:text-gray-900">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                <nav class="flex-1 px-3 py-2 space-y-0.5 overflow-y-auto">
                    @php $navGroup = null; @endphp
                    @foreach($navLinks as $link)
                        @if(($link['group'] ?? '') !== $navGroup)
                            @php $navGroup = $link['group'] ?? ''; @endphp
                            @if($navGroup !== '')
                                <p class="px-3 pt-4 pb-2 text-[10px] font-medium uppercase tracking-widest text-gray-400">{{ $navGroup }}</p>
                            @endif
                        @endif
                        @php $active = request()->routeIs($link['match']); @endphp
                        <a href="{{ route($link['route']) }}"
                           class="nav-link px-3 py-2.5 rounded-lg text-sm {{ $active ? 'bg-brand-50 text-brand-700 font-medium' : 'text-gray-600 hover:bg-gray-100' }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icon($link['icon']) !!}</svg>
                            <span>{{ $link['label'] }}</span>
                            @if(! empty($link['badge']))
                                <span class="ml-auto text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ $active ? 'bg-brand-600 text-white' : 'bg-amber-100 text-amber-700' }}">{{ $link['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
                    <div class="px-5 py-4 border-t border-gray-100 text-xs text-gray-400">
                        {{ $u->display_name }} · {{ $u->role_label }}
                    </div>
                </div>
            </div>

            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 2xl:px-14 py-8 md:py-10">
                @if($u->password_changed_at === null && ! $u->isAdmin())
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-amber-50 border border-amber-100 px-4 py-3 text-amber-800 text-sm">
                        <span>You're still using the default password{{ $u->isIntern() ? ' (your last name)' : '' }} — anyone who knows it could log in as you.</span>
                        <a href="{{ route('profile') }}" class="font-medium underline underline-offset-2 whitespace-nowrap">Set your own password →</a>
                    </div>
                @endif

                @if($u->needsProfileCompletion())
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-brand-50 border border-brand-100 px-4 py-3 text-brand-800 text-sm">
                        <span>Almost there — complete your profile to unlock your dashboard.</span>
                        <a href="{{ route('profile-completion.edit') }}" class="font-medium underline underline-offset-2 whitespace-nowrap">Complete profile →</a>
                    </div>
                @endif

                {{-- Success + validation messages float in as toasts and
                     auto-dismiss after a few seconds. --}}
                @include('partials.toast')

                @yield('content')
            </main>
        </div>
    </div>
@else
    <main>
        @yield('content')
    </main>
@endauth

@stack('scripts')
</body>
</html>
