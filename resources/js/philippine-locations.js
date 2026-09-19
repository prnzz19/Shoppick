function setupPhilippineLocation(root) {
    const endpoint = root.dataset.endpoint.replace(/\/$/, '');
    const selects = Object.fromEntries(['region', 'province', 'city', 'barangay'].map(level => [level, root.querySelector(`[data-location-select="${level}"]`)]));
    const names = Object.fromEntries(['region', 'province', 'city', 'barangay'].map(level => [level, root.querySelector(`[data-location-name="${level}"]`)]));
    const error = root.querySelector('[data-location-error]');
    const noProvince = root.querySelector('[data-location-no-province]');
    let initial = {
        region: { code: root.dataset.initialRegionCode, name: root.dataset.initialRegion },
        province: { code: root.dataset.initialProvinceCode, name: root.dataset.initialProvince },
        city: { code: root.dataset.initialCityCode, name: root.dataset.initialCity },
        barangay: { code: root.dataset.initialBarangayCode, name: root.dataset.initialBarangay },
    };

    const setOptions = (select, records, placeholder, selected = {}) => {
        select.replaceChildren(new Option(placeholder, ''));
        records.forEach(record => select.add(new Option(record.name, record.code)));
        const match = selected.code
            ? records.find(record => record.code === selected.code)
            : records.find(record => record.name.toLocaleLowerCase() === (selected.name || '').toLocaleLowerCase());
        if (match) select.value = match.code;
        select.disabled = false;
    };
    const reset = (level, placeholder) => {
        selects[level].replaceChildren(new Option(placeholder, ''));
        selects[level].disabled = true;
        if (names[level]) names[level].value = '';
    };
    const request = async path => {
        error.classList.add('hidden');
        const response = await fetch(`${endpoint}/${path}`, { headers: { Accept: 'application/json' } });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(json.message || 'Unable to load locations.');
        return Array.isArray(json.data) ? json.data : [];
    };
    const chooseName = level => {
        names[level].value = selects[level].selectedOptions[0]?.textContent || '';
    };

    async function loadBarangays(selected = {}) {
        reset('barangay', 'Loading barangays...');
        if (!selects.city.value) return reset('barangay', 'Select city / municipality first');
        setOptions(selects.barangay, await request(`cities-municipalities/${encodeURIComponent(selects.city.value)}/barangays`), 'Select barangay', selected);
        if (selects.barangay.value) chooseName('barangay');
    }
    async function loadCities(path, selected = {}) {
        reset('city', 'Loading cities / municipalities...');
        reset('barangay', 'Select city / municipality first');
        setOptions(selects.city, await request(path), 'Select city / municipality', selected);
        if (selects.city.value) { chooseName('city'); await loadBarangays(initial.barangay); }
    }
    async function loadRegionChildren(selectedProvince = {}) {
        reset('province', 'Loading provinces...');
        reset('city', 'Select province first');
        reset('barangay', 'Select city / municipality first');
        noProvince.classList.add('hidden');
        const regionCode = selects.region.value;
        if (!regionCode) return;
        const provinces = await request(`regions/${encodeURIComponent(regionCode)}/provinces`);
        if (provinces.length) {
            selects.province.required = true;
            setOptions(selects.province, provinces, 'Select province', selectedProvince);
            if (selects.province.value) {
                chooseName('province');
                await loadCities(`provinces/${encodeURIComponent(selects.province.value)}/cities-municipalities`, initial.city);
            }
            return;
        }
        selects.province.required = false;
        selects.province.replaceChildren(new Option('Not applicable', ''));
        selects.province.disabled = true;
        names.province.value = '';
        noProvince.classList.remove('hidden');
        await loadCities(`regions/${encodeURIComponent(regionCode)}/cities-municipalities`, initial.city);
    }

    selects.region.addEventListener('change', async () => {
        chooseName('region');
        try { await loadRegionChildren(); } catch (e) { showError(e); }
    });
    selects.province.addEventListener('change', async () => {
        chooseName('province');
        try { await loadCities(`provinces/${encodeURIComponent(selects.province.value)}/cities-municipalities`); } catch (e) { showError(e); }
    });
    selects.city.addEventListener('change', async () => {
        chooseName('city');
        try { await loadBarangays(); } catch (e) { showError(e); }
    });
    selects.barangay.addEventListener('change', () => chooseName('barangay'));
    function showError(exception) {
        error.textContent = exception.message;
        error.classList.remove('hidden');
    }

    root.addEventListener('ph-location:set', event => {
        const value = event.detail || {};
        initial = {
            region: { code: value.region_code || '', name: value.region || '' },
            province: { code: value.province_code || '', name: value.province || '' },
            city: { code: value.city_code || '', name: value.city || '' },
            barangay: { code: value.barangay_code || '', name: value.barangay || '' },
        };
        Object.keys(names).forEach(level => { names[level].value = initial[level].name; });
        request('regions').then(async regions => {
            setOptions(selects.region, regions, 'Select region', initial.region);
            if (selects.region.value) { chooseName('region'); await loadRegionChildren(initial.province); }
        }).catch(showError);
    });

    request('regions').then(async regions => {
        setOptions(selects.region, regions, 'Select region', initial.region);
        if (selects.region.value) { chooseName('region'); await loadRegionChildren(initial.province); }
    }).catch(showError);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ph-location]').forEach(setupPhilippineLocation);
});
