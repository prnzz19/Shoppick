// Rendered Laravel fixtures use only the opt-in RiderPortalTest in-memory database.
// Headless Edge CDP on 9227; this local server records submissions and never connects to SHOPPICK's DB.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
const base='http://127.0.0.1:8133',dir='storage/app/rider-portal-fixtures/',out='storage/app/rider-portal-previews/';
mkdirSync(out,{recursive:true});const writes=[];
const server=createServer(async(req,res)=>{
 const url=new URL(req.url,base);
 if(req.method==='POST'){let body='';for await(const chunk of req)body+=chunk;writes.push({path:url.pathname,body});res.writeHead(204).end();return;}
 if(url.pathname.startsWith('/preview/')){const key=url.pathname.split('/').at(-1);if(!/^[a-z]+$/.test(key)){res.writeHead(404).end();return;}try{res.writeHead(200,{'Content-Type':'text/html'}).end(readFileSync(dir+key+'.html','utf8').replace(/https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?/g,base));}catch{res.writeHead(404).end();}return;}
 if(/^\/build\/assets\/[\w.-]+$/.test(url.pathname)){try{res.writeHead(200,{'Content-Type':url.pathname.endsWith('.css')?'text/css':'text/javascript'}).end(readFileSync('public'+url.pathname));}catch{res.writeHead(404).end();}return;}
 res.writeHead(200,{'Content-Type':'text/html'}).end('<h1>Fixture navigation target</h1>');
});
await new Promise(r=>server.listen(8133,'127.0.0.1',r));
const targets=await(await fetch('http://127.0.0.1:9227/json/list')).json();
const ws=new WebSocket(targets.find(t=>t.type==='page'&&!t.url.startsWith('edge://')).webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
let id=0;const calls=new Map(),errors=[];ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);const pending=calls.get(m.id);if(pending){calls.delete(m.id);m.error?pending.reject(new Error(m.error.message)):pending.resolve(m.result);}};
const cdp=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;calls.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});
async function evaluate(expression){const r=await cdp('Runtime.evaluate',{expression:`{ ${expression} }`,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description??r.exceptionDetails.text);return r.result.value;}
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function until(expression){for(let i=0;i<70;i++){if(await evaluate(expression))return;await pause(80);}throw new Error('Timed out: '+expression);}
async function navigate(key){await cdp('Page.navigate',{url:base+'/preview/'+key});await until(`document.readyState==='complete' && location.pathname==='/preview/${key}'`);await pause(100);}
try{
 await cdp('Page.enable');await cdp('Runtime.enable');await cdp('Emulation.setEmulatedMedia',{media:'screen'});
 for(const [size,width,height] of [['desktop',1440,1000],['tablet',768,1024],['mobile',390,844]]){
  await cdp('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:size==='mobile'});
  for(const key of ['login','dashboard','jobs','detail','ready','empty','profile']){
   await navigate(key);assert.ok(await evaluate(`document.documentElement.scrollWidth<=${width}`),key+' no page overflow');
   if(key==='jobs')assert.equal(await evaluate(`getComputedStyle(document.querySelector('table').parentElement).display!=='none'`),width>=1024);
   if(key==='empty')assert.ok(await evaluate(`document.body.innerText.includes('No Active Delivery')`));
   writeFileSync(out+key+'-'+size+'.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));
  }
  console.log('PASS responsive '+size+' login, dashboard, list, details, empty state, profile');
 }
 await navigate('login');await evaluate(`document.querySelector('[data-password-toggle]').click()`);assert.equal(await evaluate(`document.getElementById('rider-password').type`),'text');await evaluate(`document.querySelector('[data-password-toggle]').click()`);assert.equal(await evaluate(`document.getElementById('rider-password').type`),'password');console.log('PASS password visibility toggle');
 await navigate('detail');await evaluate(`document.querySelector('a[href$="/rider/jobs"]').click()`);await until(`location.pathname==='/rider/jobs'`);console.log('PASS unchanged POD form does not trigger unsaved warning');
 await navigate('detail');await evaluate(`document.querySelector('[name="recipient_name"]').value='Unsaved Recipient';document.querySelector('a[href$="/rider/jobs"]').click()`);assert.ok(await evaluate(`document.getElementById('rider-confirm').open`));assert.equal(await evaluate(`document.getElementById('rider-confirm-title').innerText`),'You have unsaved changes. Leave this page?');await evaluate(`document.querySelector('[data-rider-cancel]').click()`);assert.ok(await evaluate(`location.pathname==='/preview/detail' && document.querySelector('[name="recipient_name"]').value==='Unsaved Recipient'`));await evaluate(`document.querySelector('a[href$="/rider/jobs"]').click();document.querySelector('[data-rider-accept]').click()`);await until(`location.pathname==='/rider/jobs'`);console.log('PASS unsaved changes Stay and Leave');
 await navigate('ready');const before=writes.length;await evaluate(`document.querySelector('[data-rider-confirm="delivered"] button').click()`);assert.equal(writes.length,before);assert.ok(await evaluate(`document.getElementById('rider-confirm').open && document.getElementById('rider-confirm-message').innerText.includes('correct recipient')`));assert.equal(await evaluate(`document.activeElement.hasAttribute('data-rider-cancel')`),true);
 await cdp('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await cdp('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await until(`!document.getElementById('rider-confirm').open`);assert.equal(writes.length,before);
 await evaluate(`document.querySelector('[data-rider-confirm="delivered"] button').click();document.querySelector('[data-rider-accept]').click();document.querySelector('[data-rider-confirm="delivered"]').requestSubmit();`);await pause(250);assert.equal(writes.length,before+1);assert.ok(writes.at(-1).body.includes('action=delivered'));assert.ok(writes.at(-1).body.includes('_token='));assert.equal(await evaluate(`document.querySelector('[data-rider-confirm="delivered"] button').disabled`),true);console.log('PASS delivered warning, keyboard cancellation, CSRF field and double-submit protection');
 await navigate('detail');await evaluate(`document.querySelector('[data-rider-open="rider-problem"]').click()`);assert.ok(await evaluate(`document.getElementById('rider-problem').open`));await evaluate(`const reason=document.querySelector('#rider-problem [name="reason"]');reason.value='other';reason.dispatchEvent(new Event('change'));`);assert.equal(await evaluate(`document.querySelector('#rider-problem [name="details"]').required`),true);await evaluate(`document.querySelector('#rider-problem [name="details"]').value='Actual delivery issue';document.querySelector('#rider-problem [data-rider-close]').click()`);assert.ok(await evaluate(`document.getElementById('rider-confirm').open`));await evaluate(`document.querySelector('[data-rider-cancel]').click()`);assert.ok(await evaluate(`document.getElementById('rider-problem').open`));
 await evaluate(`document.querySelector('#rider-problem button:not([type])').click()`);assert.equal(await evaluate(`document.getElementById('rider-confirm-title').innerText`),'Submit Delivery Problem?');const problemBefore=writes.length;await evaluate(`document.querySelector('[data-rider-accept]').click()`);await pause(250);assert.equal(writes.length,problemBefore+1);assert.ok(writes.at(-1).body.includes('reason=other'));console.log('PASS problem modal, Other notes validation, discard warning and confirmed report');
 await navigate('empty');await evaluate(`document.querySelector('[data-rider-menu]').click()`);assert.equal(await evaluate(`document.getElementById('rider-menu').checked`),true);await evaluate(`document.querySelector('[data-rider-confirm="logout"] button').click()`);assert.equal(await evaluate(`document.getElementById('rider-confirm-title').innerText`),'Log out of Rider Portal?');await evaluate(`document.querySelector('[data-rider-cancel]').click()`);assert.ok(await evaluate(`!document.getElementById('rider-confirm').open`));console.log('PASS mobile navigation and logout Stay Logged In');
 assert.deepEqual(errors,[]);console.log('PASS no JavaScript runtime errors; all submissions remained on isolated fixture server');
}finally{ws.close();server.closeAllConnections();await new Promise(r=>server.close(r));}
