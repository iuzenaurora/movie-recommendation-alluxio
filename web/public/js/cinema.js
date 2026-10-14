
document.addEventListener('DOMContentLoaded',()=>{
 if(window.lucide)lucide.createIcons();
 if(!matchMedia('(hover: hover) and (prefers-reduced-motion: no-preference)').matches)return;
 document.querySelectorAll('.glass-card').forEach(card=>{
 card.addEventListener('pointermove',e=>{const r=card.getBoundingClientRect();card.style.setProperty('--ry',((e.clientX-r.left)/r.width-.5)*7+'deg');card.style.setProperty('--rx',-((e.clientY-r.top)/r.height-.5)*5+'deg')});
 card.addEventListener('pointerleave',()=>{card.style.setProperty('--rx','0deg');card.style.setProperty('--ry','0deg')});
 });
});
