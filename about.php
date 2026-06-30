<?php $title = 'About Us | TradeZenfy'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'nav.php'; ?>

<div class="page-hero" style="position:relative;overflow:hidden;">
  <canvas id="starfield" style="position:absolute;inset:0;width:100%;height:100%;"></canvas>
  <div class="page-hero-bg"></div>
  <div style="position:relative;z-index:2;">
    <div class="page-tag">Our Story</div>
    <h1 class="page-title">Democratising<br><span class="glow-text">Wealth</span></h1>
    <p class="page-desc">We believe every Indian deserves access to the world's financial markets — without complexity, without excessive fees.</p>
  </div>
</div>

<div class="container">
  <!-- Mission -->
  <div class="mission-grid">
    <div class="mission-text">
      <div class="section-label">Our Mission</div>
      <h2>Bringing the power of markets to every household</h2>
      <p>TradeZenfy was founded on a single conviction: that the stock market should not be the exclusive domain of the privileged few. We built technology-first infrastructure to make trading as natural as sending a message.</p>
      <p>From tier-2 cities to metro trading floors, millions of Indians are discovering a new way to grow their wealth — powered by our platform.</p>
      <div style="display:flex;gap:2rem;margin-top:2rem;">
        <div><div style="font-family:'Orbitron',monospace;font-size:2rem;font-weight:800;color:#a855f7;">2015</div><div style="font-size:0.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;">Founded</div></div>
        <div><div style="font-family:'Orbitron',monospace;font-size:2rem;font-weight:800;color:#10b981;">2M+</div><div style="font-size:0.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;">Traders</div></div>
        <div><div style="font-family:'Orbitron',monospace;font-size:2rem;font-weight:800;color:#f59e0b;">₹500Cr+</div><div style="font-size:0.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.08em;">Daily Volume</div></div>
      </div>
    </div>
    <div style="position:relative;display:flex;align-items:center;justify-content:center;">
      <svg viewBox="0 0 320 320" width="320" height="320" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <radialGradient id="planet-grad" cx="38%" cy="35%"><stop offset="0%" stop-color="#7c3aed"/><stop offset="100%" stop-color="#1e1b4b"/></radialGradient>
          <radialGradient id="moon-grad" cx="38%" cy="35%"><stop offset="0%" stop-color="#f59e0b"/><stop offset="100%" stop-color="#78350f"/></radialGradient>
        </defs>
        <!-- Stars -->
        <circle cx="20" cy="40" r="1" fill="white" opacity="0.6"/>
        <circle cx="280" cy="60" r="1.5" fill="white" opacity="0.5"/>
        <circle cx="60" cy="280" r="1" fill="white" opacity="0.4"/>
        <circle cx="300" cy="250" r="1" fill="white" opacity="0.7"/>
        <circle cx="140" cy="20" r="1.5" fill="white" opacity="0.5"/>
        <!-- Orbit rings -->
        <ellipse cx="160" cy="160" rx="130" ry="40" fill="none" stroke="rgba(124,58,237,0.2)" stroke-width="1" transform="rotate(-20,160,160)"/>
        <ellipse cx="160" cy="160" rx="100" ry="30" fill="none" stroke="rgba(59,130,246,0.15)" stroke-width="1" transform="rotate(30,160,160)"/>
        <!-- Main planet -->
        <circle cx="160" cy="160" r="60" fill="url(#planet-grad)"/>
        <ellipse cx="160" cy="160" rx="88" ry="18" fill="none" stroke="rgba(167,139,250,0.5)" stroke-width="2" transform="rotate(-15,160,160)"/>
        <circle cx="160" cy="160" r="60" fill="none" stroke="rgba(167,139,250,0.15)" stroke-width="1"/>
        <!-- Craters -->
        <circle cx="145" cy="145" r="8" fill="rgba(0,0,0,0.2)"/>
        <circle cx="175" cy="170" r="5" fill="rgba(0,0,0,0.15)"/>
        <!-- Moon -->
        <circle cx="268" cy="100" r="20" fill="url(#moon-grad)"/>
        <!-- Small dots on orbit -->
        <circle cx="62" cy="148" r="4" fill="#a855f7" opacity="0.8"/>
        <circle cx="258" cy="175" r="3" fill="#3b82f6" opacity="0.7"/>
      </svg>
    </div>
  </div>

  <!-- Values -->
  <div class="section-label">What Drives Us</div>
  <h2 class="section-title" style="margin-bottom:2rem;">Our Core Values</h2>
  <div class="values-grid">
    <div class="val-card">
      <div class="val-num">01</div>
      <h3>Transparency</h3>
      <p>No hidden charges, no surprises. Every fee is disclosed upfront, always.</p>
    </div>
    <div class="val-card">
      <div class="val-num">02</div>
      <h3>Speed</h3>
      <p>Millisecond order execution. Because in markets, every microsecond counts.</p>
    </div>
    <div class="val-card">
      <div class="val-num">03</div>
      <h3>Security</h3>
      <p>Bank-grade encryption, 2FA, and SEBI-mandated safeguards on all accounts.</p>
    </div>
    <div class="val-card">
      <div class="val-num">04</div>
      <h3>Accessibility</h3>
      <p>Available in 8 regional languages. Designed for India's diverse trader base.</p>
    </div>
    <div class="val-card">
      <div class="val-num">05</div>
      <h3>Education</h3>
      <p>Free courses, webinars, and research to make every trader more confident.</p>
    </div>
    <div class="val-card">
      <div class="val-num">06</div>
      <h3>Innovation</h3>
      <p>Constantly evolving — AI insights, algo trading, and next-gen tools always in the pipeline.</p>
    </div>
  </div>

  <!-- Team -->
  <div class="team-section">
    <div class="section-label">Leadership</div>
    <h2 class="section-title">Meet the Team</h2>
    <div class="team-grid">
      <div class="team-card">
        <div class="team-avatar">🚀</div>
        <div class="team-info">
          <h4>Arjun Mehta</h4>
          <span>Chief Executive Officer</span>
        </div>
      </div>
      <div class="team-card">
        <div class="team-avatar">🌌</div>
        <div class="team-info">
          <h4>Priya Sharma</h4>
          <span>Chief Technology Officer</span>
        </div>
      </div>
      <div class="team-card">
        <div class="team-avatar">⭐</div>
        <div class="team-info">
          <h4>Rahul Gupta</h4>
          <span>Chief Financial Officer</span>
        </div>
      </div>
      <div class="team-card">
        <div class="team-avatar">🪐</div>
        <div class="team-info">
          <h4>Neha Joshi</h4>
          <span>Head of Product</span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="main.js"></script>
</body>
</html>
