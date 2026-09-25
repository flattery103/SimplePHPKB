'use strict';
const toggle=document.querySelector('.category-toggle');
if(toggle)toggle.addEventListener('click',()=>{const expanded=toggle.getAttribute('aria-expanded')==='true';toggle.setAttribute('aria-expanded',String(!expanded));document.querySelector('.category-menu').classList.toggle('open',!expanded);});
for(const form of document.querySelectorAll('form[data-confirm]'))form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm))event.preventDefault();});
for(const button of document.querySelectorAll('[data-copy-link]'))button.addEventListener('click',async()=>{
 const url=new URL(button.dataset.copyLink,window.location.origin).href;
 try{await navigator.clipboard.writeText(url);button.textContent='Link copied';setTimeout(()=>button.textContent='Copy link',2000);}
 catch{window.prompt('Copy this article link:',url);}
});
