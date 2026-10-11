// Uses rendered Laravel fixtures from SQLite :memory: and Mail::fake(); no live account writes.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
const base='http://127.0.0.1:8134',dir='storage/app/buyer-workbook-fixtures/',out='storage/app/buyer-workbook-previews/',writes=[];
mkdirSync(out,{recursive:true});
let geographyUnavailable=false;
const server=createServer(async(req,res)=>{
 const path=new URL(req.url,base).pathname;
 if(req.method==='POST'){let body='';for await(const chunk of req)body+=chunk;writes.push({path,body});res.writeHead(204).end();return;}
 if(path.startsWith('/preview/')){const key=path.split('/').at(-1);if(!/^[a-z]+$/.test(key)){res.writeHead(404).end();return;}try{res.writeHead(200,{'Content-Type':'text/html'}).end(readFileSync(dir+key+'.html','utf8').replace(/https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?/g,base));}catch{res.writeHead(404).end();}return;}
 if(/^\/build\/assets\/[\w.-]+$/.test(path)){res.writeHead(200,{'Content-Type':path.endsWith('.css')?'text/css':'text/javascript'}).end(readFileSync('public'+path));return;}
 if(path.includes('philippine-locations')){
  if(geographyUnavailable){res.writeHead(503,{'Content-Type':'application/json'}).end('{}');return;}
  const row=path.endsWith('/regions')?['0400000000','Region IV-A (CALABARZON)']:path.endsWith('/provinces')?['0403400000','Laguna']:path.endsWith('/cities-municipalities')?['0403426000','Santa Cruz']:['0403426003','Bubukal'];
  res.writeHead(200,{'Content-Type':'application/json'}).end(JSON.stringify({data:[{code:row[0],name:row[1]}]}));return;
 }
 res.writeHead(200,{'Content-Type':'text/html'}).end('<h1>Fixture navigation target</h1>');
});
await new Promise(r=>server.listen(8134,'127.0.0.1',r));
const targets=await(await fetch('http://127.0.0.1:9227/json/list')).json();
const ws=new WebSocket(targets.find(t=>t.type==='page'&&!t.url.startsWith('edge://')).webSocketDebuggerUrl);await new Promise(r=>ws.onopen=r);
let id=0;const calls=new Map(),errors=[];
ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);const pending=calls.get(m.id);if(pending){calls.delete(m.id);m.error?pending.reject(new Error(m.error.message)):pending.resolve(m.result);}};
const cdp=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;calls.set(n,{resolve,reject});ws.send(JSON.stringify({id:n,method,params}));});
async function evaluate(expression){const r=await cdp('Runtime.evaluate',{expression:`{ ${expression} }`,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description??r.exceptionDetails.text);return r.result.value;}
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function until(expression){for(let i=0;i<70;i++){if(await evaluate(expression))return;await pause(80);}throw new Error('Timed out: '+expression);}
async function navigate(key){await cdp('Page.navigate',{url:base+'/preview/'+key});await until(`document.readyState==='complete' && location.pathname==='/preview/${key}'`);await pause(150);}
try{
 await cdp('Page.enable');await cdp('Runtime.enable');
 for(const [size,width,height] of [['desktop',1440,1000],['tablet',768,1024],['mobile',390,844]]){
  await cdp('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:size==='mobile'});
  for(const key of ['register','login','errors','verification','expired','terms','privacy']){
   await navigate(key);assert.ok(await evaluate(`document.documentElement.scrollWidth<=${width}`),key+' no overflow');
   if(key==='errors')assert.ok(await evaluate(`document.querySelector('[name="email"]').value==='juanmiguel.delacruz@example.com' && document.querySelector('[role="alert"]')`));
   if(key==='register')assert.equal(await evaluate(`document.querySelector('[name="password"]').type`),'password');
   writeFileSync(out+key+'-'+size+'.png',Buffer.from((await cdp('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));
  }
  console.log('PASS responsive '+size+' registration, errors, login, OTP, expiry, policy links');
 }
 await navigate('register');await evaluate(`document.querySelector('[data-buyer-password]').click()`);assert.equal(await evaluate(`document.querySelector('[name="password"]').type`),'text');await evaluate(`document.querySelector('[data-buyer-password]').click()`);assert.equal(await evaluate(`document.querySelector('[name="password"]').type`),'password');
 const valid={first_name:'Niño Angelo',last_name:"Peñaflor-O'Hara",email:'buyer.test01@example.com',phone:'09171234567',birthday:'1998-05-14',password:'Mamimili@2026',password_confirmation:'Mamimili@2026'};
 await evaluate(`const form=document.querySelector('[data-buyer-registration]');for(const [key,value] of Object.entries(${JSON.stringify(valid)})){form.elements[key].value=value;form.elements[key].dispatchEvent(new Event('input',{bubbles:true}));}`);
 assert.ok(await evaluate(`['first_name','last_name','email','phone','birthday','password','password_confirmation'].every(key=>document.querySelector('form').elements[key].checkValidity())`));
 for(const [field,bad] of [['first_name','Juan123'],['last_name','A'],['email','buyer@example'],['phone','09!171234567'],['birthday','2030-01-01'],['password','mamimili2026'],['password_confirmation','Mismatch@2026']]){
  await evaluate(`const el=document.querySelector('[name="${field}"]');el.value=${JSON.stringify(bad)};el.dispatchEvent(new Event('input',{bubbles:true}));`);assert.equal(await evaluate(`document.querySelector('[name="${field}"]').checkValidity()`),false,field+' client validation');
  await evaluate(`const el=document.querySelector('[name="${field}"]');el.value=${JSON.stringify(valid[field])};el.dispatchEvent(new Event('input',{bubbles:true}));`);
 }
 assert.ok(await evaluate(`Array.from(document.querySelectorAll('[data-password-rule]')).every(el=>el.textContent.startsWith('✓'))`));console.log('PASS masked toggles and workbook real-time validation');
 for(const [level,code] of [['region','0400000000'],['province','0403400000'],['city','0403426000'],['barangay','0403426003']]){
  await until(`!document.querySelector('[data-location-select="${level}"]').disabled`);
  await evaluate(`const el=document.querySelector('[data-location-select="${level}"]');el.value='${code}';el.dispatchEvent(new Event('change',{bubbles:true}));`);
 }
 await evaluate(`const form=document.querySelector('[data-buyer-registration]');form.elements.sex.value='male';form.elements.address_line.value='12 Test Street';form.elements.postal_code.value='4009';form.elements.terms.checked=true;const dt=new DataTransfer();dt.items.add(new File(['fixture'],'id.pdf',{type:'application/pdf'}));form.elements.valid_id.files=dt.files;`);
 assert.ok(await evaluate(`document.querySelector('[data-buyer-registration]').checkValidity()`));
 await evaluate(`const form=document.querySelector('[data-buyer-registration]');const button=form.querySelector('[type="submit"]');form.requestSubmit(button);form.requestSubmit(button);`);await pause(250);assert.equal(writes.length,1);assert.ok(writes[0].path==='/register/buyer' && writes[0].body.includes('registration_token'));console.log('PASS dependent address dropdowns and one registration submission');
 geographyUnavailable=true;await navigate('register');await until(`!document.querySelector('[data-location-retry]').classList.contains('hidden')`);geographyUnavailable=false;await evaluate(`document.querySelector('[data-location-retry]').click()`);await until(`!document.querySelector('[data-location-select="region"]').disabled`);console.log('PASS geography outage and successful Retry');
 await navigate('login');await evaluate(`document.querySelector('[name="email"]').value='buyer.test01@example.com';document.querySelector('[name="password"]').value='Mamimili@2026';const form=document.querySelector('form');const button=form.querySelector('[type="submit"]');form.requestSubmit(button);form.requestSubmit(button);`);await pause(250);assert.equal(writes.length,2);assert.ok(writes[1].body.includes('_token='));assert.ok(await evaluate(`document.querySelector('[type="submit"]').disabled && document.querySelector('[type="submit"]').textContent==='Processing…'`));console.log('PASS double submission guard and processing state');
 await navigate('verification');await evaluate(`const panel=document.querySelector('[data-buyer-verification]');panel.dataset.expiresAt=new Date(Date.now()+299000).toISOString();panel.dataset.resendAt=new Date(Date.now()+60000).toISOString();`);await pause(1100);assert.ok(await evaluate(`document.querySelector('[data-buyer-resend]').disabled && document.querySelector('[data-code-expiry]').textContent.includes('04:')`));
 const count=writes.length;await evaluate(`const input=document.querySelector('[name="code"]');input.value='482913';input.dispatchEvent(new InputEvent('input',{bubbles:true,inputType:'insertFromPaste',data:'482913'}));`);assert.equal(writes.length,count);assert.ok(await evaluate(`document.querySelector('[name="code"]').checkValidity()`));
 await evaluate(`document.querySelector('[name="code"]').value='ABCDEF'`);assert.equal(await evaluate(`document.querySelector('[name="code"]').checkValidity()`),false);
 await evaluate(`const panel=document.querySelector('[data-buyer-verification]');panel.dataset.expiresAt=new Date(Date.now()-1000).toISOString();panel.dataset.resendAt=new Date(Date.now()-1000).toISOString();`);await pause(1100);assert.ok(await evaluate(`!document.querySelector('[data-buyer-resend]').disabled && document.querySelector('[data-code-expiry]').textContent.includes('expired')`));console.log('PASS OTP paste without auto-submit, numeric validation, countdown, expiry and resend');
 assert.deepEqual(errors,[]);console.log('PASS no JavaScript exceptions; no live database or email writes');
}finally{ws.close();server.closeAllConnections();await new Promise(r=>server.close(r));}
