(() => {
 window.shoppickHeroCleanup?.();
 const root=document.querySelector('#hero-carousel'); if(!root)return;
 const title=root.querySelector('#hero-title'), description=root.querySelector('#hero-description'), img=root.querySelector('.sp-lifestyle-hero img');
 const slides = [
 ['Find What Fits Your Life.', 'From everyday essentials to your next favorite find, SHOPPICK helps you discover products made for the way you live.', 'Original campaign model in a beige trench coat, straw hat and sunglasses, looking over her shoulder'],
 ['Better Picks for Everyday Life.', 'Find useful products, fresh finds, and everyday favorites from shops across SHOPPICK.', 'A shopper using a phone beside a tumbler and headphones'],
 ['Little Finds. Better Days.', 'Sometimes the right find can make your everyday routine a little easier, better, and more enjoyable.', 'A shopper holding a delivery parcel in a welcoming home']
 ];

 const urls=slides.map((_,i)=>new URL(i===0?'campaign-model-original.jpg':'lifestyle-slide-'+(i+1)+'.jpg',img.src).href);
 const motion=matchMedia('(prefers-reduced-motion: reduce)'), events=new AbortController();
 let index=0, timer, disposed=false, pressed=false, changing=false;
 const animated=[title,description,img];
 function update(next){index=(next+3)%3;title.textContent=slides[index][0];description.textContent=slides[index][1];img.src=urls[index];img.alt=slides[index][2];root.dataset.slide=index;}
 async function rotate(){
  if(disposed||changing||pressed||document.hidden)return;
  if(!root.isConnected){cleanup();return;}
  changing=true;
  if(!motion.matches){await Promise.all(animated.map(e=>e.animate([{opacity:1},{opacity:0}],{duration:300,fill:'forwards'}).finished.catch(()=>{})));}
  if(disposed)return;
  update(index+1);
  animated.forEach(e=>{e.getAnimations().forEach(a=>a.cancel());if(!motion.matches)e.animate([{opacity:0},{opacity:1}],{duration:300});});
  changing=false;
 }
 function start(){clearInterval(timer);if(!disposed&&!document.hidden)timer=setInterval(rotate,10000);}
 function cleanup(){disposed=true;clearInterval(timer);events.abort();animated.forEach(e=>e.getAnimations().forEach(a=>a.cancel()));}
 window.shoppickHeroCleanup=cleanup;
 root.addEventListener('pointerdown',()=>pressed=true,{signal:events.signal});
 window.addEventListener('pointerup',()=>pressed=false,{signal:events.signal});
 window.addEventListener('pointercancel',()=>pressed=false,{signal:events.signal});
 document.addEventListener('visibilitychange',start,{signal:events.signal});
 window.addEventListener('pagehide',()=>clearInterval(timer),{signal:events.signal});
 window.addEventListener('pageshow',start,{signal:events.signal});
 update(0);start();
 const preload=()=>urls.forEach(url=>{if(url!==img.src){const photo=new Image();photo.src=url;}});
 if(document.readyState==='complete')preload();else window.addEventListener('load',preload,{once:true,signal:events.signal});
})();
