// Explicit live smoke test: creates one Buyer and one pending test Seller application.
// Does not approve applications or reconcile existing accounts.
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

assert.equal(process.env.SHOPPICK_ALLOW_TEST_REGISTRATION, '1', 'Explicitly enable creation of live test records');
const base = process.env.SHOPPICK_TEST_URL || 'http://127.0.0.1:8000';
const stamp = Date.now();
const email = `local-buyer-login-${stamp}@example.test`;
const password = `Test-${randomBytes(16).toString('hex')}!`;
const pdf = join(tmpdir(), `shoppick-buyer-test-${stamp}.pdf`);
const logo = join(tmpdir(), `shoppick-buyer-test-${stamp}.png`);
await writeFile(pdf, '%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n');
await writeFile(logo, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jV1sAAAAASUVORK5CYII=', 'base64'));
const targets = await (await fetch('http://127.0.0.1:9222/json/list')).json();
const ws = new WebSocket(targets.find(target=>target.type==='page').webSocketDebuggerUrl);
await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
let id=0;
const pending=new Map();
ws.onmessage=event=>{
    const result=JSON.parse(event.data), call=pending.get(result.id);
    if(call){pending.delete(result.id);result.error?call.reject(new Error(result.error.message)):call.resolve(result.result);}
};
function cdp(method,params={}){return new Promise((resolve,reject)=>{const key=++id;pending.set(key,{resolve,reject});ws.send(JSON.stringify({id:key,method,params}));});}
async function evaluate(expression){const result=await cdp('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(result.exceptionDetails)throw new Error(result.exceptionDetails.exception?.description||result.exceptionDetails.text);return result.result.value;}
async function until(expression){const start=Date.now();while(Date.now()-start<30000){if(await evaluate(expression))return;await new Promise(resolve=>setTimeout(resolve,100));}throw new Error(`Timed out: ${expression}`);}
async function navigate(path){await cdp('Page.navigate',{url:base+path});await until(`location.pathname===${JSON.stringify(path)} && document.readyState==='complete'`);}
async function fill(values){await evaluate(`for(const [name,value] of Object.entries(${JSON.stringify(values)})){const field=document.querySelector('[name="'+name+'"]');field.value=value;field.dispatchEvent(new Event('input',{bubbles:true}));field.dispatchEvent(new Event('change',{bubbles:true}));}`);}
async function upload(name,file){const {root}=await cdp('DOM.getDocument');const {nodeId}=await cdp('DOM.querySelector',{nodeId:root.nodeId,selector:`input[type=file][name="${name}"]`});assert.ok(nodeId);await cdp('DOM.setFileInputFiles',{nodeId,files:[file]});}
async function submit(selector){assert.ok(await evaluate(`document.querySelector(${JSON.stringify(selector)}).checkValidity()`),'The form must satisfy browser validation');await evaluate(`document.querySelector(${JSON.stringify(selector)}).requestSubmit()`);}
async function login(){await navigate('/login');await fill({email,password});await submit('form[action$="/login"]');await until(`location.pathname!=='/login' && document.readyState==='complete'`);assert.equal(await evaluate(`document.body.innerText.includes('waiting for administrator approval')`),false);}

try{
    await cdp('Page.enable');
    await navigate('/register');
    await until(`document.querySelector('[name="region_code"]') && !document.querySelector('[name="region_code"]').disabled`);
    await fill({first_name:'Local',last_name:'Buyer Verification',sex:'male',birthday:'2000-01-01',email,phone:'09171234567',address_line:'12 Local Test Street',postal_code:'4009',password,password_confirmation:password,region_code:'0400000000'});
    await until(`!document.querySelector('[name="province_code"]').disabled`);
    await fill({province_code:'0403400000'});
    await until(`!document.querySelector('[name="city_code"]').disabled`);
    await fill({city_code:'0403426000'});
    await until(`!document.querySelector('[name="barangay_code"]').disabled`);
    await fill({barangay_code:'0403426003'});
    await evaluate(`document.querySelector('[name="terms"]').checked=true`);
    await upload('valid_id',pdf);
    await submit('form[action$="/register/buyer"]');
    await until(`location.pathname==='/login' && document.readyState==='complete'`);
    console.log('PASS live new-Buyer registration redirected to Login');
    await login();
    assert.equal(await evaluate('location.pathname'),'/shop');
    await navigate('/account');
    assert.equal(await evaluate('location.pathname'),'/account');
    await navigate('/cart');
    assert.equal(await evaluate('location.pathname'),'/cart');
    console.log('PASS live immediate login, Buyer account and cart access');

    await navigate('/seller/apply');
    await fill({store_name:`Local Buyer Verification ${stamp}`,store_description:'Local authentication smoke test; no products.',phone:'09171234567',address:'12 Local Test Street, Bubukal, Santa Cruz, Laguna'});
    const category=await evaluate(`Array.from(document.querySelector('[name="category_id"]').options).find(option=>option.value)?.value`);
    assert.ok(category,'An existing active category must be available');
    await fill({category_id:category});
    await upload('valid_id',pdf);await upload('business_permit',pdf);await upload('logo',logo);
    await submit('form[action$="/seller/apply"]');
    await until(`document.body.innerText.includes('Pending Admin review')`);
    console.log('PASS live Seller application remains pending');
    await evaluate(`document.querySelector('form[action$="/logout"]').requestSubmit()`);
    await until(`location.pathname==='/login' && document.readyState==='complete'`);
    await login();
    assert.equal(await evaluate('location.pathname'),'/seller/apply');
    await navigate('/account');
    await navigate('/shop');
    console.log('PASS Buyer can log in and shop after submitting the pending Seller application');
    console.log(JSON.stringify({test_email:email,store_name:`Local Buyer Verification ${stamp}`,registration:'passed',login:'passed',seller_application:'pending',buyer_access:'passed'}));
}finally{ws.close();}
