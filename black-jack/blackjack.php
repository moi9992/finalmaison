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
<title>BlackJack Elite - Immersion Totale 🃏</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/projet/assets/css/style.css">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow+Condensed:wght@400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
  --bg: #212529; --panel: rgba(10, 11, 20, 0.98); --border: #1e2035;
  --gold: #f5c842; --gold2: #e8a800; --red: #ff3a5c;
  --green: #00e87a; --blue: #4fc3ff; --purple: #a34cff; --text: #c8cfe0; --muted: #4a5068; --white: #eef0f8;
  --table-border: #1e2035;
}

body {
    font-family: 'Barlow Condensed', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    transition: background 0.8s ease-in-out;
    position: relative;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
}

.game-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
}

#bg-overlay {
    position: fixed; inset: 0; z-index: -1;
    transition: 0.8s ease;
}

/* --- FIX TABLE BLEUE (UNDERGROUND) --- */
body.bg-underground { background-color: #081020; }
body.bg-underground #bg-overlay {
    background:
        radial-gradient(circle at 50% 50%, rgba(79, 195, 255, 0.4) 0%, transparent 75%),
        radial-gradient(circle at 10% 10%, rgba(79, 195, 255, 0.2) 0%, transparent 40%),
        url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
    opacity: 1;
}
body.bg-underground .table { box-shadow: 0 0 100px rgba(79, 195, 255, 0.4); border-color: var(--blue); }

/* --- TABLE BUSINESS (OR) --- */
body.bg-business { background-color: #120f05; }
body.bg-business #bg-overlay {
    background:
        radial-gradient(circle at 50% 50%, rgba(245, 200, 66, 0.25) 0%, transparent 70%),
        url('https://www.transparenttextures.com/patterns/brushed-alum.png');
}
body.bg-business .table { box-shadow: 0 0 80px rgba(245, 200, 66, 0.3); border-color: var(--gold); }

/* --- TABLE WHALE (VIOLET) --- */
body.bg-whale { background-color: #0a0515; }
body.bg-whale #bg-overlay {
    background:
        radial-gradient(circle at 50% 50%, rgba(163, 76, 255, 0.4) 0%, transparent 75%),
        url('https://www.transparenttextures.com/patterns/stardust.png');
}
body.bg-whale .table { box-shadow: 0 0 100px rgba(163, 76, 255, 0.4); border-color: var(--purple); }

.game-container { width: 100%; max-width: 1000px; position: relative; z-index: 1; }

.table-selector { display: flex; gap: 10px; margin-bottom: 20px; justify-content: center; }
.tab-btn {
    background: var(--panel); border: 1px solid var(--border); color: var(--muted);
    padding: 10px 25px; font-family: 'Bebas Neue'; font-size: 20px; cursor: pointer; border-radius: 8px; transition: 0.3s;
}
.tab-btn.active { background: rgba(255,255,255,0.05); }
.tab-btn.active[data-table="underground"] { color: var(--blue); border-color: var(--blue); }
.tab-btn.active[data-table="business"] { color: var(--gold); border-color: var(--gold); }
.tab-btn.active[data-table="whale"] { color: var(--purple); border-color: var(--purple); }

.wallet-panel {
    background: var(--panel); border: 1px solid var(--border); padding: 15px 30px;
    border-radius: 12px; display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; border-left: 5px solid var(--blue); transition: 0.5s;
    backdrop-filter: blur(15px);
}
.balance-val { font-family: 'Bebas Neue'; font-size: 45px; color: var(--white); }

.game-table {
    background: radial-gradient(circle at center, rgba(15,35,15,0.92) 0%, rgba(5,5,10,0.98) 100%);
    border: 8px solid var(--table-border); border-radius: 40px;
    padding: 40px; position: relative;
    min-height: 420px; display: flex; flex-direction: row;
    justify-content: space-around; align-items: center; gap: 20px;
    transition: 0.5s; border-bottom: 15px solid rgba(0,0,0,0.7);
}

.hand-zone { display: flex; flex-direction: column; align-items: center; gap: 20px; flex: 1; }
.cards-display { display: flex; justify-content: center; min-height: 140px; min-width: 220px; padding-left: 45px; }

.score-badge { background: rgba(0,0,0,0.9); padding: 10px 25px; border-radius: 30px; font-family: 'JetBrains Mono'; color: var(--gold); font-weight: bold; font-size: 22px; border: 1px solid rgba(255,255,255,0.1); }

.card {
    width: 90px; height: 130px; background: white; border-radius: 10px; color: black;
    display: flex; flex-direction: column; justify-content: space-between; padding: 10px;
    font-family: 'JetBrains Mono'; font-weight: bold; font-size: 24px;
    box-shadow: -5px 0 15px rgba(0,0,0,0.5); position: relative;
    margin-left: -45px; transition: 0.3s;
}
.card.red { color: var(--red); }
.card.hidden { background: linear-gradient(135deg, #1e2035 25%, #10111a 100%); color: transparent; border: 2px solid var(--muted); }
.card.animate-card { animation: drawCard 0.4s ease-out; }

@keyframes drawCard { from { transform: translate(150px, -80px) rotate(30deg); opacity: 0; } to { transform: translate(0, 0) rotate(0); opacity: 1; } }

.controls { margin-top: 30px; display: flex; justify-content: center; gap: 15px; }
.btn-game { font-family: 'Bebas Neue'; font-size: 34px; padding: 12px 45px; border-radius: 6px; cursor: pointer; border: none; transition: 0.2s; box-shadow: 0 4px 0 rgba(0,0,0,0.3); }
.btn-game:active { transform: translateY(3px); box-shadow: none; }
.btn-game:disabled { opacity: 0.1; cursor: not-allowed; filter: grayscale(1); }

.chip-group { display: flex; gap: 8px; margin-top: 5px; }
.chip {
    background: #000; border: 1px solid var(--border); color: var(--white);
    padding: 8px 18px; border-radius: 6px; cursor: pointer; font-family: 'JetBrains Mono'; font-weight: bold; font-size: 14px;
}
.chip:hover:not(:disabled) { border-color: var(--white); background: #111; }

#msg { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-family: 'Bebas Neue'; font-size: 120px; text-shadow: 0 0 60px rgba(0,0,0,1); text-align: center; pointer-events: none; z-index: 100; display: none; }
</style>
</head>
<body class="bg-underground">

<?php include '../includes/header.php'; ?>

<div id="bg-overlay"></div>

<div class="game-wrapper">
<div class="game-container">
    <div class="table-selector">
        <button class="tab-btn active" data-table="underground" onclick="switchTable('underground')">UNDERGROUND (10J)</button>
        <button class="tab-btn" data-table="business" onclick="switchTable('business')">BUSINESS (100J)</button>
        <button class="tab-btn" data-table="whale" onclick="switchTable('whale')">ELITE WHALE (1000J)</button>
    </div>

    <div class="wallet-panel" id="wallet-ui">
        <div>
            <div id="table-label" style="color:var(--blue); font-size:12px; font-weight: bold; letter-spacing: 2px;">TABLE UNDERGROUND</div>
            <div class="balance-val" id="balance"><?= isset($_SESSION['user']['forum_gold']) ? (int)$_SESSION['user']['forum_gold'] : 0 ?> J</div>
        </div>
        <div style="text-align:right">
            <div style="color:var(--muted); font-size:12px; margin-bottom:5px; font-weight: bold;">MISE : <span id="betValueDisplay" style="color:var(--white)">0</span> J</div>
            <div class="chip-group">
                <button class="chip" onclick="setMinBet()" style="color:var(--blue); border-color:var(--blue)">MIN</button>
                <button class="chip" onclick="addBet(10)">+10</button>
                <button class="chip" onclick="addBet(100)">+100</button>
                <button class="chip" onclick="allIn()" style="color:var(--gold); border-color:var(--gold)">MAX</button>
                <button class="chip" onclick="resetBet()" style="color:var(--red); border-color:var(--red)">CLR</button>
            </div>
        </div>
    </div>

    <div class="game-table" id="game-table">
        <div id="msg">GAGNÉ !</div>

        <div class="hand-zone">
            <div class="score-badge">CROUPIER: <span id="dealer-score">?</span></div>
            <div id="dealer-cards" class="cards-display"></div>
        </div>

        <div style="width: 2px; height: 200px; background: linear-gradient(transparent, rgba(255,255,255,0.2), transparent);"></div>

        <div class="hand-zone">
            <div id="player-cards" class="cards-display"></div>
            <div class="score-badge">VOUS: <span id="player-score">0</span></div>
        </div>
    </div>

    <div class="controls">
        <button id="btn-deal" class="btn-game" style="background:var(--gold)" onclick="deal()">DISTRIBUER</button>
        <button id="btn-hit" class="btn-game" style="background:var(--blue)" onclick="hit()" disabled>CARTE</button>
        <button id="btn-double" class="btn-game" style="background:var(--gold2)" onclick="doubleDown()" disabled>DOUBLE</button>
        <button id="btn-stand" class="btn-game" style="background:var(--white)" onclick="stand()" disabled>RESTER</button>
    </div>
</div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
let balance = <?= isset($_SESSION['user']['forum_gold']) ? (int)$_SESSION['user']['forum_gold'] : 0 ?>;
let currentBet = 0;
let minBet = 10;
let deck = [];
let playerHand = [];
let dealerHand = [];
let gameState = 'betting';

const tableConfigs = {
    underground: { min: 10, color: '#4fc3ff', label: 'TABLE UNDERGROUND' },
    business: { min: 100, color: '#f5c842', label: 'BUSINESS CLUB' },
    whale: { min: 1000, color: '#a34cff', label: 'ELITE WHALE ROOM' }
};

function switchTable(type) {
    if (gameState !== 'betting') return;
    const conf = tableConfigs[type];
    minBet = conf.min;
    document.body.className = "bg-" + type;
    document.getElementById('table-label').style.color = conf.color;
    document.getElementById('table-label').textContent = conf.label;
    document.getElementById('wallet-ui').style.borderLeftColor = conf.color;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-table="${type}"]`).classList.add('active');
    resetBet();
}

function setMinBet() {
    if (gameState !== 'betting') return;
    if (balance < minBet) return;
    currentBet = minBet;
    updateUI();
}

function addBet(amount) {
    if (gameState !== 'betting') return;
    if (currentBet + amount <= balance) { currentBet += amount; updateUI(); }
}

function allIn() {
    if (gameState !== 'betting') return;
    currentBet = balance;
    updateUI();
}

function resetBet() {
    if (gameState !== 'betting') return;
    currentBet = 0;
    updateUI();
}

function createDeck() {
    const suits = ['♠', '♣', '♥', '♦'];
    const values = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
    deck = [];
    for(let suit of suits) {
        for(let value of values) {
            deck.push({suit, value, color: (suit === '♥' || suit === '♦') ? 'red' : 'black'});
        }
    }
    deck = deck.sort(() => Math.random() - 0.5);
}

function getCardValue(card) {
    if (['J', 'Q', 'K'].includes(card.value)) return 10;
    if (card.value === 'A') return 11;
    return parseInt(card.value);
}

function calculateScore(hand) {
    let score = hand.reduce((sum, card) => sum + getCardValue(card), 0);
    let aces = hand.filter(c => c.value === 'A').length;
    while (score > 21 && aces > 0) { score -= 10; aces--; }
    return score;
}

function renderCard(card, isHidden = false, animate = false) {
    const div = document.createElement('div');
    div.className = `card ${card.color} ${isHidden ? 'hidden' : ''} ${animate ? 'animate-card' : ''}`;
    if(!isHidden) {
        div.innerHTML = `<div>${card.value}</div><div style="align-self:center; font-size:40px">${card.suit}</div><div style="align-self:flex-end; transform:rotate(180deg)">${card.value}</div>`;
    }
    return div;
}

function deal() {
    if (currentBet < minBet || currentBet > balance) return;
    balance -= currentBet;
    gameState = 'playing';
    document.getElementById('msg').style.display = 'none';
    createDeck();
    playerHand = [deck.pop(), deck.pop()];
    dealerHand = [deck.pop(), deck.pop()];
    updateUI();
    if (typeof updateBalance === 'function') {
        document.getElementById('header-balance').textContent =
            Number(balance).toLocaleString();
    }

    // Check blackjack naturel
    if (calculateScore(playerHand) === 21) {
        balance += Math.floor(currentBet * 2.5);
        const msg = document.getElementById('msg');
        msg.textContent = "BLACKJACK !";
        msg.style.display = 'block';
        msg.style.color = "var(--gold)";
        gameState = 'betting';
        updateUI();

        const form = new FormData();
        form.append('action', 'finish');
        form.append('bet', currentBet);
        form.append('result', 'blackjack');
        fetch('api.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => {
                if (data.balance !== undefined) {
                    balance = data.balance;
                    updateUI();
                    if (typeof updateBalance === 'function') updateBalance();
                }
            });
    }
}

function hit() {
    playerHand.push(deck.pop());
    if (calculateScore(playerHand) >= 21) stand();
    else updateUI();
}

function doubleDown() {
    if (balance < currentBet) return;
    balance -= currentBet;
    currentBet *= 2;
    playerHand.push(deck.pop());
    stand();
}

async function stand() {
    gameState = 'dealerTurn';
    updateUI();
    let dScore = calculateScore(dealerHand);
    while (dScore < 17) {
        await new Promise(r => setTimeout(r, 600));
        dealerHand.push(deck.pop());
        dScore = calculateScore(dealerHand);
        updateUI();
    }
    finishGame();
}

function finishGame() {
    const pScore = calculateScore(playerHand);
    const dScore = calculateScore(dealerHand);
    let result = "";
    let apiResult = "";
    if (pScore > 21) { result = "BUST !"; apiResult = "lose"; }
    else if (dScore > 21 || pScore > dScore) { result = "GAGNÉ !"; balance += currentBet * 2; apiResult = "win"; }
    else if (pScore === dScore) { result = "ÉGALITÉ"; balance += currentBet; apiResult = "push"; }
    else { result = "PERDU"; apiResult = "lose"; }

    const msg = document.getElementById('msg');
    msg.textContent = result;
    msg.style.display = 'block';
    msg.style.color = (result === "GAGNÉ !") ? "var(--green)" : (result === "PERDU" || result === "BUST !") ? "var(--red)" : "var(--gold)";
    gameState = 'betting';
    updateUI();

    // Sync avec le serveur
    const form = new FormData();
    form.append('action', 'finish');
    form.append('bet', currentBet);
    form.append('result', apiResult);
    fetch('api.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.balance !== undefined) {
                balance = data.balance;
                updateUI();
                if (typeof updateBalance === 'function') updateBalance();
            }
        });
}

function updateUI() {
    document.getElementById('balance').textContent = balance + " J";
    document.getElementById('betValueDisplay').textContent = currentBet;
    const pCards = document.getElementById('player-cards');
    const dCards = document.getElementById('dealer-cards');
    pCards.innerHTML = ''; dCards.innerHTML = '';

    if (playerHand.length > 0) {
        const ani = (gameState !== 'betting');
        playerHand.forEach(c => pCards.appendChild(renderCard(c, false, ani)));
        dealerHand.forEach((c, i) => dCards.appendChild(renderCard(c, gameState === 'playing' && i === 1, ani)));
        document.getElementById('player-score').textContent = calculateScore(playerHand);
        document.getElementById('dealer-score').textContent = (gameState === 'playing') ? '?' : calculateScore(dealerHand);
    }
    document.getElementById('btn-deal').disabled = (gameState !== 'betting' || currentBet < minBet);
    document.getElementById('btn-hit').disabled = (gameState !== 'playing');
    document.getElementById('btn-stand').disabled = (gameState !== 'playing');
    document.getElementById('btn-double').disabled = (gameState !== 'playing' || playerHand.length !== 2 || balance < currentBet);
    document.querySelectorAll('.chip, .tab-btn').forEach(c => c.disabled = (gameState !== 'betting'));
}

updateUI();
</script>
</body>
</html>
