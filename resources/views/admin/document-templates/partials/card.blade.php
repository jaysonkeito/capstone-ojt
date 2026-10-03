{{-- One form type's template controls: download the starter, upload an edited
     Word design, and (when one is active) remove it. Copy adapts to whether the
     fallback is a log-driven report's built-in layout or a requirement form's
     blank official copy. Expects $t = ['type','label','category','active']. --}}
@php
    $active = $t['active'];
    $isRequirement = $t['category'] === \App\Models\DocumentTemplate::CATEGORY_REQUIREMENT;
    // Requirement forms whose shipped starter is macroized reach interns
    // filled with their own data even without an uploaded design.
    $builtInFilled = $isRequirement && \App\Models\DocumentTemplate::autofillsByDefault($t['type']);
@endphp
<section class="bg-white border border-gray-200 rounded-xl p-6 sm:p-7">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">{{ $t['label'] }}</h3>
            @if($active)
                <p class="text-xs text-gray-500 mt-1">
                    Using your uploaded design —
                    <span class="text-gray-700">{{ $active->original_name }}</span>
                </p>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    Uploaded {{ $active->updated_at->format('M j, Y \a\t g:i A') }}@if($active->uploader) by {{ $active->uploader->display_name }}@endif
                </p>
            @else
                <p class="text-xs text-gray-500 mt-1">
                    {{ $builtInFilled
                        ? 'Interns download this form filled with their own information — no upload needed.'
                        : ($isRequirement ? 'Interns download the blank official form as shipped.' : 'Using the built-in default design.') }}
                </p>
            @endif
        </div>

        @if($active)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-100 px-2.5 py-1 text-[11px] font-medium text-emerald-700">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Custom template active
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 border border-gray-200 px-2.5 py-1 text-[11px] font-medium text-gray-500">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                {{ $builtInFilled ? 'Built-in filled design' : ($isRequirement ? 'Official form' : 'Built-in default') }}
            </span>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-x-6 gap-y-4">
        {{-- Download the starter blank --}}
        <a href="{{ route('admin.document-templates.starter', $t['type']) }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 transition">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 21h14"/></svg>
            Download starter
        </a>

        {{-- Upload an edited design --}}
        <form method="POST" action="{{ route('admin.document-templates.store', $t['type']) }}"
              enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
            @csrf
            <input type="file" name="template" accept=".docx" required
                   class="block text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-900 file:text-white file:text-xs file:font-medium hover:file:bg-gray-800 file:cursor-pointer">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                {{ $active ? 'Replace' : 'Upload' }}
            </button>
        </form>
    </div>

    @if($active)
        <div class="mt-5 pt-5 border-t border-gray-100">
            <form method="POST" action="{{ route('admin.document-templates.destroy', $t['type']) }}"
                  data-confirm-title="Remove template"
                  data-confirm-message="Remove this template? {{ $t['label'] }} will go back to the {{ $builtInFilled ? 'built-in filled design' : ($isRequirement ? 'blank official form' : 'built-in default design') }}."
                  data-confirm-action="Remove"
                  onsubmit="return askConfirm(this);">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-600 hover:underline transition">
                    {{ $builtInFilled ? 'Remove template & revert to the built-in filled design' : ($isRequirement ? 'Remove template & revert to the blank official form' : 'Remove template & revert to default') }}
                </button>
            </form>
        </div>
    @endif
</section>
