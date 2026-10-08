// Run against an isolated Edge/Chrome instance with --remote-debugging-port=9222.
// Reads the real form and endpoints; never submits a real registration.
import assert from 'node:assert/strict';

const base = process.env.SHOPPICK_TEST_URL || 'http://127.0.0.1:8000';
const targets = await (await fetch('http://127.0.0.1:9222/json/list')).json();
const target = targets.find(item => item.type === 'page');
assert.ok(target, 'An isolated browser page must be available');
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
let id = 0;
const pending = new Map();
const errors = [];
ws.onmessage = event => {
    const message = JSON.parse(event.data);
    if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
    const call = pending.get(message.id);
    if (call) {
        pending.delete(message.id);
        message.error ? call.reject(new Error(message.error.message)) : call.resolve(message.result);
    }
};
function cdp(method, params = {}) {
    return new Promise((resolve, reject) => {
        const key = ++id;
        pending.set(key, { resolve, reject });
        ws.send(JSON.stringify({ id: key, method, params }));
    });
}
async function evaluate(expression) {
    const result = await cdp('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    return result.result.value;
}
async function until(expression) {
    const started = Date.now();
    while (Date.now() - started < 30000) {
        if (await evaluate(expression)) return;
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    throw new Error(`Timed out: ${expression}`);
}
const select = level => `document.querySelector('[data-location-select="${level}"]')`;
async function choose(level, code) {
    await evaluate(`${select(level)}.value=${JSON.stringify(code)}; ${select(level)}.dispatchEvent(new Event('change', {bubbles:true}));`);
}
const saved = { region: 'Region IV-A (CALABARZON)', region_code: '0400000000', province: 'Laguna', province_code: '0403400000', city: 'Santa Cruz', city_code: '0403426000', barangay: 'Bubukal', barangay_code: '0403426003' };
async function restore(value) {
    await evaluate(`document.querySelector('[data-ph-location]').dispatchEvent(new CustomEvent('ph-location:set', {detail:${JSON.stringify(value)}}));`);
    await until(`${select('barangay')}.value === '0403426003' && !${select('barangay')}.disabled`);
}

try {
    await cdp('Page.enable');
    await cdp('Runtime.enable');
    await cdp('Page.navigate', { url: `${base}/register/buyer` });
    await until(`${select('region')} && !${select('region')}.disabled && ${select('region')}.options.length > 1`);
    assert.equal(await evaluate(`${select('region')}.options[0].text`), 'Select region');
    assert.ok(await evaluate(`${select('province')}.disabled && ${select('city')}.disabled && ${select('barangay')}.disabled`));
    await evaluate(`document.querySelector('[name="address_line"]').value='12 Test Street'; document.querySelector('[name="postal_code"]').value='4009';`);
    await choose('region', saved.region_code);
    await until(`!${select('province')}.disabled && ${select('province')}.options.length > 1`);
    await choose('province', saved.province_code);
    await until(`!${select('city')}.disabled && ${select('city')}.options.length > 1`);
    await choose('city', saved.city_code);
    await until(`!${select('barangay')}.disabled && ${select('barangay')}.options.length > 1`);
    await choose('barangay', saved.barangay_code);
    const actual = await evaluate(`Object.fromEntries(['region','province','city','barangay'].map(level=>[level, document.querySelector('[data-location-name="'+level+'"]').value]))`);
    assert.deepEqual(actual, { region: saved.region, province: saved.province, city: saved.city, barangay: saved.barangay });
    console.log('PASS live nationwide cascade and canonical hidden names');

    await restore(saved);
    await restore({ ...saved, province_code: '0434000000', city_code: '0434260000', barangay_code: '0434260050' });
    await restore(Object.fromEntries(Object.entries(saved).filter(([key]) => !key.endsWith('_code'))));
    console.log('PASS edit/old-value restoration by code and by legacy name');

    // Rapid changes must not let an earlier region populate a newer selection.
    await choose('region', '1300000000');
    await choose('region', saved.region_code);
    await until(`!${select('province')}.disabled && Array.from(${select('province')}.options).some(option => option.value === '${saved.province_code}')`);
    assert.equal(await evaluate(`${select('city')}.value`), '');
    assert.equal(await evaluate(`${select('barangay')}.value`), '');
    await choose('region', '1300000000');
    await until(`!${select('city')}.disabled && ${select('city')}.options.length > 1`);
    assert.ok(await evaluate(`${select('province')}.disabled && !${select('province')}.required`));
    console.log('PASS rapid parent changes, child resets, and NCR without provinces');

    const injection = await cdp('Page.addScriptToEvaluateOnNewDocument', { source: `
        window.locationTestFailure = true;
        const originalFetch = window.fetch.bind(window);
        window.fetch = (...args) => window.locationTestFailure && String(args[0]).includes('/api/philippine-locations/')
            ? Promise.resolve(new Response(JSON.stringify({message:'internal details'}), {status:503,headers:{'Content-Type':'application/json'}}))
            : originalFetch(...args);
    ` });
    await cdp('Page.navigate', { url: `${base}/register/buyer` });
    await until(`document.querySelector('[data-location-retry]') && !document.querySelector('[data-location-retry]').classList.contains('hidden')`);
    assert.equal(await evaluate(`${select('region')}.options[0].text`), 'Unable to load regions');
    assert.equal(await evaluate(`document.querySelector('[data-location-error]').textContent.includes('internal details')`), false);
    await evaluate(`document.querySelector('[name="address_line"]').value='Kept street'; document.querySelector('[name="postal_code"]').value='4009';`);
    await evaluate(`document.querySelector('[data-ph-location]').dispatchEvent(new CustomEvent('ph-location:set', {detail:${JSON.stringify(saved)}}));`);
    await until(`document.querySelector('[data-location-retry]').disabled === false`);
    assert.equal(await evaluate(`document.querySelector('[data-location-name="barangay"]').value`), 'Bubukal');
    await evaluate(`window.locationTestFailure=false; document.querySelector('[data-location-retry]').click(); document.querySelector('[data-location-retry]').click();`);
    await until(`${select('barangay')}.value === '${saved.barangay_code}' && !${select('barangay')}.disabled`);
    assert.equal(await evaluate(`document.querySelector('[name="address_line"]').value`), 'Kept street');
    assert.equal(await evaluate(`document.querySelector('[name="postal_code"]').value`), '4009');
    assert.ok(await evaluate(`document.querySelector('[data-location-error]').classList.contains('hidden')`));
    await cdp('Page.removeScriptToEvaluateOnNewDocument', { identifier: injection.identifier });
    console.log('PASS clean failure, Retry, saved selections, and preservation of street/postal code');

    const childFailure = await cdp('Page.addScriptToEvaluateOnNewDocument', { source: `
        window.locationTestFailure = true;
        const originalFetch = window.fetch.bind(window);
        window.fetch = (...args) => window.locationTestFailure && String(args[0]).endsWith('/provinces')
            ? Promise.reject(new DOMException('Simulated timeout', 'AbortError')) : originalFetch(...args);
    ` });
    await cdp('Page.navigate', { url: `${base}/register/buyer` });
    await until(`${select('region')} && !${select('region')}.disabled`);
    await evaluate(`document.querySelector('[data-ph-location]').dispatchEvent(new CustomEvent('ph-location:set', {detail:${JSON.stringify(saved)}}));`);
    await until(`!document.querySelector('[data-location-retry]').classList.contains('hidden')`);
    assert.equal(await evaluate(`${select('province')}.options[0].text`), 'Unable to load provinces');
    assert.ok(await evaluate(`!document.querySelector('[data-location-field-error="province"]').classList.contains('hidden')`));
    await evaluate(`window.locationTestFailure=false; document.querySelector('[data-location-retry]').click();`);
    await until(`${select('barangay')}.value === '${saved.barangay_code}' && !${select('barangay')}.disabled`);
    await cdp('Page.removeScriptToEvaluateOnNewDocument', { identifier: childFailure.identifier });
    console.log('PASS child-field timeout and Retry restore the saved hierarchy');
    assert.deepEqual(errors, []);
    console.log('PASS no browser JavaScript exceptions; no real form submitted');
} finally {
    ws.close();
}
