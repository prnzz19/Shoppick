document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('[data-buyer-password]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById(button.dataset.buyerPassword);const show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';button.setAttribute('aria-pressed',String(show));}));
 const form=document.querySelector('[data-buyer-registration]');
 if(form){
  const check=()=>{
   ['first_name','last_name'].forEach(name=>{const field=form.elements[name],value=field.value.trim();field.setCustomValidity(value&&(!/^[\p{L} .'-]+$/u.test(value)||!/[\p{L}]/u.test(value)||[...value].length<2||[...value].length>50)?'Name must contain 2–50 valid characters.':'');});
   const phone=form.elements.phone;phone.setCustomValidity(phone.value&&!/^(09[0-9]{9}|\+639[0-9]{9})$/.test(phone.value.trim())?'Enter a valid Philippine mobile number.':'');
   const email=form.elements.email;if(email)email.setCustomValidity(email.value&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/u.test(email.value.trim())?'Enter a valid email address.':'');
   const birthday=form.elements.birthday;birthday.max=form.dataset.birthdayCutoff;birthday.setCustomValidity(birthday.value&&birthday.value>birthday.max?'You must be at least 18 years old.':'');
   const password=form.elements.password,confirmation=form.elements.password_confirmation;
   if(!password)return;
   const rules={length:[...password.value].length>=8,upper:/\p{Lu}/u.test(password.value),lower:/\p{Ll}/u.test(password.value),number:/\p{N}/u.test(password.value),symbol:/[^\p{L}\p{N}\s]/u.test(password.value)};
   password.setCustomValidity(password.value&&!Object.values(rules).every(Boolean)?'Password needs 8+ characters, uppercase, lowercase, a number and a special character.':'');
   confirmation.setCustomValidity(confirmation.value&&confirmation.value!==password.value?'Passwords do not match.':'');
   Object.entries(rules).forEach(([key,valid])=>{const item=form.querySelector(`[data-password-rule="${key}"]`);item.classList.toggle('text-brand-700',valid);item.textContent=(valid?'✓':'○')+item.textContent.slice(1);});
  };
  form.addEventListener('input',check);form.addEventListener('change',check);check();
  form.elements.email?.addEventListener('blur',()=>{form.elements.email.value=form.elements.email.value.trim().toLowerCase();});
 }
 document.querySelectorAll('[data-buyer-form]').forEach(form=>form.addEventListener('submit',event=>{if(form.dataset.sending==='true'){event.preventDefault();return;}if(!form.checkValidity()){event.preventDefault();form.reportValidity();return;}form.dataset.sending='true';queueMicrotask(()=>{const button=event.submitter??form.querySelector('button[type="submit"]');if(button){button.disabled=true;button.textContent='Processing…';}});}));
 const verification=document.querySelector('[data-buyer-verification]');if(verification){
  const button=verification.querySelector('[data-buyer-resend]');
  const update=()=>{const left=Math.max(0,Math.ceil((Date.parse(verification.dataset.expiresAt)-Date.now())/1000)||0);verification.querySelector('[data-code-expiry]').textContent=left?`Code expires in ${String(Math.floor(left/60)).padStart(2,'0')}:${String(left%60).padStart(2,'0')}`:'Code expired. Request a new code.';const wait=Math.max(0,Math.ceil((Date.parse(verification.dataset.resendAt)-Date.now())/1000)||0);button.disabled=wait>0||button.form.dataset.sending==='true';verification.querySelector('[data-resend-countdown]').textContent=wait?`Resend available in ${wait} seconds.`:'';};update();setInterval(update,1000);
 }
 window.addEventListener('pageshow',event=>{if(event.persisted&&document.querySelector('[data-buyer-form]'))window.location.reload();});
});
