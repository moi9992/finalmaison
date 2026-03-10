<?php
require '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Whale Raffle - Hyper Business 🎰</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/projet/assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow+Condensed:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
  --bg: #212529; --panel: #10111a; --border: #1e2035;
  --gold: #f5c842; --gold2: #e8a800; --red: #ff3a5c;
  --green: #00e87a; --blue: #4fc3ff; --text: #c8cfe0; --muted: #4a5068; --white: #eef0f8;
}
body { font-family: 'Barlow Condensed', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; display: flex; flex-direction: column; }
body::before { content: ''; position: fixed; inset: 0; background-image: linear-gradient(rgba(79,195,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(79,195,255,0.03) 1px, transparent 1px); background-size: 40px 40px; z-index: 0; }

.wrap { position: relative; z-index: 1; max-width: 980px; margin: 0 auto; padding: 30px 20px; }
.header { text-align: center; margin-bottom: 25px; user-select: none; }
.header h1 { font-family: 'Bebas Neue'; font-size: 90px; color: var(--white); line-height: 0.9; }
.header span { color: var(--gold); }

.top-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
.stats-full { background: linear-gradient(180deg, rgba(0,232,122,0.15) 0%, rgba(0,0,0,0) 100%); border: 2px solid var(--green); padding: 20px; border-radius: 15px; text-align: center; }
.stat-val-huge { font-family: 'Bebas Neue'; font-size: 60px; color: var(--green); line-height: 1; }

.win-code-panel { background: linear-gradient(180deg, rgba(245,200,66,0.15) 0%, rgba(0,0,0,0) 100%); border: 2px solid var(--gold); padding: 20px; border-radius: 15px; text-align: center; }
.win-code-display { font-family: 'JetBrains Mono'; font-size: 32px; color: var(--gold); letter-spacing: 5px; font-weight: bold; margin-top: 10px; }

.wallet-panel { background: var(--panel); border: 1px solid var(--gold2); border-radius: 12px; padding: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
.balance-val { font-family: 'Bebas Neue'; font-size: 52px; color: var(--white); }

.shop-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 25px; }
.shop-item { background: var(--panel); padding: 15px; border-radius: 12px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 10px; }
.btn-buy { width: 100%; background: rgba(79,195,255,0.1); border: 1px solid var(--blue); color: var(--blue); padding: 12px; border-radius: 6px; font-family: 'Bebas Neue'; cursor: pointer; font-size: 22px; }
.btn-promo-direct { width: 100%; background: linear-gradient(135deg, var(--gold), var(--gold2)); color: #000; border: none; padding: 12px; border-radius: 6px; font-family: 'Bebas Neue'; cursor: pointer; font-size: 20px; font-weight: bold; }

.slots-container { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 30px; margin-bottom: 25px; }
.row-align { display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 25px; }
.pick-input { width: 75px; height: 90px; background: #000; border: 2px solid var(--border); border-radius: 10px; color: var(--white); font-family: 'JetBrains Mono'; font-size: 40px; text-align: center; outline: none; }
.qty-input { width: 110px; background: #000; border: 2px solid var(--border); color: var(--gold); padding: 12px; border-radius: 8px; font-family: 'JetBrains Mono'; font-size: 24px; text-align: center; }
.btn-draw { background: linear-gradient(135deg, #1a1f3a, #252b4a); color: var(--gold); border: 2px solid var(--gold2); padding: 20px 40px; font-family: 'Bebas Neue'; font-size: 32px; border-radius: 6px; cursor: pointer; height: 85px; align-self: flex-end;}
.btn-reset-small { background: rgba(255, 58, 92, 0.05); border: 1px solid rgba(255, 58, 92, 0.3); color: var(--red); padding: 5px 10px; font-family: 'Bebas Neue'; font-size: 13px; border-radius: 4px; cursor: pointer; }

/* HISTORIQUE ET TRIS */
.history-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding: 0 5px; }
.filter-group { display: flex; background: #000; border: 1px solid var(--border); padding: 3px; border-radius: 8px; }
.filter-btn { background: transparent; border: none; color: var(--muted); padding: 6px 15px; font-family: 'Bebas Neue'; font-size: 16px; cursor: pointer; border-radius: 5px; }
.filter-btn.active { background: var(--border); color: var(--white); }

.history-panel { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 10px; height: 350px; overflow-y: auto; }
#historyList { display: flex; flex-direction: column; }

#historyList .history-item { order: 0; }
[data-view="win"] #historyList .history-item { order: var(--win-order); }

.history-item { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-family: 'JetBrains Mono'; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }

[data-view="win"] .history-item[data-type="loose"] { display: none; }
[data-view="loose"] .history-item[data-type="win"] { display: none; }

#confirmModal, #notifModal { display: none; position: fixed; inset: 0; z-index: 999; background: rgba(0,0,0,0.9); backdrop-filter: blur(10px); align-items: center; justify-content: center; }
.modal-content { background: var(--panel); border: 2px solid var(--gold); padding: 40px; border-radius: 20px; text-align: center; }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="wrap">
  <div class="header"><h1>Hourly RAFFLE</h1></div>

  <!-- Jackpot banner -->
  <div style="background: linear-gradient(135deg, rgba(245,200,66,0.08), rgba(232,168,0,0.15)); border: 2px solid var(--gold2); border-radius: 14px; padding: 18px 28px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
    <div>
      <div style="font-size: 11px; font-weight: 800; color: var(--gold2); letter-spacing: 2px; text-transform: uppercase;">🏆 Jackpot à remporter</div>
      <div style="font-family: 'Bebas Neue'; font-size: 52px; color: var(--gold); line-height: 1; text-shadow: 0 0 30px rgba(245,200,66,0.5);">25 000 <span style="font-size: 22px; color: var(--gold2);">JULIENTONS</span></div>
      <div style="font-size: 13px; color: var(--muted); margin-top: 4px;">Trouvez la combinaison parfaite 4 boules + ★ et remportez le jackpot.</div>
    </div>
    <div style="text-align: center;">
      <div style="font-size: 11px; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">Meilleure offre</div>
      <button class="btn-promo-direct" onclick="askPromo('whale')" style="font-size: 16px; padding: 14px 28px; border-radius: 8px; white-space: nowrap;">
        🐋 WHALE PACK — 400 tickets / 12 000 J
      </button>
      <div style="font-size: 11px; color: var(--gold2); margin-top: 6px;">+ bonus offert toutes les 3 commandes</div>
    </div>
  </div>

  <div class="top-grid">
      <div class="stats-full">
          <div style="color:var(--green); font-size: 12px; font-weight: bold;">CASH COLLECTÉ</div>
          <div class="stat-val-huge" id="statGains">0 J</div>
      </div>
      <div class="win-code-panel">
          <div style="color:var(--gold); font-size: 12px; font-weight: bold;">NUMÉRO GAGNANT</div>
          <div class="win-code-display" id="winningCodeDisplay">?-?-?-?-?</div>
      </div>
  </div>

  <div class="wallet-panel">
    <div><div style="color:var(--gold); font-size:12px;">SOLDE BANQUAIRE</div><div class="balance-val" id="balanceDisplay"><?= isset($_SESSION['user']['forum_gold']) ? (int)$_SESSION['user']['forum_gold'] : 0 ?> <span>J</span></div></div>
    <div class="ticket-display" style="text-align:right">
        <div style="font-size: 11px; color: var(--blue);">TICKETS DISPOS</div>
        <div id="ticketCount" style="color:var(--white); font-size: 38px; font-family: 'Bebas Neue';">0</div>
    </div>
  </div>

  <div class="shop-row">
      <div class="shop-item">
          <div class="item-name">TICKET SOLO</div>
          <button class="btn-buy" onclick="buyTickets('solo', 1, 50)">1 TICKET (50 J)</button>
          <button class="btn-promo-direct" onclick="askPromo('solo')">promo solo (500 J)</button>
      </div>
      <div class="shop-item">
          <div class="item-name">PACK 10</div>
          <button class="btn-buy" onclick="buyTickets('pack10', 10, 450)">PACK 10 (450 J)</button>
          <button class="btn-promo-direct" onclick="askPromo('pack10')">PACK promo (2250 J)</button>
      </div>
      <div class="shop-item">
          <div class="item-name">WHALE PACK</div>
          <button class="btn-buy" style="border-color:var(--gold); color:var(--gold)" onclick="buyTickets('whale', 100, 4000)">100 TICKET (4000 J)</button>
          <button class="btn-promo-direct" onclick="askPromo('whale')">PACK whale (12000 J)</button>
      </div>
  </div>

  <div class="slots-container">
    <div class="row-align">
        <div style="display:flex; gap:10px;">
            <input class="pick-input" id="p0" type="text" maxlength="1" placeholder="?">
            <input class="pick-input" id="p1" type="text" maxlength="1" placeholder="?">
            <input class="pick-input" id="p2" type="text" maxlength="1" placeholder="?">
            <input class="pick-input" id="p3" type="text" maxlength="1" placeholder="?">
            <input class="pick-input" id="p4" type="text" maxlength="1" placeholder="★" style="border-color:var(--gold2)">
        </div>
        <button class="btn-reset-small" onclick="resetNumbers()">RESET NUMS</button>
    </div>

    <div class="row-align" style="align-items: flex-end;">
        <div style="display:flex; flex-direction:column; align-items:center; gap:8px;">
            <span style="font-size: 10px; color:var(--muted)">SÉRIE</span>
            <input type="text" id="drawQty" value="1" class="qty-input">
            <div style="display:flex; gap:5px; width:100%">
                <button class="btn-reset-small" onclick="resetQty()" style="flex:1">RESET</button>
                <button class="btn-reset-small" onclick="setMaxQty()" style="flex:1; border-color:rgba(79,195,255,0.3); color:var(--blue)">MAX</button>
            </div>
        </div>
        <button id="mainDrawBtn" onclick="startCustomDraw()" class="btn-draw">LANCER LA SÉRIE</button>
    </div>
  </div>

  <div class="history-controls">
      <span style="font-family: 'Bebas Neue'; color: var(--muted); font-size: 14px; letter-spacing: 1px;">HISTORIQUE DES TIRAGES</span>
      <div class="filter-group">
          <button class="filter-btn active" data-filter="all" onclick="setFilter('all')">TOUT</button>
          <button class="filter-btn" data-filter="win" onclick="setFilter('win')">WINS (TRI GAINS)</button>
          <button class="filter-btn" data-filter="loose" onclick="setFilter('loose')">LOSSES</button>
      </div>
  </div>

  <div id="historyPanel" class="history-panel" data-view="all"><div id="historyList"></div></div>
</div>

<div id="confirmModal"><div class="modal-content"><div id="mTitle" style="font-family:'Bebas Neue'; font-size:45px; color:var(--gold)">CONFIRMER</div><div id="mDesc" style="font-size:20px; margin:20px 0; color:var(--white)"></div><button id="mConfirm" style="background:var(--gold); padding:10px 20px; border:none; font-family:'Bebas Neue'; font-size:20px; cursor:pointer;">VALIDER</button><button onclick="closeModal('confirmModal')" style="background:transparent; color:var(--muted); border:none; margin-left:15px; cursor:pointer;">ANNULER</button></div></div>
<div id="notifModal"><div class="modal-content"><div id="nTitle" style="font-family:'Bebas Neue'; font-size:45px; color:var(--gold)">OFFRE</div><div id="nDesc" style="font-size:18px; margin:20px 0; color:var(--white)"></div><button onclick="closeModal('notifModal')" style="background:var(--gold); padding:10px 20px; border:none; width:100%; cursor:pointer;">OK</button></div></div>

<?php include '../includes/footer.php'; ?>

<script>
let balance = 0; let ticketsOwned = 0; let sessionGains = 0;
let isRolling = false; let promoSolo = 0; let promoPack = 0; let promoWhale = 0;

function updateUI() {
    document.getElementById('balanceDisplay').innerHTML = `${balance} <span>J</span>`;
    document.getElementById('ticketCount').textContent = ticketsOwned;
    document.getElementById('mainDrawBtn').disabled = (isRolling || ticketsOwned <= 0);
}

async function apiCall(data, deferUI = false) {
    const form = new FormData();
    for (const [k, v] of Object.entries(data)) form.append(k, v);
    const res = await fetch('api.php', { method: 'POST', body: form });
    const json = await res.json();
    if (json.error) { alert(json.error); return null; }
    if (!deferUI) {
        if (json.balance !== undefined) balance = json.balance;
        if (json.tickets !== undefined) ticketsOwned = json.tickets;
        updateUI();
        if (typeof updateBalance === 'function') updateBalance();
    }
    return json;
}

// Load state from server on page load
apiCall({ action: 'get_state' });

function setFilter(type) {
    document.getElementById('historyPanel').setAttribute('data-view', type);
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-filter') === type));
}

function resetNumbers() { document.querySelectorAll('.pick-input').forEach(input => input.value = ''); document.getElementById('p0').focus(); }
function resetQty() { document.getElementById('drawQty').value = '1'; }
function setMaxQty() { document.getElementById('drawQty').value = ticketsOwned > 0 ? ticketsOwned : '1'; }

document.querySelectorAll('.pick-input').forEach((input, idx, array) => {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^1-9]/g, '');
        if (this.value.length === 1 && idx < array.length - 1) array[idx + 1].focus();
    });
});

document.getElementById('drawQty').addEventListener('input', function() {
    let val = this.value.replace(/[^0-9]/g, '');
    if (val.startsWith('0') && val.length > 1) val = val.substring(1);
    if (val !== '' && parseInt(val) > 999) val = '999';
    this.value = val;
});

async function buyTickets(type, qty, price) {
    await apiCall({ action: 'buy', type: type });
}

function askPromo(type) {
    let apiType, text;
    if(type==='solo'){ apiType='promo_solo'; text="Pack fidélité : 11 tickets pour 500 J ?"; }
    if(type==='pack10'){ apiType='promo_pack'; text="Pack 60 tickets pour 2250 J ?"; }
    if(type==='whale'){ apiType='promo_whale'; text="Offre Ultime : 400 tickets pour 12000 J ?"; }
    document.getElementById('mDesc').textContent = text;
    document.getElementById('confirmModal').style.display = "flex";
    document.getElementById('mConfirm').onclick = async () => {
        closeModal('confirmModal');
        const res = await apiCall({ action: 'buy', type: apiType });
        if (res) notify("SUCCÈS", "Tickets ajoutés !");
    };
}

function notify(t, d) { document.getElementById('nTitle').textContent = t; document.getElementById('nDesc').textContent = d; document.getElementById('notifModal').style.display = "flex"; }
function closeModal(id) { document.getElementById(id).style.display = "none"; }

async function startCustomDraw() {
    const qty = parseInt(document.getElementById('drawQty').value) || 0;
    const picks = [0,1,2,3,4].map(i => parseInt(document.getElementById('p'+i).value));
    if(qty > ticketsOwned || qty <= 0 || picks.some(isNaN)) return;

    isRolling = true;
    updateUI();

    const res = await apiCall({ action: 'draw', qty: qty, picks: JSON.stringify(picks) }, true);
    if (!res) { isRolling = false; updateUI(); return; }

    document.getElementById('historyList').innerHTML = "";
    const target = res.target;
    document.getElementById('winningCodeDisplay').textContent = target.join('-');

    for(let i = 0; i < res.results.length; i++) {
        await new Promise(r => setTimeout(r, 40));
        const { draw, gain } = res.results[i];

        sessionGains += gain;
        document.getElementById('statGains').textContent = sessionGains + " J";

        const it = document.createElement('div');
        it.className = "history-item";

        if(gain > 0) {
            it.setAttribute('data-type', 'win');
            it.style.setProperty('--win-order', -gain);
        } else {
            it.setAttribute('data-type', 'loose');
        }

        const color = gain > 0 ? 'var(--green)' : 'var(--red)';
        it.innerHTML = `
            <span>Tirage #${i+1} : <b style="color:var(--white)">[${draw.join('·')}]</b></span>
            <span style="color:${color}; font-weight:bold">${gain > 0 ? '+' + gain : '-50'} J</span>
        `;
        document.getElementById('historyList').prepend(it);
    }
    // Appliquer le solde final seulement après l'animation
    if (res.balance !== undefined) balance = res.balance;
    if (res.tickets !== undefined) ticketsOwned = res.tickets;
    isRolling = false;
    updateUI();
    if (typeof updateBalance === 'function') updateBalance();
}

updateUI();
</script>
</body>
</html>
