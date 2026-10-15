// Decorative only: bounded parallax and occasional compositor-driven jump trails.
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
const compactViewport = window.matchMedia('(max-width: 700px)');
const sky = document.createElement('div');
sky.className = 'starfield-jumps';
sky.setAttribute('aria-hidden', 'true');
document.body.prepend(sky);

let frame = 0;
let jumpTimer = 0;
let previousTime = 0;
let pointerX = 0;
let pointerY = 0;
let offsetX = 0;
let offsetY = 0;
let scrollOffset = 0;
let suspended = false;

function canAnimate() {
  return !reducedMotion.matches && !document.hidden && !suspended;
}

function paintParallax() {
  const style = document.body.style;
  style.setProperty('--stars-far-x', `${(offsetX * .35).toFixed(2)}px`);
  style.setProperty('--stars-far-y', `${(offsetY * .35 - scrollOffset * .3).toFixed(2)}px`);
  style.setProperty('--stars-near-x', `${offsetX.toFixed(2)}px`);
  style.setProperty('--stars-near-y', `${(offsetY - scrollOffset).toFixed(2)}px`);
}

function updateParallax(time) {
  frame = 0;
  if (!canAnimate()) return;
  const targetScroll = Math.min(Math.max(window.scrollY, 0) * .035, 22);
  const delta = previousTime ? Math.min(time - previousTime, 50) : 16;
  const ease = 1 - Math.exp(-delta / 120);
  previousTime = time;
  offsetX += (pointerX - offsetX) * ease;
  offsetY += (pointerY - offsetY) * ease;
  scrollOffset += (targetScroll - scrollOffset) * ease;
  const settling = Math.abs(pointerX - offsetX) + Math.abs(pointerY - offsetY)
    + Math.abs(targetScroll - scrollOffset) > .08;
  if (!settling) {
    offsetX = pointerX;
    offsetY = pointerY;
    scrollOffset = targetScroll;
  }
  paintParallax();
  if (settling) frame = window.requestAnimationFrame(updateParallax);
  else previousTime = 0;
}

function requestParallax() {
  if (canAnimate() && !frame) frame = window.requestAnimationFrame(updateParallax);
}

function resetPointer() {
  pointerX = 0;
  pointerY = 0;
  requestParallax();
}

function scheduleJump(first = false) {
  if (!canAnimate() || typeof sky.animate !== 'function') return;
  const delay = first ? 1800 : (compactViewport.matches ? 10000 : 6500) + Math.random() * 5500;
  jumpTimer = window.setTimeout(createJump, delay);
}

function createJump() {
  jumpTimer = 0;
  if (!canAnimate()) return;
  const width = window.innerWidth;
  const height = window.innerHeight;
  const reverse = Math.random() > .5;
  const fromX = width * (reverse ? .76 + Math.random() * .16 : .08 + Math.random() * .16);
  const toX = width * (reverse ? .08 + Math.random() * .16 : .76 + Math.random() * .16);
  const fromY = height * (.12 + Math.random() * .7);
  const toY = Math.max(height * .08, Math.min(height * .92, fromY + (Math.random() - .5) * height * .6));
  const distance = Math.hypot(toX - fromX, toY - fromY);
  const tail = Math.min(compactViewport.matches ? 85 : 150, distance * .35);
  const duration = 2400 + Math.random() * 1200;
  const jump = document.createElement('span');
  jump.className = 'stellar-jump';
  jump.style.left = `${fromX}px`;
  jump.style.top = `${fromY}px`;
  jump.style.width = `${distance}px`;
  jump.style.transform = `rotate(${Math.atan2(toY - fromY, toX - fromX)}rad)`;
  const trail = document.createElement('span');
  trail.className = 'stellar-jump-trail';
  trail.style.width = `${tail}px`;
  jump.append(trail);
  sky.append(jump);

  const flight = trail.animate([
    { transform: `translateX(${-tail}px) scaleX(.15)`, opacity: 0 },
    { offset: .12, opacity: .8 },
    { offset: .3, transform: `translateX(${distance * .22 - tail}px) scaleX(1)` },
    { offset: .8, opacity: .65 },
    { transform: `translateX(${distance - tail}px) scaleX(.15)`, opacity: 0 },
  ], { duration, delay: 250, easing: 'cubic-bezier(.4,0,.2,1)', fill: 'both' });
  const glow = jump.animate([
    { opacity: 0 }, { offset: .12, opacity: .7 },
    { offset: .85, opacity: .7 }, { opacity: 0 },
  ], { duration: duration + 700 });
  glow.onfinish = () => {
    flight.cancel();
    jump.remove();
    scheduleJump();
  };
}

function stopAnimation() {
  window.clearTimeout(jumpTimer);
  window.cancelAnimationFrame(frame);
  jumpTimer = 0;
  frame = 0;
  previousTime = 0;
  for (const animation of sky.getAnimations({ subtree: true })) animation.cancel();
  sky.replaceChildren();
}

function syncMotion() {
  stopAnimation();
  document.body.classList.toggle('starfield-paused', !canAnimate());
  if (reducedMotion.matches) {
    pointerX = pointerY = offsetX = offsetY = scrollOffset = 0;
    paintParallax();
  }
  if (canAnimate()) {
    requestParallax();
    scheduleJump(true);
  }
}

window.addEventListener('pointermove', (event) => {
  if (!canAnimate() || !finePointer.matches || event.pointerType !== 'mouse') return;
  pointerX = (.5 - Math.max(0, Math.min(1, event.clientX / window.innerWidth))) * 22;
  pointerY = (.5 - Math.max(0, Math.min(1, event.clientY / window.innerHeight))) * 16;
  requestParallax();
}, { passive: true });
window.addEventListener('scroll', requestParallax, { passive: true });
window.addEventListener('resize', syncMotion, { passive: true });
document.addEventListener('pointerout', (event) => { if (!event.relatedTarget) resetPointer(); });
window.addEventListener('blur', resetPointer);
finePointer.addEventListener('change', resetPointer);
reducedMotion.addEventListener('change', syncMotion);
document.addEventListener('visibilitychange', syncMotion);
window.addEventListener('pagehide', () => { suspended = true; syncMotion(); });
window.addEventListener('pageshow', () => { suspended = false; syncMotion(); });
syncMotion();
