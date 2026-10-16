document.addEventListener('DOMContentLoaded',()=>{
 const form=document.querySelector('[data-rider-registration]');if(!form)return;
 const panels=[...form.querySelectorAll('[data-registration-panel]')],nav=document.querySelector('[data-registration-navigation]'),next=form.querySelector('[data-registration-next]'),back=form.querySelector('[data-registration-back]'),submit=form.querySelector('[data-registration-submit]');let step=0;
 const check=()=>{
  for(const name of ['first_name','last_name']){const field=form.elements[name],value=field.value.trim();field.setCustomValidity(value&&(!/^[\p{L} .'-]+$/u.test(value)||!/[\p{L}]/u.test(value)||[...value].length<2||[...value].length>50)?'Enter a name with 2–50 letters and valid name punctuation.':'');}
  const phone=form.elements.phone;phone.setCustomValidity(phone.value&&!/^(09[0-9]{9}|\+639[0-9]{9})$/.test(phone.value.trim())?'Enter a valid Philippine mobile number.':'');
  const email=form.elements.email;email.setCustomValidity(email.value&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/u.test(email.value.trim())?'Enter a valid email address.':'');
  const password=form.elements.password,confirmation=form.elements.password_confirmation,rules={length:[...password.value].length>=8,upper:/\p{Lu}/u.test(password.value),lower:/\p{Ll}/u.test(password.value),number:/\p{N}/u.test(password.value),symbol:/[^\p{L}\p{N}\s]/u.test(password.value)};
  password.setCustomValidity(password.value&&!Object.values(rules).every(Boolean)?'Use 8+ characters, uppercase, lowercase, a number and a special character.':'');confirmation.setCustomValidity(confirmation.value&&confirmation.value!==password.value?'Your passwords do not match.':'');
  for(const [rule,valid] of Object.entries(rules)){const hint=form.querySelector(`[data-rider-password-rule="${rule}"]`);hint.classList.toggle('text-brand-700',valid);hint.textContent=(valid?'✓':'○')+hint.textContent.slice(1);}
 };
 const show=(index,focus=false)=>{step=index;panels.forEach((panel,i)=>panel.hidden=i!==index);next.hidden=index===2;back.hidden=index===0;submit.hidden=index!==2;form.querySelector('[data-registration-progress]').textContent=`Step ${index+1} of 3`;nav.querySelectorAll('button').forEach((button,i)=>{button.setAttribute('aria-current',i===index?'step':'false');button.classList.toggle('bg-brand-50',i===index);button.classList.toggle('text-brand-700',i===index);});if(focus)panels[index].querySelector('h2').focus();};
 const invalid=panel=>[...panel.querySelectorAll('input,select,textarea')].find(field=>!field.checkValidity());
 const advance=()=>{check();const field=invalid(panels[step]);if(field){field.reportValidity();return;}show(Math.min(step+1,2),true);};
 nav.hidden=false;form.noValidate=true;show(0);check();form.addEventListener('input',check);form.addEventListener('change',check);next.addEventListener('click',advance);back.addEventListener('click',()=>show(Math.max(step-1,0),true));
 nav.querySelectorAll('button').forEach(button=>button.addEventListener('click',()=>{const target=Number(button.dataset.stepIndex);if(target<=step)show(target,true);else for(let i=0;i<target;i++){check();const field=invalid(panels[i]);if(field){show(i);field.reportValidity();return;}show(i+1,true);}}));
 form.elements.email.addEventListener('blur',()=>{form.elements.email.value=form.elements.email.value.trim().toLowerCase();check();});
 form.addEventListener('submit',event=>{check();if(step<2){event.preventDefault();event.stopPropagation();advance();return;}for(let i=0;i<panels.length;i++){const field=invalid(panels[i]);if(field){event.preventDefault();event.stopPropagation();show(i);field.reportValidity();return;}}});
 const errors=JSON.parse(form.dataset.errorFields||'[]');const errorPanel=panels.findIndex(panel=>errors.some(name=>panel.querySelector(`[name="${CSS.escape(name)}"]`)));if(errorPanel>=0)show(errorPanel);
});
