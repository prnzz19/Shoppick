// Opt-in AdminSalesAnalyticsTest fixtures; isolated Edge CDP on 9227. No live database.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
const base='http://127.0.0.1:8132',dir='storage/app/analytics-fixtures/',out='storage/app/analytics-previews/';mkdirSync(out,{recursive:true});
const ids=JSON.parse(readFileSync(dir+'ids.json','utf8'));
const signature=(path,params)=>path+'?'+JSON.stringify([...params].filter(([k])=>k!=='sort'||params.get(k)!=='sales_desc').sort(([a],[b])=>a.localeCompare(b)));
const raw=JSON.parse(JSON.stringify(JSON.parse(readFileSync(dir+'responses.json','utf8'))).replaceAll('http://127.0.0.1:8000',base));
const responses=new Map(Object.entries(raw).map(([key,data])=>{const u=new URL(key,base+'/');return [signature(u.pathname.slice(1),u.searchParams),data];}));
let failNext=false,delayNext=false,writes=0;const requests=[];
const server=createServer((req,res)=>{
 const u=new URL(req.url,base);if(req.method!=='GET'){writes++;res.writeHead(405).end();return;}
 if(['/admin/analytics','/admin/dashboard'].includes(u.pathname)){
  const page=u.pathname.split('/').at(-1);
  if(req.headers.accept?.includes('application/json')){
   requests.push(Object.fromEntries(u.searchParams));if(failNext){failNext=false;res.writeHead(503).end();return;}
   const data=responses.get(signature(page,u.searchParams));if(!data){console.log('Missing fixture',u.search,signature(page,u.searchParams));res.writeHead(422).end(JSON.stringify({missing:u.search}));return;}
   const delay=delayNext?800:30;delayNext=false;setTimeout(()=>res.writeHead(200,{'Content-Type':'application/json'}).end(JSON.stringify(data)),delay);
  }else{const file=page==='dashboard'?'dashboard-'+(u.searchParams.get('range')||'week')+'.html':'analytics-'+(u.searchParams.get('preview')||'0')+'.html';res.writeHead(200,{'Content-Type':'text/html'}).end(readFileSync(dir+file,'utf8').replaceAll('http://127.0.0.1:8000',base).replace(/<script src="https:\/\/cdn\.jsdelivr[^>]*><\/script>/,''));}
 }else if(u.pathname==='/admin/analytics/print'){res.writeHead(200,{'Content-Type':'text/html'}).end(readFileSync(dir+'print.html'));}
 else if(/^\/build\/assets\/[\w.-]+$/.test(u.pathname)){try{res.writeHead(200,{'Content-Type':u.pathname.endsWith('.css')?'text/css':'text/javascript'}).end(readFileSync('public'+u.pathname));}catch{res.writeHead(404).end();}}
 else res.writeHead(404).end();
});await new Promise(r=>server.listen(8132,'127.0.0.1',r));
const targets=await(await fetch('http://127.0.0.1:9227/json/list')).json();const ws=new WebSocket(targets.find(t=>t.type==='page'&&!t.url.startsWith('edge://')).webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
let id=0;const calls=new Map(),errors=[];ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);if(m.id){const pair=calls.get(m.id);calls.delete(m.id);m.error?pair.reject(m.error):pair.resolve(m.result);}};
const cdp=(method,params={})=>new Promise((resolve,reject)=>{const key=++id;calls.set(key,{resolve,reject});ws.send(JSON.stringify({id:key,method,params}));});
const pause=ms=>new Promise(r=>setTimeout(r,ms));const evaluate=async expression=>(await cdp('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result.value;
async function until(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await pause(50);}throw new Error('Timed out: '+expression);}
async function navigate(page='analytics',query='range=week'){await cdp('Page.navigate',{url:base+'/admin/'+page+'?'+query});await until(`document.querySelector('[data-admin-analytics]') && document.readyState==='complete'`);}
async function change(name,value){await evaluate(`(()=>{const f=document.querySelector('[data-analytics-form]').elements[${JSON.stringify(name)}];f.value=${JSON.stringify(value)};f.dispatchEvent(new Event('change',{bubbles:true}));})()`);}
async function updated(action){const n=requests.length;await action();for(let i=0;i<100&&requests.length===n;i++)await pause(50);assert.ok(requests.length>n,'Automatic GET request');await until(`document.querySelector('[data-analytics-content]').getAttribute('aria-busy')==='false'`);assert.equal(await evaluate(`document.querySelector('[data-analytics-error]').hidden`),true);}
const sales=()=>evaluate(`document.querySelector('.report-value').innerText`);
try{
 await cdp('Page.enable');await cdp('Runtime.enable');await cdp('Emulation.setEmulatedMedia',{media:'screen'});await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
 await navigate();assert.equal(await sales(),'₱200.00');assert.equal(await evaluate(`document.querySelectorAll('.report-metric').length`),4);assert.equal(await evaluate(`document.querySelectorAll('.analytics-chart circle').length`),7);
 await updated(()=>change('range','30days'));assert.equal(await sales(),'₱400.00');await updated(()=>change('range','month'));assert.equal(await sales(),'₱400.00');assert.ok(await evaluate(`location.search.includes('range=month')`));
 await updated(()=>evaluate('history.back()'));assert.ok(await evaluate(`document.querySelector('[name=range]').value==='30days'`));await updated(()=>evaluate('history.forward()'));console.log('PASS period auto-filter, metrics, chart, Back and Forward');
 await navigate();await updated(()=>change('shop',String(ids.tech)));assert.equal(await sales(),'₱80.00');await navigate();await updated(()=>change('category',String(ids.beauty)));assert.equal(await sales(),'₱30.00');
 const exports=await evaluate(`Array.from(document.querySelectorAll('[data-report-export]')).map(a=>a.href)`);assert.equal(exports.length,3);for(const url of exports)assert.equal(new URL(url).searchParams.get('category'),String(ids.beauty));
 await navigate();await updated(()=>change('status','cancelled'));assert.equal(await sales(),'₱0.00');assert.ok(await evaluate(`document.querySelector('[data-analytics-content]').innerText.includes('No completed sales were recorded')`));console.log('PASS shop/category/status filters and matching exports');
 await navigate();await updated(()=>evaluate(`document.querySelector('[data-analytics-pagination] a[rel=next]').click()`));assert.ok(await evaluate(`location.search.includes('page=2')`));
 await navigate();await updated(()=>evaluate(`const f=document.querySelector('[data-analytics-sort]');f.value='sales_asc';f.dispatchEvent(new Event('change',{bubbles:true}));`));assert.ok(await evaluate(`location.search.includes('sort=sales_asc')`));
 await navigate();await evaluate(`const f=document.querySelector('[data-analytics-form]');f.elements.from.value='2026-10-06';f.elements.to.value='2026-10-06';`);await updated(()=>change('range','custom'));assert.equal(await sales(),'₱200.00');console.log('PASS pagination, sort, custom inclusive dates');
 await navigate();failNext=true;await change('range','today');await until(`!document.querySelector('[data-analytics-error]').hidden`);await updated(()=>evaluate(`document.querySelector('[data-analytics-retry]').click()`));assert.equal(await sales(),'₱0.00');
 await navigate();delayNext=true;await change('range','30days');await pause(50);await updated(()=>change('range','today'));await pause(850);assert.equal(await sales(),'₱0.00');assert.ok(await evaluate(`location.search.includes('range=today')`));console.log('PASS retry and stale response protection');
 for(const page of ['dashboard','analytics'])for(const [size,width,height] of [['desktop',1440,1100],['tablet',1024,1000],['mobile',390,844]]){
  await cdp('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:size==='mobile'});await navigate(page);assert.ok(await evaluate(`document.documentElement.scrollWidth<=${width}`),page+' overflow at '+size);
  if(page==='dashboard'){assert.equal(await sales(),'₱200.00');await updated(()=>change('range','30days'));assert.equal(await sales(),'₱400.00');await updated(()=>change('range','month'));assert.equal(await sales(),'₱400.00');await navigate(page);}
  writeFileSync(out+page+'-'+size+'.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));
 }console.log('PASS Dashboard and Analytics desktop/tablet/mobile, week/30 days/month');
 await cdp('Page.navigate',{url:base+'/admin/analytics/print?range=week'});await until(`document.querySelector('h1')?.innerText==='SHOPPICK'`);await cdp('Emulation.setEmulatedMedia',{media:'print'});assert.equal(await evaluate(`getComputedStyle(document.querySelector('.toolbar')).display`),'none');writeFileSync(out+'analytics-print.pdf',Buffer.from((await cdp('Page.printToPDF',{printBackground:true,paperWidth:8.27,paperHeight:11.69})).data,'base64'));
 assert.deepEqual(errors,[]);assert.equal(writes,0);console.log('PASS print layout, no JS errors or writes');
}finally{ws.close();server.closeAllConnections();await new Promise(r=>server.close(r));}
