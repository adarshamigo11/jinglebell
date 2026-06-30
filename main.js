// Starfield
const canvas = document.getElementById('starfield');
if (canvas) {
  const ctx = canvas.getContext('2d');
  let stars = [];
  function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  }
  function initStars() {
    stars = [];
    for (let i = 0; i < 200; i++) {
      stars.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: Math.random() * 1.5 + 0.3,
        alpha: Math.random(),
        speed: Math.random() * 0.3 + 0.05,
        twinkle: Math.random() * 0.02 + 0.005
      });
    }
  }
  function drawStars() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    stars.forEach(s => {
      s.alpha += s.twinkle * (Math.random() > 0.5 ? 1 : -1);
      s.alpha = Math.max(0.1, Math.min(1, s.alpha));
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(255,255,255,${s.alpha})`;
      ctx.fill();
    });
    requestAnimationFrame(drawStars);
  }
  resize();
  initStars();
  drawStars();
  window.addEventListener('resize', () => { resize(); initStars(); });
}

// Mini chart generator for stock table
document.querySelectorAll('.mini-chart').forEach(el => {
  const up = el.dataset.trend === 'up';
  const bars = 12;
  let val = 50;
  for (let i = 0; i < bars; i++) {
    val += (Math.random() - (up ? 0.35 : 0.65)) * 12;
    val = Math.max(15, Math.min(85, val));
    const bar = document.createElement('div');
    bar.className = 'mini-bar';
    bar.style.height = val + '%';
    bar.style.flex = '1';
    bar.style.background = up ? 'rgba(52,211,153,0.6)' : 'rgba(248,113,113,0.6)';
    bar.style.borderRadius = '2px';
    el.appendChild(bar);
  }
});

// Order tabs
document.querySelectorAll('.otab').forEach(tab => {
  tab.addEventListener('click', () => {
    const panel = tab.closest('.order-tabs');
    panel.querySelectorAll('.otab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    const btn = document.querySelector('.btn-buy-full');
    if (btn) {
      if (tab.classList.contains('sell')) {
        btn.style.background = 'var(--red)';
        btn.textContent = 'PLACE SELL ORDER';
      } else {
        btn.style.background = 'var(--green)';
        btn.textContent = 'PLACE BUY ORDER';
      }
    }
  });
});

// Filter tabs
document.querySelectorAll('.ftab').forEach(tab => {
  tab.addEventListener('click', () => {
    tab.closest('.filter-tabs').querySelectorAll('.ftab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
  });
});

// Market chart
const chartCanvas = document.getElementById('marketChart');
if (chartCanvas) {
  const ctx2 = chartCanvas.getContext('2d');
  const w = chartCanvas.width = chartCanvas.offsetWidth;
  const h = chartCanvas.height = 280;
  let prices = [2800];
  for (let i = 1; i < 80; i++) {
    prices.push(Math.max(2600, prices[i-1] + (Math.random()-0.42)*30));
  }
  const min = Math.min(...prices) - 30;
  const max = Math.max(...prices) + 30;
  const toY = v => h - ((v - min) / (max - min)) * h * 0.9 - h * 0.05;
  const toX = i => (i / (prices.length - 1)) * w;

  // Gradient fill
  const grad = ctx2.createLinearGradient(0, 0, 0, h);
  grad.addColorStop(0, 'rgba(52,211,153,0.25)');
  grad.addColorStop(1, 'rgba(52,211,153,0)');
  ctx2.beginPath();
  ctx2.moveTo(toX(0), toY(prices[0]));
  prices.forEach((p, i) => ctx2.lineTo(toX(i), toY(p)));
  ctx2.lineTo(w, h);
  ctx2.lineTo(0, h);
  ctx2.closePath();
  ctx2.fillStyle = grad;
  ctx2.fill();

  // Line
  ctx2.beginPath();
  ctx2.moveTo(toX(0), toY(prices[0]));
  prices.forEach((p, i) => ctx2.lineTo(toX(i), toY(p)));
  ctx2.strokeStyle = '#10b981';
  ctx2.lineWidth = 2;
  ctx2.stroke();
}
