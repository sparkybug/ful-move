const money = kobo => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: kobo % 100 ? 2 : 0 }).format(kobo / 100);

const list = document.querySelector('#bus-list');
if (list) {
    let busy = false;
    const refresh = async () => {
        if (busy || document.hidden) return;
        busy = true;
        const status = document.querySelector('#refresh-status');
        try {
            const response = await fetch(list.dataset.pollUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Refresh failed');
            list.innerHTML = await response.text();
            if (status) status.textContent = 'Updated just now · Live';
        } catch {
            if (status) status.textContent = 'Connection interrupted. Retrying…';
        } finally { busy = false; }
    };
    setInterval(refresh, 12000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
}

const bookingForm = document.querySelector('[data-book-form]');
if (bookingForm) {
    const dialog = document.querySelector('#booking-confirm');
    const submit = bookingForm.querySelector('button[type=submit]');
    const feedback = bookingForm.querySelector('.form-feedback');
    let checking = false;
    bookingForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (checking) return;
        checking = true;
        submit.disabled = true;
        feedback.textContent = 'Checking your seat and wallet…';
        try {
            const response = await fetch(bookingForm.dataset.availability, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Could not refresh this ride. Please try again.');
            const ride = await response.json();
            if (ride.status !== 'boarding') throw new Error('This bus is no longer boarding. Choose another bus.');
            if (ride.seats_left < 1) throw new Error('This bus is now full. Choose another bus.');
            if (ride.balance_kobo === null) throw new Error('Your session has expired. Sign in again.');
            if (ride.balance_kobo < ride.fare_kobo) throw new Error('Your wallet needs more credit before you can book.');
            document.querySelector('#booking-summary').textContent = ride.seats_left + ' seats available. One seat costs ' + money(ride.fare_kobo) + '. Your remaining balance will be ' + money(ride.balance_kobo - ride.fare_kobo) + '.';
            feedback.textContent = '';
            dialog.showModal();
        } catch (error) { feedback.textContent = error.message; }
        finally { checking = false; submit.disabled = false; }
    });
    document.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());
    document.querySelector('#confirm-booking').addEventListener('click', event => {
        event.currentTarget.disabled = true;
        event.currentTarget.textContent = 'Reserving your seat…';
        submit.disabled = true;
        HTMLFormElement.prototype.submit.call(bookingForm);
    });
}

const actionDialog = document.querySelector('#action-confirm');
let pendingAction;
let pendingSubmitter;
const approvedForms = new WeakSet();
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (approvedForms.has(form)) return;
        event.preventDefault();
        pendingAction = form;
        pendingSubmitter = event.submitter;
        document.querySelector('#action-confirm-message').textContent = form.dataset.confirm;
        actionDialog.showModal();
    });
});
document.querySelector('#action-confirm-back')?.addEventListener('click', () => actionDialog.close());
document.querySelector('#action-confirm-submit')?.addEventListener('click', event => {
    if (!pendingAction) return;
    event.currentTarget.disabled = true;
    approvedForms.add(pendingAction);
    actionDialog.close();
    pendingSubmitter ? pendingAction.requestSubmit(pendingSubmitter) : pendingAction.requestSubmit();
});

const role = document.querySelector('#register-role');
if (role) {
    const label = document.querySelector('#student-id-label');
    const update = () => {
        label.hidden = role.value !== 'student';
        label.querySelector('input').required = role.value === 'student';
    };
    role.addEventListener('change', update);
    update();
}

const busSelect = document.querySelector('select[name="bus_id"]');
const fareSelect = document.querySelector('select[name="fare_id"]');
if (busSelect && fareSelect) {
    const filter = () => {
        const type = busSelect.selectedOptions[0]?.dataset.type;
        for (const option of fareSelect.options) {
            if (!option.value) continue;
            option.disabled = Boolean(type && option.dataset.type !== type);
            option.hidden = option.disabled;
        }
        if (fareSelect.selectedOptions[0]?.disabled) fareSelect.value = '';
    };
    busSelect.addEventListener('change', filter);
    filter();
}


