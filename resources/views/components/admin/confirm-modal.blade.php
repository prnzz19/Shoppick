<div id="shoppick-confirm" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="shoppick-confirm-title" aria-describedby="shoppick-confirm-message">
    <div data-confirm-panel class="w-full max-w-lg scale-95 rounded-[22px] bg-white p-6 text-center opacity-0 shadow-2xl transition duration-200 sm:p-8">
        <div data-confirm-icon class="mx-auto flex h-20 w-20 items-center justify-center rounded-full border-2 border-accent-400 bg-accent-50 text-3xl font-black text-accent-500" aria-hidden="true">!</div>
        <h2 id="shoppick-confirm-title" class="mt-5 text-xl font-extrabold text-navy-900 sm:text-2xl"></h2>
        <p id="shoppick-confirm-message" class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500"></p>
        <div data-confirm-reason-wrap class="mt-5 hidden text-left">
            <label for="shoppick-confirm-reason" class="text-sm font-semibold text-navy-800">Reason <span class="text-rose-500">*</span></label>
            <textarea id="shoppick-confirm-reason" class="input mt-2 min-h-24 w-full" maxlength="1000" placeholder="Enter a clear reason"></textarea>
            <p data-confirm-reason-error class="mt-1 hidden text-xs font-medium text-rose-600">Please provide a reason.</p>
        </div>
        <div class="mt-7 flex flex-col-reverse justify-center gap-3 sm:flex-row">
            <button type="button" data-confirm-cancel class="btn-outline min-w-32 bg-slate-100 text-navy-800 hover:bg-slate-200">Cancel</button>
            <button type="button" data-confirm-submit class="min-w-32 rounded-xl px-5 py-2.5 text-sm font-bold text-white shadow-sm transition"></button>
        </div>
    </div>
</div>
