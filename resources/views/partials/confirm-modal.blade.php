{{-- Confirm-action modal — replaces the browser confirm() prompts on
     destructive buttons with an in-page, centered dialog. Each guarded form
     carries data-confirm-title / data-confirm-message / data-confirm-action
     and calls askConfirm(this) from its onsubmit; the dialog remembers the
     form and only submits it when the user confirms. --}}
<div id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmMessage"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 px-4"
     onclick="if(event.target === this) closeConfirm()">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-sm p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 id="confirmTitle" class="font-semibold text-gray-900"></h3>
            <button type="button" onclick="closeConfirm()" class="text-gray-400 hover:text-gray-900" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <p id="confirmMessage" class="text-sm text-gray-500 leading-relaxed"></p>
        <div class="flex gap-2 pt-5">
            <button type="button" id="confirmProceed" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition"></button>
            <button type="button" onclick="closeConfirm()" class="text-sm font-medium px-4 py-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">Cancel</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var pendingForm = null;

        // Ask before destructive form actions: remember the submitted form and
        // show the dialog; the confirm button then submits it for real. The
        // dialog copy lives on the form's data-confirm-* attributes.
        window.askConfirm = function (form) {
            pendingForm = form;
            document.getElementById('confirmTitle').textContent = form.dataset.confirmTitle;
            document.getElementById('confirmMessage').textContent = form.dataset.confirmMessage;
            document.getElementById('confirmProceed').textContent = form.dataset.confirmAction;
            document.getElementById('confirmModal').classList.remove('hidden');
            return false; // stop the native submit — the modal decides
        };

        window.closeConfirm = function () {
            document.getElementById('confirmModal').classList.add('hidden');
            pendingForm = null;
        };

        // form.submit() bypasses onsubmit, so confirming never re-opens the
        // dialog — it performs the actual submission.
        document.getElementById('confirmProceed').addEventListener('click', function () {
            if (pendingForm) { pendingForm.submit(); }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !document.getElementById('confirmModal').classList.contains('hidden')) {
                closeConfirm();
            }
        });
    });
</script>
@endpush
