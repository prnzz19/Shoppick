// Real-browser UX test against Blade/JSON fixtures exported from SQLite :memory:.
// Export first: SHOPPICK_SELLER_BROWSER_FIXTURES=1 php artisan test --filter=AdminSellerPerformanceTest::test_export_isolated_browser_fixtures_when_requested
// Launch an isolated headless Edge/Chrome with --remote-debugging-port=9225, then run node tests/Browser/admin-sellers.mjs.
// This server accepts no data mutations and never connects to the real database.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFileSync, writeFileSync } from 'node:fs';

const base = 'http://127.0.0.1:8127';
const fixture = JSON.parse(JSON.stringify(JSON.parse(readFileSync('storage/app/admin-seller-browser-fixtures.json', 'utf8'))).replaceAll('http://127.0.0.1:8000', base));
const signature = params => JSON.stringify(Object.entries(params).map(([k,v]) => [k,String(v)]).sort(([a],[b]) => a.localeCompare(b)));
const responses = new Map(fixture.responses.map(item => [signature(item.params), item.data]));
const requests = [];
let failNext = false;
let delayPanda = false;
let posts = 0;
const server = createServer((req, res) => {
    const url = new URL(req.url, base);
    if (req.method !== 'GET') { posts++; res.writeHead(405).end(); return; }
    if (url.pathname === '/admin/sellers/filter') {
        const params = Object.fromEntries(url.searchParams);
        requests.push(params);
        if (failNext) { failNext = false; res.writeHead(503).end('Unavailable'); return; }
        const data = responses.get(signature(params));
        if (!data) { res.writeHead(422).end(JSON.stringify({message:'Missing test fixture', params})); return; }
        setTimeout(() => { res.writeHead(200, {'Content-Type':'application/json','Cache-Control':'no-store'}).end(JSON.stringify(data)); }, delayPanda && params.q === 'Panda' ? 900 : 25);
    } else if (url.pathname === '/admin/sellers') {
        // External Alpine is unrelated to the Sellers module and omitted for this offline fixture.
        res.writeHead(200, {'Content-Type':'text/html'}).end(fixture.html.replace(/<script src="https:\/\/cdn\.jsdelivr[^>]*><\/script>/, ''));
    } else if (/^\/build\/assets\/[a-zA-Z0-9_.-]+$/.test(url.pathname)) {
        try { res.writeHead(200, {'Content-Type':url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript'}).end(readFileSync('public'+url.pathname)); }
        catch { res.writeHead(404).end(); }
    } else { res.writeHead(404).end(); }
});
await new Promise(resolve => server.listen(8127, '127.0.0.1', resolve));
const targets = await (await fetch('http://127.0.0.1:9225/json/list')).json();
const target = targets.find(item => item.type === 'page');
assert.ok(target, 'Start an isolated debugging browser on port 9225');
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve,reject) => { ws.onopen=resolve; ws.onerror=reject; });
let nextId=0;
const pending=new Map();
const exceptions=[];
ws.onmessage = event => {
    const message=JSON.parse(event.data);
    if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.text);
    const call=pending.get(message.id);
    if (call) { pending.delete(message.id); message.error ? call.reject(new Error(message.error.message)) : call.resolve(message.result); }
};
function cdp(method,params={}) {
    return new Promise((resolve,reject) => { const id=++nextId; pending.set(id,{resolve,reject}); ws.send(JSON.stringify({id,method,params})); });
}
async function evaluate(expression) {
    const result=await cdp('Runtime.evaluate',{expression:`{ ${expression} }`,awaitPromise:true,returnByValue:true});
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
    return result.result.value;
}
const pause = ms => new Promise(resolve => setTimeout(resolve,ms));
async function until(expression) {
    const start=Date.now();
    while (Date.now()-start<10000) { if (await evaluate(expression)) return; await pause(50); }
    throw new Error('Timed out: '+expression);
}
async function change(name,value) {
    await evaluate(`const field=document.querySelector('[data-sellers-form]').elements[${JSON.stringify(name)}];field.value=${JSON.stringify(String(value))};field.dispatchEvent(new Event('change',{bubbles:true}));`);
}
async function updated(action) {
    const count=requests.length;
    await action();
    await until(`document.querySelector('[data-admin-sellers]').dataset.state==='ready' && document.querySelector('[data-sellers-table]').getAttribute('aria-busy')==='false'`);
    assert.equal(requests.length,count+1,'One immediate async request per change');
}
async function clear() { await updated(() => evaluate(`document.querySelector('[data-sellers-clear-control]').click()`)); }
async function tableText() { return evaluate(`document.querySelector('[data-sellers-table]').innerText`); }
async function summary() { return evaluate(`Array.from(document.querySelectorAll('[data-sellers-summary] section')).map(card=>card.querySelector('p:nth-child(2)').textContent.trim())`); }

try {
    await cdp('Page.enable');
    await cdp('Runtime.enable');
    await cdp('Page.navigate',{url:base+'/admin/sellers'});
    await until(`document.querySelector('[data-admin-sellers]')?.dataset.enhanced==='true'`);
    assert.equal(await evaluate(`document.querySelector('[data-sellers-form] button[type="submit"]')!==null`),false);
    assert.equal(await evaluate(`document.querySelector('[data-sellers-clear-control]').hidden`),true);
    await evaluate(`window.sellerDocumentMarker=123;window.scrollTo(0,150)`);
    const scroll=await evaluate('scrollY');
    await updated(() => change('shop',fixture.panda_shop_id));
    assert.deepEqual(await summary(),['1','1','₱9,150.00','2']);
    await updated(() => change('range','30days'));
    assert.deepEqual(await summary(),['1','1','₱150.00','1']);
    for (const [name,value] of Object.entries({status:'approved',sort:'sales_desc',shop_status:'active',sales:'with'})) await updated(() => change(name,value));
    assert.equal(await evaluate('window.sellerDocumentMarker'),123,'Document was not reloaded');
    assert.equal(await evaluate('scrollY'),scroll,'Scroll was preserved');
    assert.ok((await evaluate('location.search')).includes('sales=with'));
    console.log('PASS all six dropdowns auto-apply; cards, URL and scroll stay synchronized');

    await clear();
    assert.equal(await evaluate('location.search'),'');
    assert.equal(await evaluate(`document.querySelector('[data-sellers-clear-control]').hidden`),true);
    const beforeSearch=requests.length;
    for (const text of ['T','Te','Tech']) {
        await evaluate(`const input=document.querySelector('[name="q"]');input.focus();input.value=${JSON.stringify(text)};input.dispatchEvent(new Event('input',{bubbles:true}));`);
        await pause(75);
    }
    assert.equal(requests.length,beforeSearch,'Search waits for the debounce');
    await until(`document.querySelector('[data-sellers-table]').innerText.includes('Tech Seller') && location.search.includes('q=Tech')`);
    assert.equal(requests.length,beforeSearch+1);
    assert.equal(await evaluate('document.activeElement.name'),'q');
    assert.ok(!(await tableText()).includes('Panda Seller'));
    console.log('PASS search debounces 400 ms and preserves keyboard focus');

    await clear();
    const beforeCustom=requests.length;
    await change('range','custom');
    await change('from','2026-10-08');
    await change('to','2026-10-01');
    await pause(150);
    assert.equal(requests.length,beforeCustom,'Incomplete/reversed custom dates send no request');
    assert.ok(await evaluate(`document.querySelector('[data-sellers-error]').textContent.includes('on or after')`));
    await updated(() => change('to','2026-10-08'));
    assert.deepEqual(await summary(),['18','17','₱275.00','3']);
    await clear();
    assert.equal(await evaluate(`document.querySelector('[name="from"]').value`),'');
    console.log('PASS custom dates validate locally and auto-apply only when complete');

    await updated(() => evaluate(`document.querySelector('[data-sellers-pagination] a[href*="page=2"]').click()`));
    assert.ok((await evaluate('location.search')).includes('page=2'));
    assert.ok((await evaluate(`document.querySelector('[data-sellers-pagination]').innerText`)).includes('Showing 16–18'));
    await updated(() => change('range','30days'));
    assert.ok(!(await evaluate('location.search')).includes('page=2'),'Changing a filter resets pagination');
    await evaluate('history.back()');
    await until(`location.search.includes('page=2') && document.querySelector('[name="range"]').value==='all' && document.querySelector('[data-admin-sellers]').dataset.state==='ready'`);
    await evaluate('history.forward()');
    await until(`location.search.includes('range=30days') && document.querySelector('[name="range"]').value==='30days' && document.querySelector('[data-admin-sellers]').dataset.state==='ready'`);
    console.log('PASS async pagination and browser Back/Forward restore matching controls and results');

    await clear();
    const previous=await tableText();
    failNext=true;
    await change('sales','none');
    await until(`document.querySelector('[data-admin-sellers]').dataset.state==='error'`);
    assert.equal(await tableText(),previous,'Failed updates retain visible data');
    assert.equal(await evaluate(`document.querySelector('[data-sellers-table]').getAttribute('aria-busy')`),'false');
    await updated(() => evaluate(`document.querySelector('[data-sellers-retry]').click()`));
    assert.ok(!(await tableText()).includes('Panda Seller'));
    console.log('PASS server failures keep the table, end loading and support Retry');

    await clear();
    const beforeOffline=await tableText();
    await cdp('Network.enable');
    await cdp('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:-1,uploadThroughput:-1});
    await change('sales','none');
    await until(`document.querySelector('[data-admin-sellers]').dataset.state==='error'`);
    assert.equal(await tableText(),beforeOffline);
    assert.equal(await evaluate(`document.querySelector('[data-sellers-loading]').hidden`),true);
    await cdp('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
    await updated(() => evaluate(`document.querySelector('[data-sellers-retry]').click()`));
    console.log('PASS offline failures retain results and recover through Retry');

    await clear();
    delayPanda=true;
    const beforeRace=requests.length;
    await evaluate(`const input=document.querySelector('[name="q"]');input.value='Panda';input.dispatchEvent(new Event('input',{bubbles:true}));`);
    await pause(450);
    assert.equal(requests.length,beforeRace+1);
    assert.equal(await evaluate(`document.querySelector('[data-sellers-table]').getAttribute('aria-busy')`),'true');
    await evaluate(`const input=document.querySelector('[name="q"]');input.value='Tech';input.dispatchEvent(new Event('input',{bubbles:true}));`);
    await until(`location.search.includes('q=Tech') && document.querySelector('[data-admin-sellers]').dataset.state==='ready'`);
    await pause(950);
    assert.ok(!(await tableText()).includes('Panda Seller'),'Old response never replaces the new search');
    delayPanda=false;
    console.log('PASS request cancellation and latest-request protection');

    await clear();
    await updated(() => evaluate(`document.querySelector('[data-sellers-tabs] a[href*="tab=archived"]').click()`));
    assert.ok((await tableText()).includes('Archived Seller'));
    assert.ok((await tableText()).includes('Restore'));
    await evaluate(`document.querySelector('[data-entity-archive-form] button').click()`);
    await until(`!document.querySelector('#shoppick-confirm').classList.contains('hidden')`);
    assert.ok(await evaluate(`document.querySelector('#shoppick-confirm-title').textContent.includes('Restore')`));
    await evaluate(`document.querySelector('[data-confirm-cancel]').click()`);
    assert.equal(posts,0,'Archive confirmation was exercised without submitting a mutation');
    console.log('PASS async Archived tab retains working restore confirmation');

    await updated(() => evaluate(`document.querySelector('[data-sellers-tabs] a:not([href*="tab=archived"])').click()`));
    await updated(() => change('status','suspended'));
    assert.deepEqual(await summary(),['1','0','₱75.00','1']);
    await clear();
    await updated(() => change('shop_status','suspended'));
    assert.deepEqual(await summary(),['1','0','₱75.00','1']);
    await clear();
    await updated(() => change('sort','sales_desc'));
    assert.ok((await tableText()).indexOf('Panda Seller')<(await tableText()).indexOf('Tech Seller'));
    await clear();
    const beforeEmpty=requests.length;
    await evaluate(`const input=document.querySelector('[name="q"]');input.value='no matching seller';input.dispatchEvent(new Event('input',{bubbles:true}));`);
    await until(`new URLSearchParams(location.search).get('q')==='no matching seller' && document.querySelector('[data-admin-sellers]').dataset.state==='ready'`);
    assert.equal(requests.length,beforeEmpty+1);
    assert.ok((await tableText()).includes('No sellers match your filters.'));
    assert.deepEqual(await summary(),['0','0','₱0.00','0']);
    await updated(() => evaluate(`document.querySelector('[data-sellers-table] [data-sellers-clear]').click()`));
    assert.equal(await evaluate('location.search'),'');
    console.log('PASS empty results update all cards and the inline Clear Filters link works');
    assert.deepEqual(exceptions,[],'No browser JavaScript exceptions');
    await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    const screenshot=await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
    writeFileSync('storage/app/admin-sellers-async-preview.png',Buffer.from(screenshot.data,'base64'));
    console.log('PASS status and sorting; screenshot saved to storage/app/admin-sellers-async-preview.png');
} finally {
    ws.close();
    server.closeAllConnections();
    await new Promise(resolve => server.close(resolve));
}
