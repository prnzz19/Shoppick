const locationLists = new Map();

export function setupPhilippineLocation(root) {
    const endpoint = root.dataset.endpoint.replace(/\/$/, '');
    const levels = ['region', 'province', 'city', 'barangay'];
    const selects = Object.fromEntries(levels.map(level => [level, root.querySelector(`[data-location-select="${level}"]`)]));
    const names = Object.fromEntries(levels.map(level => [level, root.querySelector(`[data-location-name="${level}"]`)]));
    const error = root.querySelector('[data-location-error]');
    const retry = root.querySelector('[data-location-retry]');
    const noProvince = root.querySelector('[data-location-no-province]');
    const required = selects.region.required;
    const placeholders = { region: 'Select region', province: 'Select region first', city: 'Select province first', barangay: 'Select city / municipality first' };
    const labels = { region: 'regions', province: 'provinces', city: 'cities / municipalities', barangay: 'barangays' };
    let initial = Object.fromEntries(levels.map(level => [level, {
        code: root.dataset[`initial${level[0].toUpperCase()}${level.slice(1)}Code`] || '',
        name: root.dataset[`initial${level[0].toUpperCase()}${level.slice(1)}`] || '',
    }]));
    let generation = 0;
    let controller;
    let retryAction;
    let affected = 'region';

    function reset(level, placeholder = placeholders[level], clear = true) {
        selects[level].replaceChildren(new Option(placeholder, ''));
        selects[level].disabled = true;
        if (clear) names[level].value = '';
    }
    function setOptions(level, records, placeholder, selected = {}) {
        const select = selects[level];
        select.replaceChildren(new Option(placeholder, ''));
        records.forEach(record => select.add(new Option(record.name, record.code)));
        const match = records.find(record => selected.code && record.code === selected.code)
            || records.find(record => record.name.toLocaleLowerCase() === (selected.name || '').toLocaleLowerCase());
        if (match) select.value = match.code;
        select.disabled = false;
        chooseName(level);
    }
    function chooseName(level) {
        names[level].value = selects[level].value ? selects[level].selectedOptions[0]?.textContent || '' : '';
    }
    async function request(path, level, token) {
        affected = level;
        reset(level, `Loading ${labels[level]}...`, false);
        const url = `${endpoint}/${path}`;
        let records = locationLists.get(url);
        if (!records) {
            const activeController = controller;
            const timeout = setTimeout(() => activeController.abort(), 25000);
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: activeController.signal });
                const json = await response.json();
                if (!response.ok || !Array.isArray(json.data)) throw new Error('Unavailable locations');
                records = json.data;
                locationLists.set(url, records);
            } finally {
                clearTimeout(timeout);
            }
        }
        if (token !== generation) throw new DOMException('Superseded', 'AbortError');
        return records;
    }
    async function loadBarangays(selected, token) {
        if (!selects.city.value) return reset('barangay');
        const code = selects.city.value;
        const records = await request(`cities-municipalities/${encodeURIComponent(code)}/barangays`, 'barangay', token);
        setOptions('barangay', records, 'Select barangay', selected);
    }
    async function loadCities(path, saved, token) {
        reset('barangay', placeholders.barangay, !saved.barangay?.code && !saved.barangay?.name);
        const records = await request(path, 'city', token);
        setOptions('city', records, 'Select city / municipality', saved.city);
        if (selects.city.value) await loadBarangays(saved.barangay, token);
    }
    async function loadRegionChildren(saved, token) {
        reset('province', placeholders.province, !saved.province?.code && !saved.province?.name);
        reset('city', placeholders.city, !saved.city?.code && !saved.city?.name);
        reset('barangay', placeholders.barangay, !saved.barangay?.code && !saved.barangay?.name);
        noProvince.classList.add('hidden');
        if (!selects.region.value) return;
        const code = selects.region.value;
        const provinces = await request(`regions/${encodeURIComponent(code)}/provinces`, 'province', token);
        selects.province.required = required && provinces.length > 0;
        if (provinces.length) {
            setOptions('province', provinces, 'Select province', saved.province);
            if (selects.province.value) await loadCities(`provinces/${encodeURIComponent(selects.province.value)}/cities-municipalities`, saved, token);
        } else {
            reset('province', 'Not applicable');
            noProvince.classList.remove('hidden');
            await loadCities(`regions/${encodeURIComponent(code)}/cities-municipalities`, saved, token);
        }
    }
    async function run(action) {
        controller?.abort();
        controller = new AbortController();
        const token = ++generation;
        retryAction = action;
        error.classList.add('hidden');
        retry.classList.add('hidden');
        root.querySelectorAll('[data-location-field-error]').forEach(el => { el.textContent = ''; el.classList.add('hidden'); });
        retry.disabled = true;
        try {
            await action(token);
        } catch (exception) {
            if (token !== generation) return;
            levels.forEach(level => {
                if (selects[level].disabled && selects[level].options[0]?.textContent.startsWith('Loading ')) {
                    reset(level, `Unable to load ${labels[level]}`, false);
                }
            });
            const message = `Unable to load ${labels[affected]}. Please try again.`;
            const fieldError = root.querySelector(`[data-location-field-error="${affected}"]`);
            fieldError.textContent = message;
            fieldError.classList.remove('hidden');
            error.textContent = message;
            error.classList.remove('hidden');
            retry.classList.remove('hidden');
        } finally {
            if (token === generation) retry.disabled = false;
        }
    }
    const restore = token => loadRegions(initial, token);
    async function loadRegions(saved, token) {
        const regions = await request('regions', 'region', token);
        setOptions('region', regions, 'Select region', saved.region);
        await loadRegionChildren(saved, token);
    }
    selects.region.addEventListener('change', () => {
        initial = {};
        chooseName('region');
        run(token => loadRegionChildren({}, token));
    });
    selects.province.addEventListener('change', () => {
        initial = {};
        chooseName('province');
        reset('city'); reset('barangay');
        const code = selects.province.value;
        run(token => code ? loadCities(`provinces/${encodeURIComponent(code)}/cities-municipalities`, {}, token) : Promise.resolve());
    });
    selects.city.addEventListener('change', () => {
        initial = {};
        chooseName('city'); reset('barangay');
        run(token => loadBarangays({}, token));
    });
    selects.barangay.addEventListener('change', () => chooseName('barangay'));
    retry.addEventListener('click', () => retryAction && run(retryAction));
    root.addEventListener('ph-location:set', event => {
        const value = event.detail || {};
        initial = Object.fromEntries(levels.map(level => [level, { code: value[`${level}_code`] || '', name: value[level] || '' }]));
        levels.forEach(level => { names[level].value = initial[level].name; reset(level, placeholders[level], false); });
        run(restore);
    });
    // A pending/failed cascade must never submit an incomplete structured address.
    root.closest('form')?.addEventListener('submit', event => {
        const active = !root.closest('[hidden]') && !root.closest('.hidden');
        if (required && active && (levels.some(level => selects[level].disabled && !(level === 'province' && !selects.province.required)) || !error.classList.contains('hidden'))) {
            event.preventDefault();
            error.textContent = 'Finish loading and selecting your address before submitting.';
            error.classList.remove('hidden');
        }
    });
    run(restore);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ph-location]').forEach(setupPhilippineLocation);
});
