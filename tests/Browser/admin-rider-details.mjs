// Run opt-in AdminRiderDetailsTest fixtures first. Uses isolated headless Edge on 9227.
// Serves Laravel-generated SQLite fixtures and compiled assets, with no live DB connection.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
const base='http://127.0.0.1:8131',dir='storage/app/rider-detail-fixtures/',out='storage/app/rider-detail-previews/';mkdirSync(out,{recursive:true});
const riders=JSON.parse(readFileSync(dir+'riders.json','utf8')),carlo=riders['rider-carlo'];
const signature=p=>JSON.stringify([...p].sort(([a],[b])=>a.localeCompare(b)));
const raw=JSON.parse(readFileSync(dir+'responses.json','utf8').replaceAll('http://127.0.0.1:8000',base));
const responses=new Map(Object.entries(raw).map(([q,data])=>[signature(new URLSearchParams(q)),data]));
let failNext=false,delaySearch=false,writes=0;const requests=[];
const server=createServer((req,res)=>{
 const url=new URL(req.url,base);if(req.method!=='GET'){writes++;res.writeHead(405).end();return;}
 if(/^\/admin\/logistics\/riders\/\d+$/.test(url.pathname)){
  if(req.headers.accept?.includes('application/json')){
   requests.push(Object.fromEntries(url.searchParams));if(failNext){failNext=false;res.writeHead(503).end();return;}
   const data=responses.get(signature(url.searchParams));if(!data){res.writeHead(422).end(JSON.stringify({missing:Object.fromEntries(url.searchParams)}));return;}
   setTimeout(()=>res.writeHead(200,{'Content-Type':'application/json'}).end(JSON.stringify(data)),delaySearch&&url.searchParams.get('q')==='RIDER-TRACK'?900:30);
  }else{const key=url.searchParams.get('preview')??'busy';const html=readFileSync(dir+key+'.html','utf8').replaceAll('http://127.0.0.1:8000',base).replace(/<script src="https:\/\/cdn\.jsdelivr[^>]*><\/script>/,'');res.writeHead(200,{'Content-Type':'text/html'}).end(html);}
 }else if(/^\/build\/assets\/[\w.-]+$/.test(url.pathname)){try{res.writeHead(200,{'Content-Type':url.pathname.endsWith('.css')?'text/css':'text/javascript'}).end(readFileSync('public'+url.pathname));}catch{res.writeHead(404).end();}}
 else res.writeHead(404).end();
});await new Promise(r=>server.listen(8131,'127.0.0.1',r));
const targets=await(await fetch('http://127.0.0.1:9227/json/list')).json();const ws=new WebSocket(targets.find(t=>t.type==='page'&&!t.url.startsWith('edge://')).webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
let id=0;const calls=new Map(),errors=[];ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);const p=calls.get(m.id);if(p){calls.delete(m.id);m.error?p.reject(new Error(m.error.message)):p.resolve(m.result);}};
const cdp=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;calls.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});
async function evaluate(expression){const r=await cdp('Runtime.evaluate',{expression:`{ ${expression} }`,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description??r.exceptionDetails.text);return r.result.value;}
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function until(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await pause(80);}throw new Error('Timed out: '+expression);}
async function navigate(key='busy'){await cdp('Page.navigate',{url:base+'/admin/logistics/riders/'+carlo+'?preview='+key});await pause(150);await until(`document.querySelector('[data-rider-details]')?.dataset.state==='ready' && document.readyState==='complete'`);}
async function updated(action){const n=requests.length;await action();for(let i=0;i<100&&requests.length===n;i++)await pause(40);assert.ok(requests.length>n,'Automatic request');await until(`document.querySelector('[data-rider-details]').dataset.state==='ready'`);}
async function status(value){await evaluate(`const f=document.querySelector('[name="status"]');f.value=${JSON.stringify(value)};f.dispatchEvent(new Event('change',{bubbles:true}));`);}
async function search(value){await evaluate(`const f=document.querySelector('[name="q"]');f.focus();f.value=${JSON.stringify(value)};f.dispatchEvent(new Event('input',{bubbles:true}));`);}
try{
 await cdp('Page.enable');await cdp('Runtime.enable');await cdp('Emulation.setEmulatedMedia',{media:'screen'});await cdp('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
 await navigate();assert.equal(await evaluate(`document.querySelectorAll('[data-rider-shipments] tbody tr').length`),10);assert.equal(await evaluate(`document.querySelectorAll('.rd-event').length`),10);
 await updated(()=>status('delivered'));assert.ok(await evaluate(`Array.from(document.querySelectorAll('[data-rider-shipments] [data-status]')).every(x=>x.dataset.status==='delivered')`));assert.ok(await evaluate(`location.search.includes('status=delivered')`));
 await updated(()=>evaluate(`document.querySelector('[data-rider-clear]').click()`));
 console.log('PASS status auto-filter, clear and 10-row shipment/event limits');
 const count=requests.length;await search('RIDER-TRACK');await pause(180);assert.equal(requests.length,count);await until(`location.search.includes('q=RIDER-TRACK') && document.querySelector('[data-rider-details]').dataset.state==='ready'`);assert.equal(requests.length,count+1);assert.equal(await evaluate(`document.activeElement.name`),'q');
 await updated(()=>search('missing'));assert.ok(await evaluate(`document.querySelector('[data-rider-shipments]').innerText.includes('No assigned shipments match your filters.')`));await updated(()=>evaluate(`document.querySelector('[data-rider-clear]').click()`));console.log('PASS 400 ms search debounce, retained focus and filtered empty state');
 await updated(()=>evaluate(`document.querySelector('[data-rider-activity] a[rel="next"]').click()`));assert.ok(await evaluate(`location.search.includes('activity_page=2')`));
 await updated(()=>status('delivered'));await updated(()=>evaluate(`document.querySelector('[data-rider-shipments] a[rel="next"]').click()`));assert.ok(await evaluate(`location.search.includes('page=2') && location.search.includes('activity_page=2') && location.search.includes('status=delivered')`));
 await updated(()=>evaluate(`history.back()`));assert.ok(await evaluate(`!new URLSearchParams(location.search).has('page') && new URLSearchParams(location.search).get('activity_page')==='2'`));await updated(()=>evaluate(`history.forward()`));
 console.log('PASS independent pagination preserves status, rider context, Back and Forward');
 await navigate();failNext=true;await status('delivered');await until(`document.querySelector('[data-rider-details]').dataset.state==='error'`);assert.equal(await evaluate(`document.querySelector('[data-rider-retry]').hidden`),false);await updated(()=>evaluate(`document.querySelector('[data-rider-retry]').click()`));
 await updated(()=>evaluate(`document.querySelector('[data-rider-clear]').click()`));delaySearch=true;await search('RIDER-TRACK');await pause(500);await updated(()=>search('missing'));await pause(950);assert.ok(await evaluate(`location.search.includes('q=missing') && document.querySelector('[data-rider-shipments]').innerText.includes('No assigned shipments match')`));delaySearch=false;console.log('PASS retry and stale-request protection');
 for(const [key,riderId] of Object.entries(riders)){
  for(const [size,width,height] of [['desktop',1440,1100],['tablet',1024,1000],['mobile',390,844]]){
   await cdp('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:size==='mobile'});await cdp('Page.navigate',{url:base+'/admin/logistics/riders/'+riderId+'?preview='+key});await pause(150);await until(`document.querySelector('[data-rider-details]')?.dataset.state==='ready' && document.readyState==='complete'`);
   assert.ok(await evaluate(`document.documentElement.scrollWidth<=${width}`),key+' has no page overflow');assert.equal(await evaluate(`document.querySelectorAll('.rd-metric').length`),4);assert.ok(await evaluate(`document.querySelector('h1').innerText===${JSON.stringify(key==='new-rider'?'New Rider':key.split('-').map(s=>s[0].toUpperCase()+s.slice(1)).join(' '))}`));
   const actions=await evaluate(`Array.from(document.querySelectorAll('.rd-action[href]')).map(a=>a.href)`);for(const url of actions)assert.ok(new URL(url).pathname.startsWith('/admin/logistics/deliveries/'));
   if(key==='new-rider')assert.ok(await evaluate(`document.body.innerText.includes('No active delivery') && document.body.innerText.includes('No shipments have been assigned') && document.body.innerText.includes('No delivery activity has been recorded')`));
   writeFileSync(out+key+'-'+size+'.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));
  }
  console.log('PASS '+key+' shared template at desktop/tablet/mobile, correct details and no overflow');
 }
 assert.deepEqual(errors,[]);assert.equal(writes,0);
}finally{ws.close();server.closeAllConnections();await new Promise(r=>server.close(r));}
