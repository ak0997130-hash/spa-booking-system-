// Site behaviour (menu toggle, confirm dialogs, image fallback). Lives in a file so pages need no inline scripts (strict Content-Security-Policy).
(()=>{const d=document;
d.addEventListener('click',e=>{const t=e.target.closest('[data-toggle-nav]');
 if(t){const n=d.querySelector(t.getAttribute('data-toggle-nav'));if(n){const o=n.classList.toggle('open');t.setAttribute('aria-expanded',o)}}
 const c=e.target.closest('[data-confirm]');if(c&&!confirm(c.getAttribute('data-confirm')))e.preventDefault()});
d.addEventListener('error',e=>{if(e.target.tagName==='IMG'&&e.target.hasAttribute('data-fb'))e.target.remove()},true);
d.querySelectorAll('img[data-fb]').forEach(i=>{if(i.complete&&i.naturalWidth===0)i.remove()});})();
