@extends('layouts.app')

@section('title', auth()->user()->isCoordinator() ? 'Class' : 'My Class')

@section('content')
@php
    // Members split by their preference rows: who left stays visible to
    // the coordinator (to add them back), everyone else is active.
    $leftMembers = $members->filter(fn ($m) => $memberPrefs->get($m->id)?->left_at !== null);
    $activeMembers = $members->reject(fn ($m) => $leftMembers->contains($m));
    $isCoordinator = auth()->user()->isCoordinator();
    $muted = $prefs->muted_at !== null;
    $iLeft = $prefs->left_at !== null && ! $isCoordinator;
@endphp

<div id="chatShell" class="flex h-[calc(100dvh-7.5rem)] md:h-[calc(100dvh-6rem)] min-h-[30rem] border border-gray-200 rounded-2xl overflow-hidden bg-white">

    {{-- ============ Left: chats list (desktop; mobile shows it first) ============ --}}
    <aside id="chatListPane" class="flex flex-col w-full md:w-72 lg:w-80 shrink-0 border-r border-gray-100 min-w-0 overflow-hidden">
        <div class="px-4 pt-4 pb-2">
            <div class="flex items-center justify-between mb-3">
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Chats</h1>
                <span class="w-8 h-8 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center" title="New chat — coming soon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                </span>
            </div>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" id="chatSearch" placeholder="Search chats" autocomplete="off"
                    class="w-full pl-9 pr-3 py-2 rounded-full bg-gray-100 border-0 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
            </div>
            {{-- Filters: All / Unread / Groups — the class chat is the one
                 conversation today, so they share its single entry. --}}
            <div class="flex gap-1.5 mt-3" id="chatTabs">
                <button type="button" data-tab="all" class="tab-pill active px-3.5 py-1.5 rounded-full text-sm font-medium bg-brand-50 text-brand-700">All</button>
                <button type="button" data-tab="unread" class="tab-pill px-3.5 py-1.5 rounded-full text-sm font-medium text-gray-500 hover:bg-gray-100">Unread</button>
                <button type="button" data-tab="groups" class="tab-pill px-3.5 py-1.5 rounded-full text-sm font-medium text-gray-500 hover:bg-gray-100">Groups</button>
            </div>
        </div>

        <div id="conversationList" class="flex-1 overflow-y-auto px-2 pb-3">
            @foreach($conversations as $index => $conversation)
                <button type="button" data-conversation data-name="{{ strtolower($conversation['name']) }}"
                    data-unread="{{ $conversation['unread'] ? 1 : 0 }}"
                    onclick="openConversation()"
                    class="conversation-item w-full text-left flex items-center gap-3 px-2.5 py-2.5 rounded-xl {{ $index === 0 ? 'bg-brand-50' : 'hover:bg-gray-50' }} transition">
                    <span class="relative shrink-0">
                        @include('partials.avatar', ['user' => $coordinator, 'class' => 'w-11 h-11'])
                        <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-[15px] font-semibold text-gray-900 truncate">{{ $conversation['name'] }}</span>
                            <span class="text-[11px] text-gray-400 shrink-0">{{ $conversation['preview_at'] ?? '' }}</span>
                        </span>
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-[13px] text-gray-500 truncate">{{ $conversation['preview'] }}</span>
                            @if($conversation['unread'])
                                <span class="shrink-0 w-2.5 h-2.5 rounded-full bg-brand-500"></span>
                            @endif
                        </span>
                    </span>
                </button>
            @endforeach
        </div>
    </aside>

    {{-- ============ Center: the conversation ============ --}}
    <section id="chatPane" class="hidden md:flex flex-1 flex-col min-w-0 bg-white">
        {{-- Chat header --}}
        <div class="px-4 py-2.5 border-b border-gray-100 flex items-center gap-3 shrink-0">
            <button type="button" onclick="showListView()" class="md:hidden text-gray-500 hover:text-gray-900 p-1" aria-label="Back to chats">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </button>
            @include('partials.avatar', ['user' => $coordinator, 'class' => 'w-9 h-9'])
            <div class="min-w-0 flex-1 cursor-pointer" onclick="toggleInfoPanel()">
                <p id="chatTitle" class="text-[15px] font-semibold text-gray-900 truncate leading-tight">{{ $chatName }}</p>
                <p class="text-[11px] text-gray-400">{{ $activeMembers->count() + 1 }} members · everyone sees every message</p>
            </div>
            <button type="button" onclick="toggleInfoPanel()" class="p-2 rounded-full text-brand-600 hover:bg-brand-50 transition" title="Chat info">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 12v4"/></svg>
            </button>
        </div>

        {{-- The feed --}}
        <div id="chatFeed" class="flex-1 overflow-y-auto px-4 py-5 space-y-2.5">
            @forelse($messages as $message)
                @php $author = $message->author; $mine = $author->is(auth()->user()); @endphp
                <div class="chat-msg flex {{ $mine ? 'justify-end' : 'justify-start' }}" data-msg-id="{{ $message->id }}" data-body="{{ mb_strtolower($message->body) }}">
                    @if(! $mine)
                        <div class="w-7 mr-2 shrink-0 self-end mb-0.5">
                            @include('partials.avatar', ['user' => $author, 'class' => 'w-7 h-7'])
                        </div>
                    @endif
                    <div class="max-w-[78%] sm:max-w-[70%]">
                        @if(! $mine)
                            <p class="text-[11px] font-medium text-gray-500 mb-0.5 ml-1">{{ $author->full_name }}<span class="text-gray-400 font-normal"> · {{ $author->role_label }}</span></p>
                        @endif
                        <div class="px-3.5 py-2 text-sm leading-relaxed whitespace-pre-line break-words {{ $mine
                            ? 'bg-brand-600 text-white rounded-2xl rounded-br-md'
                            : 'bg-gray-100 text-gray-800 rounded-2xl rounded-bl-md' }}">
                            {{ $message->body }}
                        </div>
                        <p class="text-[10px] text-gray-400 mt-0.5 {{ $mine ? 'text-right mr-1' : 'ml-1' }}">{{ $message->created_at->format('M d, g:i A') }}</p>
                    </div>
                </div>
            @empty
                <div class="h-full flex items-center justify-center py-16">
                    <p class="text-sm text-gray-400">
                        No messages yet — say hello{{ $isCoordinator ? ' or post an announcement to your class.' : ' to start the conversation.' }}
                    </p>
                </div>
            @endforelse

            {{-- The "typing" caret — shows while a sent message is in flight. --}}
            <div id="typingRow" style="display:none" class="justify-end pr-1">
                <div class="bg-brand-600/70 rounded-2xl rounded-br-md px-3 py-2.5 flex items-center">
                    <span class="typing-caret inline-block w-[2px] h-4 bg-white/90"></span>
                </div>
            </div>
        </div>

        @if($iLeft)
            {{-- Left the class: read-only with a way back through the coordinator. --}}
            <div class="border-t border-gray-100 px-4 py-6 text-center bg-gray-50">
                <p class="text-sm text-gray-500">You left this class — ask your coordinator to add you back to post.</p>
            </div>
        @else
            {{-- Composer — pill input, Enter sends, no page reload. --}}
            <form id="chatForm" class="border-t border-gray-100 p-3 shrink-0">
                <div class="flex items-end gap-2 bg-gray-100 rounded-full pl-4 pr-1.5 py-1.5">
                    <textarea id="chatInput" name="body" rows="1" maxlength="2000" placeholder="Aa"
                        class="flex-1 resize-none max-h-32 bg-transparent border-0 focus:ring-0 focus:outline-none text-sm py-1.5 placeholder:text-gray-400"></textarea>
                    <button type="submit" aria-label="Send"
                        class="my-0.5 shrink-0 w-8 h-8 rounded-full bg-brand-600 hover:bg-brand-700 text-white flex items-center justify-center transition">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M3.4 20.4l17.45-7.48a1 1 0 0 0 0-1.84L3.4 3.6a.993.993 0 0 0-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91z"/></svg>
                    </button>
                </div>
            </form>
        @endif
    </section>

    {{-- ============ Right: chat info panel (desktop rail; full-screen overlay on mobile) ============ --}}
    <aside id="infoPanel" class="w-80 lg:w-96 shrink-0 border-l border-gray-100 flex-col overflow-y-auto">
        <div class="p-5 text-center border-b border-gray-100">
            <div class="flex justify-end">
                <button type="button" onclick="toggleInfoPanel()" class="p-1.5 rounded-full text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition" title="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex justify-center -mt-4">
                @include('partials.avatar', ['user' => $coordinator, 'class' => 'w-20 h-20 text-2xl'])
            </div>
            <p id="infoChatName" class="mt-3 text-base font-semibold text-gray-900">{{ $chatName }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $activeMembers->count() + 1 }} members</p>

            {{-- Icon row: mute + in-chat search --}}
            <div class="flex justify-center gap-6 mt-4">
                <form method="POST" action="{{ route('class-board.mute') }}">
                    @csrf
                    <button type="submit" class="flex flex-col items-center gap-1 text-[11px] {{ $muted ? 'text-brand-600' : 'text-gray-500' }} hover:opacity-75 transition">
                        <span class="w-9 h-9 rounded-full {{ $muted ? 'bg-brand-50' : 'bg-gray-100' }} flex items-center justify-center">
                            @if($muted)
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M18.63 13A17.9 17.9 0 0 1 18 8"/><path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"/><path d="M18 8a6 6 0 0 0-9.33-5"/><path d="m1 1 22 22"/></svg>
                            @else
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            @endif
                        </span>
                        {{ $muted ? 'Unmute' : 'Mute' }}
                    </button>
                </form>
                <button type="button" onclick="toggleChatSearch()" class="flex flex-col items-center gap-1 text-[11px] text-gray-500 hover:opacity-75 transition">
                    <span class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    </span>
                    Search
                </button>
            </div>
            <div id="chatSearchBlock" class="hidden mt-3">
                <input type="search" id="msgSearch" placeholder="Search in conversation" autocomplete="off"
                    class="w-full px-3 py-2 rounded-lg bg-gray-100 border-0 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
            </div>
        </div>

        <div class="divide-y divide-gray-100">
            {{-- Chat info --}}
            <details class="px-5 py-3.5" open>
                <summary class="flex items-center justify-between cursor-pointer select-none text-sm font-semibold text-gray-900 list-none">
                    Chat info
                    <svg class="text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <dl class="mt-3 space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3"><dt class="text-gray-400">Class</dt><dd class="text-gray-800 text-right">{{ $isCoordinator ? $activeMembers->count().' interns' : $coordinator->full_name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-400">Coordinator</dt><dd class="text-gray-800">{{ $coordinator->full_name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-400">Pinned messages</dt><dd class="text-gray-400">None yet</dd></div>
                </dl>
            </details>

            {{-- Customize chat --}}
            <details class="px-5 py-3.5">
                <summary class="flex items-center justify-between cursor-pointer select-none text-sm font-semibold text-gray-900 list-none">
                    Customize chat
                    <svg class="text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                @if($isCoordinator)
                    <form method="POST" action="{{ route('class-board.rename') }}" class="mt-3 flex gap-2">
                        @csrf
                        <input type="text" name="name" value="{{ \App\Models\ClassMember::chatNameFor(auth()->id()) ?? '' }}" required maxlength="60" placeholder="Class chat name"
                            class="flex-1 px-3 py-2 rounded-lg bg-gray-100 border-0 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition">Save</button>
                    </form>
                @else
                    <p class="mt-3 text-[13px] text-gray-400">Only the coordinator can rename this chat.</p>
                @endif
            </details>

            {{-- Chat members --}}
            <details class="px-5 py-3.5" open>
                <summary class="flex items-center justify-between cursor-pointer select-none text-sm font-semibold text-gray-900 list-none">
                    Chat members
                    <span class="text-[11px] font-normal text-gray-400">{{ $activeMembers->count() + 1 }}</span>
                </summary>
                <div class="mt-3 space-y-2.5">
                    <div class="flex items-center gap-2.5">
                        @include('partials.avatar', ['user' => $coordinator, 'class' => 'w-8 h-8'])
                        <div class="min-w-0">
                            <p class="text-[13px] font-medium text-gray-900 truncate">{{ $coordinator->full_name }}</p>
                            <p class="text-[11px] text-gray-400">Coordinator</p>
                        </div>
                    </div>
                    @foreach($activeMembers as $member)
                        <div class="flex items-center gap-2.5">
                            @include('partials.avatar', ['user' => $member, 'class' => 'w-8 h-8'])
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-medium text-gray-900 truncate">{{ $member->full_name }}</p>
                                <p class="text-[11px] text-gray-400">{{ $member->role_label }}@if($memberPrefs->get($member->id)?->muted_at !== null) · muted @endif</p>
                            </div>
                        </div>
                    @endforeach
                    @forelse($leftMembers as $member)
                        <div class="flex items-center gap-2.5 opacity-50">
                            @include('partials.avatar', ['user' => $member, 'class' => 'w-8 h-8'])
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-medium text-gray-700 truncate">{{ $member->full_name }}</p>
                                <p class="text-[11px] text-gray-400">Left the class</p>
                            </div>
                            @if($isCoordinator)
                                <form method="POST" action="{{ route('class-board.members.add-back', $member) }}">
                                    @csrf
                                    <button type="submit" class="text-[11px] font-medium text-brand-600 hover:underline">Add back</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        @if($isCoordinator)
                            <p class="text-[11px] text-gray-400">No one has left the class.</p>
                        @endif
                    @endforelse
                </div>
            </details>

            {{-- Media, files and links --}}
            <details class="px-5 py-3.5">
                <summary class="flex items-center justify-between cursor-pointer select-none text-sm font-semibold text-gray-900 list-none">
                    Media, files and links
                    <svg class="text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <p class="mt-3 text-[13px] text-gray-400">Photos and files shared in this class will appear here.</p>
            </details>

            {{-- Privacy & support --}}
            <details class="px-5 py-3.5">
                <summary class="flex items-center justify-between cursor-pointer select-none text-sm font-semibold text-gray-900 list-none">
                    Privacy &amp; support
                    <svg class="text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="mt-3 space-y-3">
                    <form method="POST" action="{{ route('class-board.mute') }}" class="flex items-center justify-between gap-3">
                        @csrf
                        <span class="text-[13px] text-gray-700">Chat notification</span>
                        <button type="submit" class="text-xs font-medium px-3 py-1.5 rounded-lg {{ $muted ? 'bg-brand-50 text-brand-700 hover:bg-brand-100' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }} transition">
                            {{ $muted ? 'Unmute' : 'Mute' }}
                        </button>
                    </form>
                    <details>
                        <summary class="cursor-pointer select-none text-[13px] font-medium text-red-600 list-none">Report</summary>
                        <form method="POST" action="{{ route('class-board.report') }}" class="mt-2 space-y-2">
                            @csrf
                            <textarea name="reason" rows="3" required maxlength="1000" placeholder="What's wrong in this chat? The System Admin reads this."
                                class="w-full px-3 py-2 rounded-lg border-gray-200 text-xs focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition"></textarea>
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition">Send report</button>
                        </form>
                    </details>
                </div>
            </details>

            {{-- Leave group — classes only, and never the coordinator --}}
            @unless($isCoordinator)
                <div class="px-5 py-4">
                    <form method="POST" action="{{ route('class-board.leave') }}"
                        data-confirm-title="Leave class"
                        data-confirm-message="Leave this class? You'll stop seeing it and receiving its messages — your coordinator can add you back."
                        data-confirm-action="Leave"
                        onsubmit="return askConfirm(this);">
                        @csrf
                        <button type="submit" class="w-full text-sm font-medium text-red-600 hover:bg-red-50 py-2 rounded-lg transition">Leave group</button>
                    </form>
                </div>
            @endunless
        </div>
    </aside>
</div>

@include('partials.confirm-modal')

@push('scripts')
<style>
    @keyframes caretBlink { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }
    .typing-caret { animation: caretBlink 0.9s steps(1) infinite; }
</style>
<script>
(function () {
    const feed = document.getElementById('chatFeed');
    const listPane = document.getElementById('chatListPane');
    const chatPane = document.getElementById('chatPane');
    const infoPanel = document.getElementById('infoPanel');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const typingRow = document.getElementById('typingRow');
    // The newest message id on the page — the poll asks for everything
    // after it. (Never :last-of-type here: the typing row is a div too,
    // so that selector matched nothing and crashed the whole script.)
    const renderedMsgs = document.querySelectorAll('.chat-msg[data-msg-id]');
    let lastMsgId = renderedMsgs.length ? Number(renderedMsgs[renderedMsgs.length - 1].dataset.msgId) : 0;
    let sending = false;

    feed.scrollTop = feed.scrollHeight;

    // ---- mobile: two screens, list ↔ conversation ----
    window.openConversation = function () {
        listPane.classList.add('hidden');
        chatPane.classList.remove('hidden');
        chatPane.classList.add('flex');
        setTimeout(() => { input && input.focus(); }, 60);
    };

    window.showListView = function () {
        chatPane.classList.add('hidden');
        chatPane.classList.remove('flex');
        listPane.classList.remove('hidden');
    };

    // The panes carry their desktop layout in the markup (md:flex) — JS
    // only has to manage the mobile two-screen flow, so a script error
    // can never leave the board half-drawn.
    if (! window.matchMedia('(min-width: 768px)').matches) {
        chatPane.classList.add('hidden');
        chatPane.classList.remove('flex');
    }

    // ---- info panel: desktop rail toggle; mobile full-screen overlay ----
    infoPanel.classList.add('hidden');

    window.toggleInfoPanel = function () {
        if (window.matchMedia('(min-width: 768px)').matches) {
            infoPanel.classList.toggle('hidden');
            infoPanel.classList.toggle('flex');
        } else if (infoPanel.classList.contains('hidden')) {
            infoPanel.classList.remove('hidden');
            ['fixed', 'inset-0', 'z-50', 'w-full', 'bg-white', 'flex'].forEach((c) => infoPanel.classList.add(c));
        } else {
            infoPanel.classList.add('hidden');
            ['fixed', 'inset-0', 'z-50', 'w-full', 'bg-white', 'flex'].forEach((c) => infoPanel.classList.remove(c));
        }
    };

    // ---- conversation list: search + tab filters ----
    const searchInput = document.getElementById('chatSearch');
    let activeTab = 'all';
    function filterConversations() {
        const q = (searchInput.value || '').toLowerCase();
        document.querySelectorAll('[data-conversation]').forEach((item) => {
            const matchesName = item.dataset.name.includes(q);
            const matchesTab = activeTab === 'all'
                || (activeTab === 'unread' && item.dataset.unread === '1')
                || (activeTab === 'groups'); // the class chat is a group
            item.style.display = matchesName && matchesTab ? '' : 'none';
        });
    }
    searchInput.addEventListener('input', filterConversations);
    document.querySelectorAll('.tab-pill').forEach((pill) => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.tab-pill').forEach((p) => {
                p.classList.remove('bg-brand-50', 'text-brand-700');
                p.classList.add('text-gray-500', 'hover:bg-gray-100');
            });
            pill.classList.add('bg-brand-50', 'text-brand-700');
            pill.classList.remove('text-gray-500', 'hover:bg-gray-100');
            activeTab = pill.dataset.tab;
            filterConversations();
        });
    });

    // ---- in-chat message search (right panel → Search) ----
    window.toggleChatSearch = function () {
        document.getElementById('chatSearchBlock').classList.toggle('hidden');
    };
    document.getElementById('msgSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.chat-msg').forEach((bubble) => {
            bubble.style.display = ! q || bubble.dataset.body.includes(q) ? '' : 'none';
        });
    });

    // ---- bubbles ----
    function bubbleHtml(m) {
        const mine = !!m.mine;
        let html = '<div class="chat-msg flex ' + (mine ? 'justify-end' : 'justify-start') + '" data-msg-id="' + m.id + '" data-body="' + esc(m.body.toLowerCase()) + '">';
        if (! mine) {
            html += '<div class="w-7 mr-2 shrink-0 self-end mb-0.5">' + avatarHtml(m) + '</div>';
        }
        html += '<div class="max-w-[78%] sm:max-w-[70%]">';
        if (! mine) {
            html += '<p class="text-[11px] font-medium text-gray-500 mb-0.5 ml-1">' + esc(m.author) +
                '<span class="text-gray-400 font-normal"> · ' + esc(m.authorRole) + '</span></p>';
        }
        html += '<div class="px-3.5 py-2 text-sm leading-relaxed whitespace-pre-line break-words ' + (mine ? 'bg-brand-600 text-white rounded-2xl rounded-br-md' : 'bg-gray-100 text-gray-800 rounded-2xl rounded-bl-md') + '">' +
            esc(m.body) + '</div>' +
            '<p class="text-[10px] text-gray-400 mt-0.5 ' + (mine ? 'text-right mr-1' : 'ml-1') + '">' + esc(m.at) + '</p></div></div>';
        return html;
    }

    function avatarHtml(m) {
        if (m.avatarUrl) {
            return '<img src="' + esc(m.avatarUrl) + '" alt="" class="w-7 h-7 rounded-full object-cover border border-gray-200">';
        }
        return '<span class="w-7 h-7 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center text-[10px] font-semibold text-gray-500">' + esc(m.initials || '•') + '</span>';
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function appendMessage(m) {
        if (document.querySelector('[data-msg-id="' + m.id + '"]')) { return; }
        typingRow.classList.add('hidden');
        feed.insertAdjacentHTML('beforeend', bubbleHtml(m));
        lastMsgId = Math.max(lastMsgId, Number(m.id));
        feed.scrollTop = feed.scrollHeight;
    }

    // ---- send: optimistic bubble + blinking caret, no reload ----
    const csrf = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').content
        : '{{ csrf_token() }}';

    if (form) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && ! e.shiftKey) {
                e.preventDefault();
                form.requestSubmit();
            }
        });
        input.addEventListener('input', function () {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 128) + 'px';
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const body = input.value.trim();
            if (! body || sending) { return; }
            sending = true;

            // Optimistic bubble with a pending look + the blinking caret.
            const pending = document.createElement('div');
            pending.className = 'chat-msg flex justify-end';
            pending.innerHTML = '<div class="max-w-[78%] sm:max-w-[70%]">' +
                '<div class="px-3.5 py-2 text-sm leading-relaxed whitespace-pre-line break-words bg-brand-600/60 text-white rounded-2xl rounded-br-md">' + esc(body) + '</div></div>';
            feed.insertBefore(pending, typingRow);
            typingRow.classList.remove('hidden');
            feed.scrollTop = feed.scrollHeight;

            const form_data = new FormData();
            form_data.append('body', body);
            form_data.append('_token', csrf);

            fetch("{{ route('class-board.store') }}", {
                method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin', body: form_data,
            }).then((res) => {
                if (! res.ok) { throw new Error('failed'); }
                return res.json();
            }).then((data) => {
                pending.remove();
                appendMessage(data.message);
                input.value = '';
                input.style.height = 'auto';
            }).catch(() => {
                pending.remove();
                typingRow.classList.add('hidden');
                window.alert('The message could not be sent — check your connection and try again.');
            }).finally(() => { sending = false; });
        });
    }

    // ---- poll for the others' messages ----
    setInterval(function () {
        if (document.hidden || sending) { return; }
        fetch("{{ route('class-board.feed') }}?after=" + lastMsgId, {
            headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
        }).then((res) => res.ok ? res.json() : null).then((data) => {
            if (data && data.messages) { data.messages.forEach(appendMessage); }
        }).catch(() => {});
    }, 5000);
})();
</script>
@endpush
@endsection
