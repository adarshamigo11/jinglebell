<?php
require_once __DIR__ . '/../includes/middleware.php';
$admin = requireAdmin();
if ($admin['role'] !== 'super_admin') { header('Location: /admin/index.php'); exit; }
$db = getDB();

$admins = $db->query("SELECT id, username, name, email, role, is_active, last_login, created_at FROM admins ORDER BY created_at ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admins — TradeZenfy</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#F5F7FA;color:#1A1A1A;min-height:100vh;overflow-x:hidden}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.page-header h1{font-size:22px;font-weight:600}
.add-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#2563EB;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.add-btn:hover{background:#1D4ED8}
.card{background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
table{width:100%;border-collapse:collapse}
th{font-size:11px;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;padding:12px 16px;text-align:left;background:#F9FAFB;font-weight:600}
td{padding:13px 16px;font-size:14px;border-top:1px solid #F3F4F6;vertical-align:middle;color:#1A1A1A}
tr:hover td{background:#F9FAFB}
.role-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
.role-super{background:#DBEAFE;color:#2563EB}
.role-admin{background:#F3F4F6;color:#6B7280}
.status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px}
.dot-active{background:#10B981}
.dot-inactive{background:#EF4444}
.action-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;border:none;margin-right:4px}
.btn-toggle{background:#F3F4F6;color:#6B7280}
.btn-toggle:hover{opacity:.8}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border:1px solid #E5E7EB;border-radius:16px;padding:28px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,0.15)}
.modal h3{font-size:17px;font-weight:600;margin-bottom:20px;color:#1A1A1A}
.form-group{margin-bottom:14px}
label{display:block;font-size:13px;color:#6B7280;margin-bottom:5px}
input,select{width:100%;padding:10px 12px;background:#F5F7FA;border:1px solid #E5E7EB;border-radius:8px;color:#1A1A1A;font-size:14px;outline:none;font-family:inherit}
input:focus,select:focus{border-color:#2563EB}
.modal-actions{display:flex;gap:10px;margin-top:18px}
.modal-btn{flex:1;padding:11px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.modal-btn.confirm{background:#2563EB;color:#fff}
.modal-btn.cancel{background:#F3F4F6;color:#6B7280}
.alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;display:none}
.alert-error{background:#FEE2E2;border:1px solid #FECACA;color:#DC2626}

/* ── Mobile Responsive ── */
@media (max-width: 768px) {
  .main { padding: 20px 16px; }
  .page-header { flex-direction: column; align-items: flex-start; gap: 8px; }
  .page-header h1 { font-size: 20px; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  th, td { padding: 10px 12px; font-size: 13px; }
  .modal { margin: 16px; padding: 20px; max-width: calc(100% - 32px); }
  .modal h3 { font-size: 17px; }
  .modal-actions { flex-direction: column; }
  .modal-btn { width: 100%; }
  .action-btn { padding: 6px 10px; font-size: 11px; }
}
@media (max-width: 480px) {
  .main { padding: 16px 12px; }
  .page-header h1 { font-size: 18px; }
  .action-btn { padding: 5px 8px; font-size: 10px; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin-header.php'; ?>

<div class="main" style="padding:32px">
  <div class="page-header">
    <h1><i class="fa fa-user-shield" style="color:#2563EB;margin-right:10px"></i>Admins</h1>
    <button class="add-btn" onclick="document.getElementById('addModal').classList.add('open')"><i class="fa fa-plus"></i> Add Admin</button>
  </div>

  <div class="card">
    <table>
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($admins as $a): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($a['name']) ?></td>
          <td style="color:#94a3b8">@<?= htmlspecialchars($a['username']) ?></td>
          <td style="font-size:13px;color:#94a3b8"><?= htmlspecialchars($a['email']) ?></td>
          <td><span class="role-badge role-<?= $a['role']==='super_admin'?'super':'admin' ?>"><?= $a['role']==='super_admin'?'Super Admin':'Admin' ?></span></td>
          <td><span class="status-dot <?= $a['is_active']?'dot-active':'dot-inactive' ?>"></span><?= $a['is_active']?'Active':'Inactive' ?></td>
          <td style="font-size:12px;color:#4b5563"><?= $a['last_login']?date('d M Y, H:i',strtotime($a['last_login'])):'Never' ?></td>
          <td>
            <?php if($a['id'] !== $admin['id']): ?>
              <button class="action-btn btn-toggle" onclick="toggleAdmin(<?= $a['id'] ?>,<?= $a['is_active'] ?>)">
                <i class="fa <?= $a['is_active']?'fa-ban':'fa-check' ?>"></i> <?= $a['is_active']?'Deactivate':'Activate' ?>
              </button>
            <?php else: ?>
              <span style="font-size:12px;color:#4b5563">(You)</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Admin Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <h3><i class="fa fa-user-shield" style="color:#6366f1;margin-right:8px"></i>Add New Admin</h3>
    <div id="addAlert" class="alert alert-error"></div>
    <div class="form-group"><label>Full Name</label><input type="text" id="aName" placeholder="Full name"></div>
    <div class="form-group"><label>Username</label><input type="text" id="aUsername" placeholder="Username (lowercase)"></div>
    <div class="form-group"><label>Email</label><input type="email" id="aEmail" placeholder="email@example.com"></div>
    <div class="form-group"><label>Password</label><input type="password" id="aPassword" placeholder="Min 8 characters"></div>
    <div class="form-group"><label>Role</label><select id="aRole"><option value="admin">Admin</option><option value="super_admin">Super Admin</option></select></div>
    <div class="modal-actions">
      <button class="modal-btn cancel" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
      <button class="modal-btn confirm" onclick="addAdmin()">Create Admin</button>
    </div>
  </div>
</div>

<script>
async function addAdmin() {
  const body = {
    name: document.getElementById('aName').value.trim(),
    username: document.getElementById('aUsername').value.trim().toLowerCase(),
    email: document.getElementById('aEmail').value.trim(),
    password: document.getElementById('aPassword').value,
    role: document.getElementById('aRole').value,
  };
  const res  = await fetch('../api/admin-create-admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const data = await res.json();
  if(data.success){location.reload();}
  else{const a=document.getElementById('addAlert');a.textContent=data.message;a.style.display='block';}
}

async function toggleAdmin(id, current) {
  if(!confirm('Toggle admin status?')) return;
  const res  = await fetch('../api/admin-toggle-admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({admin_id:id,is_active:current?0:1})});
  const data = await res.json();
  if(data.success) location.reload();
  else alert(data.message);
}
document.getElementById('addModal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open')});

</script>
</body>
</html>
