// TomYum: cat stampede. Type `:3` anywhere on the site (outside of a text input) and a handful of
// cats bounce across the viewport. Fires at most once every few seconds and never shows up in a
// screen reader (aria-hidden, pointer-events: none, decorative only).

const trigger = ':3';
const spawnDelayMs = 60;
const cats = ['🐱', '😺', '😸', '🙀', '😻', '🐈', '🐾'];
const minLive = 2500;
const maxLive = 4000;
const cooldownMs = 1500;

let buffer = '';
let lastKey = 0;
let onCooldown = false;

function shouldIgnore(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) {
    return false;
  }
  if (target.isContentEditable) {
    return true;
  }
  const tag = target.tagName;
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
}

function random(low: number, high: number): number {
  return low + Math.random() * (high - low);
}

function spawnOne(index: number) {
  const el = document.createElement('div');
  el.textContent = cats[Math.floor(Math.random() * cats.length)];
  el.setAttribute('aria-hidden', 'true');
  el.style.cssText = [
    'position: fixed',
    `top: ${random(10, 85)}vh`,
    'left: -4rem',
    `font-size: ${random(1.4, 2.8).toFixed(2)}rem`,
    `transform: rotate(${random(-15, 15).toFixed(1)}deg)`,
    'pointer-events: none',
    'user-select: none',
    'z-index: 2147483646',
    'transition: left linear, opacity 400ms ease-in',
    'will-change: left, opacity',
  ].join('; ');
  document.body.appendChild(el);

  const lifetime = random(minLive, maxLive);
  // Separate frame so the browser picks up the starting position before transitioning.
  requestAnimationFrame(() => {
    el.style.transitionDuration = `${lifetime}ms, 400ms`;
    el.style.left = `calc(100vw + ${random(2, 6).toFixed(1)}rem)`;
  });

  window.setTimeout(() => { el.style.opacity = '0'; }, lifetime - 400);
  window.setTimeout(() => { el.remove(); }, lifetime + 50);

  if (index === 0) {
    // Only the first spawn starts the cooldown — one burst per :3.
    onCooldown = true;
    window.setTimeout(() => { onCooldown = false; }, cooldownMs + lifetime);
  }
}

function stampede() {
  const count = 5 + Math.floor(Math.random() * 3);
  for (let i = 0; i < count; i++) {
    window.setTimeout(() => spawnOne(i), i * spawnDelayMs);
  }
}

function onKey(event: KeyboardEvent) {
  if (event.key.length !== 1 || shouldIgnore(event.target) || onCooldown) {
    return;
  }
  // Any pause between keys resets the match (so unrelated keystrokes don't accumulate).
  const now = event.timeStamp;
  if (now - lastKey > 800) {
    buffer = '';
  }
  lastKey = now;
  buffer = (buffer + event.key).slice(-trigger.length);
  if (buffer === trigger) {
    buffer = '';
    stampede();
  }
}

if (typeof window !== 'undefined') {
  window.addEventListener('keydown', onKey, { passive: true });
}
