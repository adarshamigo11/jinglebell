<?php
$title = 'Contact Us | TradeZenfy';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Process form - integrate your mailer here
  $name    = htmlspecialchars($_POST['name'] ?? '');
  $email   = htmlspecialchars($_POST['email'] ?? '');
  $subject = htmlspecialchars($_POST['subject'] ?? '');
  $message = htmlspecialchars($_POST['message'] ?? '');
  if ($name && $email && $message) $success = true;
}
?>
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

<div style="position:relative;overflow:hidden;">
  <canvas id="starfield" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;"></canvas>
  <div class="page-hero-bg"></div>
  <div class="container" style="position:relative;z-index:2;padding-top:8rem;">
    <div class="page-tag">Get In Touch</div>
    <h1 class="page-title" style="margin-bottom:0.5rem;">Contact<br><span class="glow-text">Mission Control</span></h1>
    <p class="page-desc" style="text-align:left;margin:0 0 0;">Our support crew is on standby 24/7. Reach out — we're here.</p>

    <div class="contact-layout">
      <!-- Info -->
      <div class="contact-info">
        <h2 style="margin-top:2rem;">We're just a<br>signal away</h2>
        <p>Whether it's an account query, technical issue, or partnership proposal — your message lands directly with our team.</p>
        <div class="contact-items">
          <div class="c-item">
            <div class="c-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.54 7.09 19.79 19.79 0 01.46 3a2 2 0 012-2.18h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.91 8.34a16 16 0 006.24 6.24l.79-.79a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
              </svg>
            </div>
            <div class="c-text">
              <h4>Phone</h4>
              <p>+91 800 000 0000</p>
            </div>
          </div>
          <div class="c-item">
            <div class="c-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
              </svg>
            </div>
            <div class="c-text">
              <h4>Email</h4>
              <p>support@yourcompany.in</p>
            </div>
          </div>
          <div class="c-item">
            <div class="c-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
              </svg>
            </div>
            <div class="c-text">
              <h4>Office</h4>
              <p>TradeZenfy HQ<br>Mumbai, Maharashtra — 400001</p>
            </div>
          </div>
          <div class="c-item">
            <div class="c-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
            </div>
            <div class="c-text">
              <h4>Support Hours</h4>
              <p>Mon–Fri: 9:00 AM – 6:00 PM<br>Trading Desk: 24x7</p>
            </div>
          </div>
        </div>

        <!-- Social icons -->
        <div style="display:flex;gap:0.75rem;margin-top:2.5rem;">
          <?php $socials = ['Twitter/X','LinkedIn','YouTube','Telegram']; ?>
          <?php foreach($socials as $s): ?>
          <a href="#" style="width:40px;height:40px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.65rem;color:var(--muted);transition:all 0.2s;text-align:center;line-height:1.2;" title="<?= $s ?>"><?= implode('<br>',str_split(strtoupper(substr($s,0,2)))) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Form -->
      <div class="contact-form-wrap">
        <?php if ($success): ?>
          <div style="text-align:center;padding:3rem 0;">
            <div style="font-size:3rem;margin-bottom:1rem;">🚀</div>
            <h3 style="font-family:'Orbitron',monospace;color:#34d399;margin-bottom:0.75rem;">Message Launched!</h3>
            <p style="color:var(--muted);">We've received your message and will respond within 24 hours.</p>
          </div>
        <?php else: ?>
          <h3>Send a Message</h3>
          <form method="POST" action="">
            <div class="form-row">
              <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Your name" required>
              </div>
              <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="your@email.com" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" placeholder="+91 00000 00000">
              </div>
              <div class="form-group">
                <label>Topic</label>
                <select name="subject">
                  <option value="">Select topic...</option>
                  <option>Account Opening</option>
                  <option>Trading Support</option>
                  <option>Fund Transfer</option>
                  <option>Technical Issue</option>
                  <option>Partnership</option>
                  <option>Other</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Message</label>
              <textarea name="message" rows="5" placeholder="Describe your query in detail..." required></textarea>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;text-align:center;border:none;">Launch Message &rarr;</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="main.js"></script>
</body>
</html>
