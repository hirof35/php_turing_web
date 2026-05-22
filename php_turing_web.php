<?php
// ==========================================
// 1. PHP バックエンド: 初期設定とAPI処理
// ==========================================

// 初期設定のルール群（二進数インクリメントマシン）
$defaultRules = [
    ["state" => "init", "read" => "1", "write" => "1", "dir" => "R", "next" => "init"],
    ["state" => "init", "read" => "0", "write" => "0", "dir" => "R", "next" => "init"],
    ["state" => "init", "read" => "_", "write" => "_", "dir" => "L", "next" => "add"],
    ["state" => "add",  "read" => "1", "write" => "0", "dir" => "L", "next" => "add"],
    ["state" => "add",  "read" => "0", "write" => "1", "dir" => "L", "next" => "done"],
    ["state" => "add",  "read" => "_", "write" => "1", "dir" => "L", "next" => "done"]
];

// Ajax（JavaScript）からのステップ実行リクエストを処理
if (isset($_GET['action']) && $_GET['action'] === 'step') {
    header('Content-Type: application/json');
    
    // フロントエンドから現在の状態を受け取る
    $input = json_decode(file_get_contents('php://input'), true);
    
    $tape = $input['tape'] ?? [];
    $head = (int)($input['head'] ?? 0);
    $state = $input['state'] ?? 'init';
    $rules = $input['rules'] ?? [];
    $blank = '_';

    $currentSymbol = $tape[$head] ?? $blank;
    
    // 一致するルールを検索
    $matchedRule = null;
    foreach ($rules as $rule) {
        if ($rule['state'] === $state && $rule['read'] === $currentSymbol) {
            $matchedRule = $rule;
            break;
        }
    }

    if ($matchedRule) {
        // テープ書き換え
        $tape[$head] = $matchedRule['write'];
        // 状態更新
        $state = $matchedRule['next'];
        // ヘッド移動
        if ($matchedRule['dir'] === 'R') {
            $head++;
        } else if ($matchedRule['dir'] === 'L') {
            $head--;
        }
        
        echo json_encode([
            'halted' => false,
            'tape' => $tape,
            'head' => $head,
            'state' => $state,
            'log' => "Applied: ({$matchedRule['state']}, {$matchedRule['read']}) -> ({$matchedRule['write']}, {$matchedRule['dir']}, {$matchedRule['next']})"
        ]);
    } else {
        echo json_encode([
            'halted' => true,
            'tape' => $tape,
            'head' => $head,
            'state' => $state,
            'log' => "Halted: No matched rule for state '{$state}' and symbol '{$currentSymbol}'"
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>PHP × JS チューリングマシン シミュレーター</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            padding: 30px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        h1 { margin-top: 0; color: #2c3e50; }
        
        /* 視覚的なテープのスタイリング */
        .tape-container {
            overflow-x: auto;
            padding: 20px 0;
            margin: 20px 0;
            background: #ebeff2;
            border-radius: 8px;
            display: flex;
            justify-content: center;
        }
        .tape {
            display: flex;
            position: relative;
            padding-bottom: 40px; /* ヘッド表示用のスペース */
        }
        .cell {
            width: 50px;
            height: 50px;
            border: 2px solid #bdc3c7;
            background: white;
            margin: 0 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            border-radius: 4px;
            transition: all 0.3s;
            position: relative;
        }
        .cell.active {
            border-color: #3498db;
            background-color: #e8f4f8;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.5);
        }
        .cell.active::after {
            content: "▲";
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            color: #e74c3c;
            font-size: 24px;
        }

        /* コントロールエリア */
        .status-panel {
            font-size: 18px;
            margin-bottom: 20px;
            padding: 10px;
            background: #ecf0f1;
            border-left: 5px solid #2ecc71;
            border-radius: 4px;
        }
        .controls {
            margin-bottom: 25px;
        }
        button {
            background: #34495e;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            transition: background 0.2s;
        }
        button:hover { background: #2c3e50; }
        button#startBtn { background: #2ecc71; }
        button#startBtn:hover { background: #27ae60; }
        button#pauseBtn { background: #f39c12; }
        button#pauseBtn:hover { background: #d35400; }

        /* ログエリア */
        .log-box {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 6px;
            height: 150px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Turing Machine Simulator</h1>
    
    <div class="status-panel">
        <strong>Current State:</strong> <span id="stateDisplay">init</span>
    </div>

    <!-- グラフィカル・テープ領域 -->
    <div class="tape-container">
        <div class="tape" id="tapeDisplay">
            <!-- JSで動的にセルを生成 -->
        </div>
    </div>

    <div class="controls">
        <button id="stepBtn">1ステップ進む</button>
        <button id="startBtn">自動実行 (オート)</button>
        <button id="pauseBtn" style="display:none;">一時停止</button>
        <button id="resetBtn">リセット</button>
    </div>

    <h3>実行ログ</h3>
    <div class="log-box" id="logBox"></div>
</div>

<!-- ==========================================
     2. JavaScript フロントエンド: アニメーション・通信制御
     ========================================== -->
<script>
// PHPから初期ルールをパース
const machineRules = <?php echo json_encode($defaultRules); ?>;

// 初期状態
const initialTapeInput = ['1', '1']; // 11 (十進数の3)
let currentState = {
    tape: {}, // インデックスをキーにするためオブジェクトで扱う
    head: 0,
    state: 'init',
    rules: machineRules
};

// テープの初期化
initialTapeInput.forEach((val, index) => {
    currentState.tape[index] = val;
});

let autoInterval = null;

// 表示を更新する関数
function updateUI() {
    document.getElementById('stateDisplay').innerText = currentState.state;
    
    const tapeDisplay = document.getElementById('tapeDisplay');
    tapeDisplay.innerHTML = '';

    // 見栄えのために、現在のヘッド位置の前後5セルを最低限表示する
    const viewRange = 5;
    const minIndex = Math.min(...Object.keys(currentState.tape).map(Number), currentState.head) - viewRange;
    const maxIndex = Math.max(...Object.keys(currentState.tape).map(Number), currentState.head) + viewRange;

    for (let i = minIndex; i <= maxIndex; i++) {
        const cellValue = currentState.tape[i] || '_';
        const cellDiv = document.createElement('div');
        cellDiv.className = 'cell' + (i === currentState.head ? ' active' : '');
        cellDiv.innerText = cellValue;
        tapeDisplay.appendChild(cellDiv);
    }
}

// ログ出力関数
function addLog(message) {
    const logBox = document.getElementById('logBox');
    logBox.innerHTML += `<div>${message}</div>`;
    logBox.scrollTop = logBox.scrollHeight;
}

// PHP APIを叩いて1ステップ実行させる
async function triggerStep() {
    try {
        const response = await fetch('?action=step', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(currentState)
        });
        
        const result = await response.json();
        
        if (result.halted) {
            addLog(result.log);
            stopAuto();
            alert("マシンが停止（Halt）しました。計算完了です！");
            return false;
        }

        // 状態を更新して画面に反映
        currentState.tape = result.tape;
        currentState.head = result.head;
        currentState.state = result.state;
        
        addLog(result.log);
        updateUI();
        return true;
    } catch (e) {
        console.error("通信エラー:", e);
        stopAuto();
        return false;
    }
}

// 自動実行の開始
function startAuto() {
    document.getElementById('startBtn').style.display = 'none';
    document.getElementById('pauseBtn').style.display = 'inline-block';
    
    autoInterval = setInterval(async () => {
        const pussible = await triggerStep();
        if (!pussible) stopAuto();
    }, 400); // 0.4秒間隔でステップを実行
}

// 自動実行の停止
function stopAuto() {
    clearInterval(autoInterval);
    document.getElementById('startBtn').style.display = 'inline-block';
    document.getElementById('pauseBtn').style.display = 'none';
}

// イベントリスナー設定
document.getElementById('stepBtn').addEventListener('click', () => { stopAuto(); triggerStep(); });
document.getElementById('startBtn').addEventListener('click', startAuto);
document.getElementById('pauseBtn').addEventListener('click', stopAuto);
document.getElementById('resetBtn').addEventListener('click', () => {
    stopAuto();
    currentState.tape = {};
    initialTapeInput.forEach((val, index) => currentState.tape[index] = val);
    currentState.head = 0;
    currentState.state = 'init';
    document.getElementById('logBox').innerHTML = '';
    addLog("Reset machine.");
    updateUI();
});

// 初回読み込み時の描画
addLog("Machine initialized. Ready.");
updateUI();
</script>

</body>
</html>
