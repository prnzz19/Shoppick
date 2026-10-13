// Rendered Laravel fixtures from SQLite :memory:. This server records POSTs and never writes live accounts.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
const base='http://127.0.0.1:8135',dir='storage/app/rider-registration-fixtures/',out='storage/app/rider-registration-previews/',writes=[];
mkdirSync(out,{recursive:true});let locationsUnavailable=false;
const server=createServer(async(req,res)=>{
 const path=new URL(req.url,base).pathname;
 if(req.method==='POST'){let body='';for await(const chunk of req)body+=chunk;writes.push({path,body});res.writeHead(204).end();return;}
 if(path.startsWith('/preview/')){const key=path.split('/').at(-1);if(!/^[a-z]+$/.test(key)){res.writeHead(404).end();return;}res.writeHead(200,{'Content-Type':'text/html'}).end(readFileSync(dir+key+'.html','utf8').replace(/https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?/g,base));return;}
 if(/^\/build\/assets\/[\w.-]+$/.test(path)){res.writeHead(200,{'Content-Type':path.endsWith('.css')?'text/css':'text/javascript'}).end(readFileSync('public'+path));return;}
 if(path.includes('philippine-locations')){
  if(locationsUnavailable){res.writeHead(503,{'Content-Type':'application/json'}).end('{}');return;}
  const row=path.endsWith('/regions')?['0400000000','Region IV-A (CALABARZON)']:path.endsWith('/provinces')?['0403400000','Laguna']:path.endsWith('/cities-municipalities')?['0403407000','Cavinti']:['0403407012','Mahipon'];res.writeHead(200,{'Content-Type':'application/json'}).end(JSON.stringify({data:[{code:row[0],name:row[1]}]}));return;
 }
 res.writeHead(200,{'Content-Type':'text/html'}).end('<h1>Isolated navigation target</h1>');
});
await new Promise(r=>server.listen(8135,'127.0.0.1',r));
const targets=await(await fetch('http://127.0.0.1:9228/json/list')).json();
const ws=new WebSocket(targets.find(t=>t.type==='page'&&!t.url.startsWith('edge://')).webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
let id=0;const calls=new Map(),errors=[];ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);const pending=calls.get(m.id);if(pending){calls.delete(m.id);m.error?pending.reject(new Error(m.error.message)):pending.resolve(m.result);}};
const cdp=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;calls.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});
async function evaluate(expression){const r=await cdp('Runtime.evaluate',{expression:`{ ${expression} }`,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description??r.exceptionDetails.text);return r.result.value;}
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function until(expression){for(let i=0;i<70;i++){if(await evaluate(expression))return;await pause(80);}throw new Error('Timed out: '+expression);}
async function navigate(key){await cdp('Page.navigate',{url:base+'/preview/'+key});await until(`document.readyState==='complete' && location.pathname==='/preview/${key}'`);await pause(150);}
async function screenshot(key,width){assert.ok(await evaluate(`document.documentElement.scrollWidth<=${width}`),'no overflow '+key);writeFileSync(out+key+'.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));}
async function fillPersonal(){
 const data={first_name:'Niño Angelo',last_name:"Peñaflor-O'Hara",sex:'male',email:'rider.preview@example.com',phone:'09171234567',birthday:'1995-06-15',address_line:'12 Test Street',postal_code:'4013'};
 await evaluate(`const form=document.querySelector('[data-rider-registration]');for(const [key,value] of Object.entries(${JSON.stringify(data)})){form.elements[key].value=value;form.elements[key].dispatchEvent(new Event('input',{bubbles:true}));}`);
 for(const [level,code] of [['region','0400000000'],['province','0403400000'],['city','0403407000'],['barangay','0403407012']]){await until(`!document.querySelector('[data-location-select="${level}"]').disabled`);await evaluate(`const el=document.querySelector('[data-location-select="${level}"]');el.value='${code}';el.dispatchEvent(new Event('change',{bubbles:true}));`);}
}
async function fillRider(){await evaluate(`const form=document.querySelector('[data-rider-registration]');form.elements.driver_license_number.value='N01-23-456789';form.elements.driver_license_classification.value='Test classification';form.elements.driver_license_expires_at.value=new Date(Date.now()+366*86400000).toISOString().slice(0,10);for(const name of ['valid_id','driver_license_front','driver_license_back']){const dt=new DataTransfer();dt.items.add(new File(['fixture'],name+'.pdf',{type:'application/pdf'}));form.elements[name].files=dt.files;}`);}
try {
 await cdp('Page.enable');await cdp('Runtime.enable');
 for(const [size,width,height] of [['desktop',1440,1000],['tablet',768,1024],['mobile',390,844]]){
  await cdp('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:size==='mobile'});
  await navigate('register');assert.ok(await evaluate(`!document.querySelector('[data-registration-panel="0"]').hidden && document.querySelector('[data-registration-panel="1"]').hidden && document.querySelector('[data-registration-panel="2"]').hidden`));await screenshot('personal-'+size,width);
  const count=writes.length;await evaluate(`document.querySelector('[data-rider-registration]').requestSubmit()`);assert.equal(writes.length,count);assert.ok(await evaluate(`!document.querySelector('[data-registration-next]').disabled`));
  await fillPersonal();await evaluate(`document.querySelector('[data-registration-next]').click()`);assert.ok(await evaluate(`!document.querySelector('[data-registration-panel="1"]').hidden`));await screenshot('details-'+size,width);
  await fillRider();await evaluate(`document.querySelector('[data-registration-next]').click()`);assert.ok(await evaluate(`!document.querySelector('[data-registration-panel="2"]').hidden`));await screenshot('security-'+size,width);
  for(const key of ['login','submitted','pending','rejected','unavailable','errors']){await navigate(key);await screenshot(key+'-'+size,width);if(key==='errors')assert.ok(await evaluate(`!document.querySelector('[data-registration-panel="2"]').hidden && document.querySelector('[name="email"]').value==='rider.signup@example.com' && document.querySelector('[name="password"]').value===''`));}
  console.log('PASS '+size+' three-step registration, login, success, pending/rejected/restricted and inline errors');
 }
 await navigate('register');await evaluate(`document.querySelector('[name="first_name"]').value='Juan123';document.querySelector('[name="first_name"]').dispatchEvent(new Event('input',{bubbles:true}));`);assert.equal(await evaluate(`document.querySelector('[name="first_name"]').checkValidity()`),false);
 await fillPersonal();await evaluate(`document.querySelector('[data-registration-next]').click()`);await fillRider();await evaluate(`document.querySelector('[data-registration-back]').click()`);assert.equal(await evaluate(`document.querySelector('[name="first_name"]').value`),'Niño Angelo');await evaluate(`document.querySelector('[data-registration-next]').click();document.querySelector('[data-registration-next]').click()`);
 await evaluate(`document.querySelector('[data-password-toggle]').click()`);assert.equal(await evaluate(`document.querySelector('[name="password"]').type`),'text');await evaluate(`document.querySelector('[data-password-toggle]').click()`);assert.equal(await evaluate(`document.querySelector('[name="password"]').type`),'password');
 await evaluate(`const form=document.querySelector('[data-rider-registration]');form.elements.password.value='RiderPass123!';form.elements.password_confirmation.value='Mismatch@2026';form.dispatchEvent(new Event('input',{bubbles:true}));`);assert.equal(await evaluate(`document.querySelector('[name="password_confirmation"]').checkValidity()`),false);
 await evaluate(`const form=document.querySelector('[data-rider-registration]');form.elements.password_confirmation.value='RiderPass123!';form.elements.terms.checked=true;form.dispatchEvent(new Event('input',{bubbles:true}));`);assert.ok(await evaluate(`[...document.querySelectorAll('[data-rider-password-rule]')].every(el=>el.textContent.startsWith('✓'))`));
 const before=writes.length;await evaluate(`const form=document.querySelector('[data-rider-registration]');const button=form.querySelector('[data-registration-submit]');form.requestSubmit(button);form.requestSubmit(button);`);await pause(300);assert.equal(writes.length,before+1);assert.equal(writes.at(-1).path,'/rider/register');assert.ok(writes.at(-1).body.includes('_token'));assert.ok(await evaluate(`document.querySelector('[data-registration-submit]').disabled && document.querySelector('[data-registration-submit]').textContent==='Creating account…'`));console.log('PASS field validation, step Back, show/hide, confirmation, strength and one POST with processing state');
 locationsUnavailable=true;await navigate('register');await until(`!document.querySelector('[data-location-retry]').classList.contains('hidden')`);locationsUnavailable=false;await evaluate(`document.querySelector('[data-location-retry]').click()`);await until(`!document.querySelector('[data-location-select="region"]').disabled`);console.log('PASS location error and Retry');
 await navigate('login');assert.ok(await evaluate(`document.querySelector('a[href="${base}/rider/register"]')`));await navigate('submitted');assert.ok(await evaluate(`document.querySelector('a[href="${base}/rider/login"]')`));await navigate('pending');assert.ok(await evaluate(`document.querySelector('form').action==='${base}/rider/logout'`));
 assert.deepEqual(errors,[]);console.log('PASS Rider auth navigation, no runtime errors and no live database writes');
}finally{ws.close();server.closeAllConnections();await new Promise(r=>server.close(r));}
