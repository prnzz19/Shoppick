// Export SQLite fixtures first with SHOPPICK_REPORT_BROWSER_FIXTURES=1 and the focused feature test.
// Uses an isolated browser on debugging port 9226. Never writes to a live database.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFileSync,writeFileSync,mkdirSync,readdirSync} from 'node:fs';
import {resolve} from 'node:path';

const base='http://127.0.0.1:8128';
const fixtures='storage/app/shop-report-fixtures/';
const filename='SHOPPICK_panda-picks_Sales_Report_2026-10-05_to_2026-10-11';
const downloads=resolve('storage/app/shop-report-downloads-'+Date.now());
mkdirSync(downloads,{recursive:true});
const server=createServer((req,res)=>{
    const url=new URL(req.url,base);
    if(req.method!=='GET'){res.writeHead(405).end();return;}
    if(/^\/admin\/shops\/\d+\/sales-report(?:\/(pdf|csv|print))?$/.test(url.pathname)){
        const format=url.pathname.split('/').at(-1);
        if(format==='pdf'||format==='csv'){
            res.writeHead(200,{'Content-Type':format==='pdf'?'application/pdf':'text/csv; charset=UTF-8','Content-Disposition':`attachment; filename="${filename}.${format}"`}).end(readFileSync(fixtures+'report.'+format));
        }else{
            const html=readFileSync(fixtures+(format==='print'?'print.html':'web.html'),'utf8').replaceAll('http://127.0.0.1:8000',base).replace(/<script src="https:\/\/cdn\.jsdelivr[^>]*><\/script>/,'');
            res.writeHead(200,{'Content-Type':'text/html'}).end(html);
        }
    }else if(/^\/build\/assets\/[a-zA-Z0-9_.-]+$/.test(url.pathname)){
        try{res.writeHead(200,{'Content-Type':url.pathname.endsWith('.css')?'text/css':'text/javascript'}).end(readFileSync('public'+url.pathname));}catch{res.writeHead(404).end();}
    }else res.writeHead(404).end();
});
await new Promise(r=>server.listen(8128,'127.0.0.1',r));
const targets=await(await fetch('http://127.0.0.1:9226/json/list')).json();
const ws=new WebSocket(targets.find(t=>t.type==='page' && !t.url.startsWith('edge://')).webSocketDebuggerUrl);
await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
let nextId=0;const pending=new Map();const errors=[];
ws.onmessage=e=>{
    const m=JSON.parse(e.data);
    if(m.method==='Runtime.exceptionThrown') errors.push(m.params.exceptionDetails.text);
    const call=pending.get(m.id);if(call){pending.delete(m.id);m.error?call.reject(new Error(m.error.message)):call.resolve(m.result);}
};
function cdp(method,params={}){return new Promise((resolve,reject)=>{const id=++nextId;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression){const r=await cdp('Runtime.evaluate',{expression:`{ ${expression} }`,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;}
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function until(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await pause(100);}throw new Error('Timed out: '+expression);}
async function download(format){
    await evaluate(`document.querySelector('[data-report-export="${format}"]').click()`);
    const name=filename+'.'+format;
    for(let i=0;i<100&&!readdirSync(downloads).includes(name);i++)await pause(100);
    assert.ok(readdirSync(downloads).includes(name),'Download completed: '+format);
    assert.deepEqual(readFileSync(downloads+'/'+name),readFileSync(fixtures+'report.'+format));
}
try{
    await cdp('Page.enable');await cdp('Runtime.enable');
    await cdp('Emulation.setEmulatedMedia',{media:'screen'});
    await cdp('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:downloads});
    await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await cdp('Page.navigate',{url:base+'/admin/shops/1/sales-report?range=week'});
    await until(`document.querySelector('[data-shop-report-filters]') && document.readyState==='complete'`);
    assert.equal(await evaluate(`document.querySelector('[name="range"]').value`),'week');
    const links=await evaluate(`Array.from(document.querySelectorAll('[data-report-export]')).map(a=>a.href)`);
    assert.equal(links.length,3);for(const link of links)assert.equal(new URL(link).searchParams.get('range'),'week');
    await evaluate(`document.querySelector('details summary').click()`);
    await download('pdf');
    assert.ok(readFileSync(downloads+'/'+filename+'.pdf').subarray(0,5).equals(Buffer.from('%PDF-')));
    await download('csv');
    assert.ok(readFileSync(downloads+'/'+filename+'.csv','utf8').includes('María Dela Cruz'));
    console.log('PASS PDF and UTF-8 CSV downloaded through the report header with the selected week and meaningful filenames');
    await cdp('Page.addScriptToEvaluateOnNewDocument',{source:'window.print=()=>{window.reportPrintRequested=true}'});
    await cdp('Page.navigate',{url:links.find(link=>link.includes('/print?'))});
    await until(`window.reportPrintRequested===true`);
    assert.equal(await evaluate(`document.querySelector('aside,button,nav,form')!==null`),false);
    assert.ok(await evaluate(`document.body.innerText.includes('Oct 05, 2026 – Oct 11, 2026')`));
    assert.ok(await evaluate(`document.body.innerText.includes('₱150.00') && document.body.innerText.includes('Performance: Improving')`));
    await cdp('Emulation.setDeviceMetricsOverride',{width:794,height:1123,deviceScaleFactor:1,mobile:false});
    await cdp('Emulation.setEmulatedMedia',{media:'print'});
    const screenshot=await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
    writeFileSync(fixtures+'print-preview.png',Buffer.from(screenshot.data,'base64'));
    const printed=await cdp('Page.printToPDF',{printBackground:true,preferCSSPageSize:true});
    writeFileSync(fixtures+'browser-print-preview.pdf',Buffer.from(printed.data,'base64'));
    assert.deepEqual(errors,[]);
    console.log('PASS print action requests printing; A4 document contains current totals/trends without Admin navigation or controls');
}finally{
    ws.close();server.closeAllConnections();await new Promise(r=>server.close(r));
}
