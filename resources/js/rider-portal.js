document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('[data-rider-portal]')) return;
    const modal = document.getElementById('rider-confirm');
    const dirtyForms = [...document.querySelectorAll('form[data-rider-dirty]')];
    const snapshot = form => JSON.stringify([...new FormData(form)].map(([key, value]) => [key, value instanceof File ? (value.name ? `${value.name}:${value.size}:${value.lastModified}` : '') : value]));
    const initial = new Map(dirtyForms.map(form => [form, snapshot(form)]));
    let sending = false;
    const dirty = () => !sending && dirtyForms.some(form => snapshot(form) !== initial.get(form));
    const warnings = {
        logout: ['Log out of Rider Portal?', 'Make sure any delivery status updates have been saved before leaving.', 'Logout', 'Stay Logged In'],
        accept_pickup: ['Accept this pickup?', 'You are confirming that this delivery has been assigned to you and you are ready to collect the parcel.', 'Accept Pickup'],
        confirm_pickup: ['Confirm Parcel Pickup', 'Have you received the correct parcel from the Seller? Confirm the parcel details before continuing.', 'Confirm Pickup'],
        legacy_pickup: ['Confirm Parcel Pickup', 'Have you received the correct parcel from the Seller? Confirm the parcel details before continuing.', 'Confirm Pickup'],
        arrive_sorting: ['Confirm Sorting Center Arrival', 'Confirm that you and the parcel have arrived at the Sorting Center. Logistics will receive the parcel.', 'Confirm Arrival'],
        accept_delivery: ['Accept Delivery Assignment?', 'Confirm you have the correct parcel and are ready to handle this delivery.', 'Accept Delivery'],
        start_delivery: ['Start Delivery?', 'This will mark the parcel as Out for Delivery and notify the delivery tracking system. Make sure you have the correct parcel before continuing.', 'Start Delivery'],
        legacy_out: ['Start Delivery?', 'This will mark the parcel as Out for Delivery. Make sure you have the correct parcel before continuing.', 'Start Delivery'],
        legacy_transit: ['Start Transit?', 'Confirm you have the correct parcel and are starting transit.', 'Start Transit'],
        delivered: ['Confirm Delivery', 'Are you sure this parcel has been successfully delivered to the correct recipient? Only mark a parcel as Delivered after the delivery has actually been completed.', 'Confirm Delivered'],
        failed: ['Submit Delivery Problem?', 'This records a failed delivery attempt and notifies Logistics. Check the reason and notes before continuing.', 'Submit Report'],
        decline: ['Decline this assignment?', 'This returns the parcel to Logistics for reassignment. Check your reason before continuing.', 'Decline Assignment'],
        pod: ['Submit Proof of Delivery?', 'Confirm this photo and recipient describe the actual delivery. Submitting a changed proof sends it to Logistics for review.', 'Submit Proof'],
        cod: ['Confirm COD Collection?', 'Confirm you have received the cash amount shown for this order.', 'Confirm Collection'],
    };
    let proceed = null;
    function confirm(config, callback) {
        if (!modal || modal.open) return;
        const [title, message, action, cancel = 'Cancel'] = config;
        document.getElementById('rider-confirm-title').textContent = title;
        document.getElementById('rider-confirm-message').textContent = message;
        modal.querySelector('[data-rider-accept]').textContent = action;
        modal.querySelector('[data-rider-cancel]').textContent = cancel;
        proceed = callback;
        modal.showModal();
        modal.querySelector('[data-rider-cancel]').focus();
    }
    modal?.querySelector('[data-rider-cancel]').addEventListener('click', () => { proceed = null; modal.close(); });
    modal?.querySelector('[data-rider-accept]').addEventListener('click', () => { const callback = proceed; proceed = null; modal.close(); callback?.(); });
    modal?.addEventListener('cancel', () => { proceed = null; });
    document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        const visible = input.type === 'password'; input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Hide' : 'Show'; button.setAttribute('aria-pressed', String(visible));
    }));
    document.querySelectorAll('[data-rider-menu]').forEach(button => button.addEventListener('click', () => {
        const menu = document.getElementById('rider-menu'); menu.checked = !menu.checked;
        document.querySelectorAll('[data-rider-menu]').forEach(item => item.setAttribute('aria-expanded', String(menu.checked)));
    }));
    document.querySelectorAll('[data-rider-open]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.riderOpen)?.showModal()));
    document.querySelectorAll('[data-rider-close]').forEach(button => button.addEventListener('click', () => {
        const dialog = button.closest('dialog');
        const form = dialog.querySelector('[data-rider-dirty]');
        if (form && snapshot(form) !== initial.get(form)) {
            confirm(['Discard unsaved changes?', 'Your report has not been submitted.', 'Discard', 'Stay'], () => { form.reset(); initial.set(form, snapshot(form)); dialog.close(); });
        } else dialog.close();
    }));
    document.querySelectorAll('dialog:not(#rider-confirm)').forEach(dialog => dialog.addEventListener('cancel', event => {
        const form = dialog.querySelector('[data-rider-dirty]');
        if (form && snapshot(form) !== initial.get(form)) { event.preventDefault(); dialog.querySelector('[data-rider-close]').click(); }
    }));
    document.querySelectorAll('select[name="reason"]').forEach(select => {
        const update = () => { const notes = select.form.querySelector('[name="details"]'); if (notes) notes.required = select.value === 'other'; };
        select.addEventListener('change', update); update();
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get') return;
        if (sending || form.dataset.submitting === 'true') { event.preventDefault(); return; }
        if (form.dataset.riderConfirm && form.dataset.approved !== 'true') {
            event.preventDefault();
            let config = [...warnings[form.dataset.riderConfirm]];
            if (form.dataset.tracking) config[1] += `\n\nTracking: ${form.dataset.tracking}${form.dataset.shop ? `\nShop: ${form.dataset.shop}` : ''}`;
            const unrelatedDirty = dirtyForms.some(other => other !== form && snapshot(other) !== initial.get(other));
            if (unrelatedDirty) config[1] += '\n\nYou also have unsaved form changes. Continuing will discard them.';
            confirm(config, () => { form.dataset.approved = 'true'; form.requestSubmit(); });
            return;
        }
        if (dirty() && !form.hasAttribute('data-rider-dirty') && form.dataset.approved !== 'true') {
            event.preventDefault();
            confirm(['You have unsaved changes. Leave this page?', 'Your form changes will not be saved.', 'Leave', 'Stay'], () => { form.dataset.approved = 'true'; form.requestSubmit(); });
            return;
        }
        sending = true; form.dataset.submitting = 'true';
        // Delay disabling until native form data has been constructed.
        queueMicrotask(() => { document.querySelectorAll('button[type="submit"], form button:not([type])').forEach(button => { button.disabled = true; }); const button = event.submitter || form.querySelector('button:not([type="button"])'); if (button) button.textContent = button.dataset.loadingLabel || 'Updating…'; });
    });
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || link.target === '_blank' || link.hasAttribute('download') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || !dirty()) return;
        if (link.getAttribute('href').startsWith('#')) return;
        event.preventDefault();
        confirm(['You have unsaved changes. Leave this page?', 'Your form changes will not be saved.', 'Leave', 'Stay'], () => { sending = true; window.location.assign(link.href); });
    });
    window.addEventListener('beforeunload', event => { if (dirty()) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
});
