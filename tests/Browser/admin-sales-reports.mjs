// SQLite-generated Laravel responses plus compiled assets; no live database.
// Generate with SHOPPICK_SALES_OVERVIEW_FIXTURES=1 php artisan test --filter=AdminSalesReportsTest.
// Launch an isolated headless Edge with debugging port 9227 before running this script.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFileSync,writeFileSync,mkdirSync,readdirSync} from 'node:fs';
import {resolve} from 'node:path';

const base='http://127.0.0.1:8129', fixtures='storage/app/sales-overview-fixtures/';
const signature=q=>JSON.stringify([...q].sort(([a],[b])=>a.localeCompare(b)));
const raw=JSON.parse(readFileSync(fixtures+'responses.json','utf8').replaceAll('http://127.0.0.1:8000',base));
const responses=new Map(Object.entries(raw).map(([q,data])=>[signature(new URLSearchParams(q)),data]));
const requests=[];
let failNext=false,delayPanda=false,writes=0;
const server=createServer((req,res)=>{
    const url=new URL(req.url,base);
    if(req.method!=='GET'){writes++;res.writeHead(405).end();return;}
    if(url.pathname==='/admin/sales-reports/filter'){
        requests.push(Object.fromEntries(url.searchParams));
        if(failNext){failNext=false;res.writeHead(503).end();return;}
        const data=responses.get(signature(url.searchParams));
        if(!data){res.writeHead(422).end(JSON.stringify({missing:Object.fromEntries(url.searchParams)}));return;}
        setTimeout(()=>res.writeHead(200,{'Content-Type':'application/json'}).end(JSON.stringify(data)),delayPanda&&url.searchParams.get('q')==='Panda'?900:30);
    }else if(url.pathname==='/admin/sales-reports'){
        res.writeHead(200,{'Content-Type':'text/html'}).end(readFileSync(fixtures+'web.html','utf8').replaceAll('http://127.0.0.1:8000',base).replace(/<script src="https:\/\/cdn\.jsdelivr[^>]*><\/script>/,''));
    }else if(['/admin/sales-reports/pdf','/admin/sales-reports/csv'].includes(url.pathname)){
        const format=url.pathname.split('/').at(-1);
        assert.equal(url.searchParams.get('shop'),'1');assert.equal(url.searchParams.get('range'),'week');
        res.writeHead(200,{'Content-Type':format==='pdf'?'application/pdf':'text/csv; charset=UTF-8','Content-Disposition':`attachment; filename="SHOPPICK_Seller_Sales_Report_2026-10-05_to_2026-10-11.${format}"`}).end(readFileSync(fixtures+'report.'+format));
    }else if(url.pathname==='/admin/sales-reports/print'){
        res.writeHead(200,{'Content-Type':'text/html'}).end(readFileSync(fixtures+'print.html'));
    }else if(/^\/build\/assets\/[a-zA-Z0-9_.-]+$/.test(url.pathname)){
        try{res.writeHead(200,{'Content-Type':url.pathname.endsWith('.css')?'text/css':'text/javascript'}).end(readFileSync('public'+url.pathname));}catch{res.writeHead(404).end();}
    }else res.writeHead(404).end();
});
await new Promise(r=>server.listen(8129,'127.0.0.1',r));
const targets=await(await fetch('http://127.0.0.1:9227/json/list')).json();
const ws=new WebSocket(targets.find(t=>t.type==='page' && !t.url.startsWith('edge://')).webSocketDebuggerUrl);
await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
const pending=new Map(),errors=[];
let id=0;
ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);const p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(new Error(m.error.message)):p.resolve(m.result);}};
const cdp=(method,params={})=>new Promise((resolve,reject)=>{const number=++id;pending.set(number,{resolve,reject});ws.send(JSON.stringify({id:number,method,params}));});
async function evaluate(expression){const r=await cdp('Runtime.evaluate',{expression:`{ ${expression} }`,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;}
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function until(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await pause(80);}throw new Error('Timed out: '+expression);}
async function change(name,value){await evaluate(`const f=document.querySelector('[data-sales-form]').elements[${JSON.stringify(name)}];f.value=${JSON.stringify(String(value))};f.dispatchEvent(new Event('change',{bubbles:true}));`);}
async function updated(action){const n=requests.length;await action();for(let i=0;i<100&&requests.length===n;i++)await pause(40);assert.ok(requests.length>n,'Automatic fetch');await until(`document.querySelector('[data-admin-sales-reports]').dataset.state==='ready'`);}
async function clear(){await updated(()=>evaluate(`document.querySelector('[data-sales-clear-control]').click()`));}
async function search(value){await evaluate(`const f=document.querySelector('[name="q"]');f.focus();f.value=${JSON.stringify(value)};f.dispatchEvent(new Event('input',{bubbles:true}));`);}
try{
    await cdp('Page.enable');await cdp('Runtime.enable');
    await cdp('Emulation.setEmulatedMedia',{media:'screen'});
    await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await cdp('Page.navigate',{url:base+'/admin/sales-reports'});
    await until(`document.querySelector('[data-admin-sales-reports]')?.dataset.enhanced==='true'`);
    assert.equal(await evaluate(`document.querySelectorAll('[data-shop-id]').length`),15);
    await updated(()=>change('range','week'));
    assert.ok(await evaluate(`document.querySelector('[data-sales-summary]').innerText.includes('₱200.00')`));
    await updated(()=>change('shop','1'));
    assert.equal(await evaluate(`document.querySelectorAll('[data-shop-id]').length`),1);
    const exports=await evaluate(`Array.from(document.querySelectorAll('[data-report-export]')).map(a=>a.href)`);
    for(const link of exports){assert.equal(new URL(link).searchParams.get('shop'),'1');assert.equal(new URL(link).searchParams.get('range'),'week');}
    const detail=await evaluate(`document.querySelector('[data-shop-id] a').href`);
    assert.equal(new URL(detail).pathname,'/admin/shops/1/sales-report');assert.equal(new URL(detail).searchParams.get('range'),'week');
    console.log('PASS automatic shop/date filters update totals, URL, detail and export links');

    const downloads=resolve('storage/app/sales-overview-downloads-'+Date.now());mkdirSync(downloads,{recursive:true});
    await cdp('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:downloads});
    await evaluate(`document.querySelector('[data-sales-exports] summary').click()`);
    for(const format of ['pdf','csv']){
        await evaluate(`document.querySelector('[data-report-export="${format}"]').click()`);
        const file=`SHOPPICK_Seller_Sales_Report_2026-10-05_to_2026-10-11.${format}`;
        for(let i=0;i<100&&!readdirSync(downloads).includes(file);i++)await pause(80);
        assert.deepEqual(readFileSync(downloads+'/'+file),readFileSync(fixtures+'report.'+format));
    }
    console.log('PASS real PDF and CSV files download from the filtered overview');

    await clear();await updated(()=>change('range','week'));await updated(()=>change('performance','Improving'));
    assert.equal(await evaluate(`document.querySelectorAll('[data-shop-id]').length`),1);
    await clear();await updated(()=>change('shop_status','suspended'));
    assert.ok(await evaluate(`document.querySelector('[data-sales-table]').innerText.includes('Tech Corner')`));
    await clear();await updated(()=>change('sort','name'));
    assert.ok(await evaluate(`document.querySelector('[data-shop-id]').innerText.includes('Extra 00')`));
    console.log('PASS performance, shop status and sort refresh automatically');

    await clear();const before=requests.length;await search('Panda');await pause(180);assert.equal(requests.length,before);
    await until(`location.search.includes('q=Panda') && document.querySelector('[data-admin-sales-reports]').dataset.state==='ready'`);
    assert.equal(requests.length,before+1);assert.equal(await evaluate(`document.activeElement.name`),'q');
    await updated(()=>search('missing'));
    assert.ok(await evaluate(`document.querySelector('[data-sales-table]').innerText.includes('No shop sales match your current filters.')`));
    await clear();console.log('PASS search debounces, preserves focus and shows the empty state');

    const count=requests.length;await change('range','custom');await pause(120);assert.equal(requests.length,count);
    await change('from','2026-10-05');await pause(100);assert.equal(requests.length,count);
    await updated(()=>change('to','2026-10-06'));assert.ok(await evaluate(`document.querySelector('[data-sales-summary]').innerText.includes('₱200.00')`));
    await change('to','2026-10-01');await pause(120);assert.equal(requests.length,count+1);
    assert.ok(await evaluate(`document.querySelector('[data-sales-error]').innerText.includes('valid date range')`));
    await clear();console.log('PASS custom dates wait for a complete valid inclusive range');

    await updated(()=>evaluate(`document.querySelector('[data-sales-pagination] a[rel="next"]').click()`));
    assert.equal(await evaluate(`document.querySelectorAll('[data-shop-id]').length`),4);
    assert.ok(await evaluate(`location.search.includes('page=2')`));
    await updated(()=>evaluate(`history.back()`));assert.equal(await evaluate(`document.querySelectorAll('[data-shop-id]').length`),15);
    await updated(()=>evaluate(`history.forward()`));assert.equal(await evaluate(`document.querySelectorAll('[data-shop-id]').length`),4);
    await clear();console.log('PASS pagination, Back and Forward restore report state');

    failNext=true;await change('range','today');await until(`document.querySelector('[data-admin-sales-reports]').dataset.state==='error'`);
    assert.equal(await evaluate(`document.querySelector('[data-sales-retry]').hidden`),false);
    await updated(()=>evaluate(`document.querySelector('[data-sales-retry]').click()`));
    assert.ok(await evaluate(`document.querySelector('[data-sales-summary]').innerText.includes('₱0.00')`));
    await clear();delayPanda=true;await search('Panda');await pause(500);await updated(()=>search('missing'));await pause(950);
    assert.ok(await evaluate(`location.search.includes('q=missing') && document.querySelector('[data-sales-table]').innerText.includes('No shop sales match')`));
    delayPanda=false;await clear();console.log('PASS retry recovers and slow stale responses cannot replace newer results');

    const screenshot=await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});writeFileSync(fixtures+'overview-preview.png',Buffer.from(screenshot.data,'base64'));
    await cdp('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await evaluate(`document.querySelector('button[onclick]').click()`);
    assert.ok(await evaluate(`Array.from(document.querySelectorAll('#mobile-nav a')).some(a=>a.innerText==='Sales Reports' && a.className.includes('bg-brand-500/20'))`));
    assert.ok(await evaluate(`document.documentElement.scrollWidth <= 390`));
    console.log('PASS desktop and mobile navigation highlight Sales Reports without page overflow');

    await cdp('Page.addScriptToEvaluateOnNewDocument',{source:'window.print=()=>{window.printRequested=true}'});
    await cdp('Page.navigate',{url:exports.find(url=>url.includes('/print?'))});
    await until(`window.printRequested===true`);
    assert.equal(await evaluate(`document.querySelector('aside,nav,button,form')!==null`),false);
    assert.ok(await evaluate(`document.body.innerText.includes('Panda Picks') && !document.body.innerText.includes('Tech Corner') && document.body.innerText.includes('₱150.00')`));
    await cdp('Emulation.setDeviceMetricsOverride',{width:794,height:1123,deviceScaleFactor:1,mobile:false});await cdp('Emulation.setEmulatedMedia',{media:'print'});
    writeFileSync(fixtures+'print-preview.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));
    assert.deepEqual(errors,[]);assert.equal(writes,0);
    console.log('PASS filtered A4 print document requests printing with matching totals and no admin controls');
}finally{ws.close();server.closeAllConnections();await new Promise(r=>server.close(r));}
