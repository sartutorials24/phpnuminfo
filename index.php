<?php
/**
 * NumInfo — PHP + Bootstrap (Light/Dark Mode Toggle)
 * API: https://decryptkarnrwalebkl.wasmer.app/?key=<API_KEY>&term=<term>
 * Reads API_KEY from environment variable for Render deployment
 */

define('API_URL', 'https://decryptkarnrwalebkl.wasmer.app/');
$API_KEY = getenv('API_KEY') ?: 'lodalelobaby';
$key_param = 'key';
$term_param = 'term';

// ---------- Helper Functions ----------
function safe($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function call_api($term, $key, $timeout=10) {
    $url = API_URL . '?' . http_build_query(['key'=>$key,'term'=>$term]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) return ['error'=>"cURL error: $err",'status'=>$code];
    $decoded = json_decode($resp,true);
    if (json_last_error()===JSON_ERROR_NONE) return ['data'=>$decoded,'status'=>$code];
    return ['data'=>['raw'=>$resp],'status'=>$code];
}

function mock_lookup($term) {
    $samples=[
        ['name'=>'Rahul Kumar','fname'=>'Suresh Kumar','mobile'=>$term,'email'=>'rahul.k@example.com','address'=>'Delhi'],
        ['name'=>'Priya Sharma','fname'=>'Anil Sharma','mobile'=>$term,'email'=>'priya.sh@example.com','address'=>'Mumbai'],
        ['name'=>'Amit Verma','fname'=>'R.C. Verma','mobile'=>$term,'email'=>'amit.v@example.com','address'=>'Kolkata'],
    ];
    return $samples[abs(crc32($term)) % count($samples)];
}

function is_assoc($a){return is_array($a)&&array_keys($a)!==range(0,count($a)-1);}
function array_to_csv($arr){
    $rows=is_assoc($arr)?[$arr]:(is_array($arr)?$arr:[['value'=>json_encode($arr)]]);
    $keys=[];foreach($rows as $r)foreach($r as $k=>$v)$keys[$k]=true;
    $f=fopen('php://memory','r+');fputcsv($f,array_keys($keys));
    foreach($rows as $r){$row=[];foreach($keys as $k=>$v)$row[]=$r[$k]??'';fputcsv($f,$row);}
    rewind($f);$csv=stream_get_contents($f);fclose($f);return $csv;
}

// ---------- Handle Request ----------
$term = $_REQUEST['term'] ?? '';
$use_mock = isset($_REQUEST['use_mock']);
$download_csv = isset($_REQUEST['download_csv']);
$result=null; $status=null; $error=null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if ($use_mock) {
        $result = mock_lookup($term); $status=200;
    } else {
        $resp = call_api($term, $API_KEY);
        $result = $resp['data'] ?? ['error'=>'No data']; 
        $status = $resp['status']; 
        if(isset($resp['error'])) $error=$resp['error'];
    }
    if ($download_csv && $result) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="numinfo_'.preg_replace('/\\W+/','_',$term).'.csv"');
        echo array_to_csv($result); exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>NumInfo — Light/Dark Mode</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link id="theme-link" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css">
<style>
.card-compact{padding:12px;border-radius:10px;box-shadow:0 4px 10px rgba(0,0,0,0.15);margin-bottom:8px;}
.key{font-weight:700;color:#0d6efd;}
.val{font-weight:500;}
pre.json-box{background:#f8f9fa;border-radius:8px;padding:12px;overflow:auto;max-height:380px;}
footer{margin-top:2rem;text-align:center;font-size:0.9rem;opacity:0.8;}
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>📱 NumInfo — PHP App</h2>
    <div>
      <button id="toggleTheme" class="btn btn-outline-secondary btn-sm">🌗 Toggle Theme</button>
    </div>
  </div>

  <?php if (getenv('API_KEY') === false): ?>
    <div class="alert alert-warning">⚠️ Environment variable <code>API_KEY</code> not set. Using fallback key <b>lodalelobaby</b>.</div>
  <?php endif; ?>

  <form method="post" class="card p-3 shadow-sm mb-4">
    <input type="hidden" name="action" value="lookup">
    <div class="row g-3 align-items-end">
      <div class="col-md-8">
        <label class="form-label">Enter term / phone number</label>
        <input type="text" name="term" value="<?=safe($term)?>" placeholder="+919876543210 or F" class="form-control">
      </div>
      <div class="col-md-2 form-check text-center">
        <input class="form-check-input" type="checkbox" name="use_mock" id="use_mock" <?= $use_mock?'checked':''?>>
        <label class="form-check-label" for="use_mock">Mock mode</label>
      </div>
      <div class="col-md-2 text-end">
        <button class="btn btn-primary" type="submit">🔍 Lookup</button>
        <button class="btn btn-outline-success mt-2" name="download_csv" value="1">⬇️ CSV</button>
      </div>
    </div>
  </form>

  <?php if ($_SERVER['REQUEST_METHOD']==='POST'): ?>
  <div class="row">
    <div class="col-lg-8">
      <h5>Summary</h5>
      <?php
        if(!$result){echo '<div class="alert alert-secondary">No data</div>';}
        else{
          $summary=is_assoc($result)?$result:(is_array($result)?$result[0]:['value'=>$result]);
          $fields=['name','fname','mobile','email','address','circle','id'];
          $shown=false;
          foreach($fields as $f){
            if(!empty($summary[$f])){
              echo '<div class="card-compact border">';
              echo '<div class="key">'.safe(ucfirst($f)).'</div>';
              echo '<div class="val">'.safe($summary[$f]).'</div>';
              echo '</div>'; $shown=true;
            }
          }
          if(!$shown) echo '<div class="alert alert-info">No standard fields found. See raw JSON.</div>';
        }
      ?>
    </div>
    <div class="col-lg-4">
      <h5>Raw JSON</h5>
      <pre class="json-box"><?=safe(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))?></pre>
      <?php if($status):?><small class="text-muted">HTTP Status: <?=safe($status)?></small><?php endif;?>
    </div>
  </div>
  <?php endif; ?>

  <footer class="mt-4">
    🔐 Secure & Deployable on Render — API key loaded from environment (<code>API_KEY</code>)
  </footer>
</div>

<script>
// --- Light/Dark Theme Toggle ---
const themeLink = document.getElementById('theme-link');
const toggleBtn = document.getElementById('toggleTheme');
const storedTheme = localStorage.getItem('theme') || 'light';
const themes = {
  light: "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css",
  dark:  "https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css"
};
function applyTheme(theme){
  themeLink.href = themes[theme];
  localStorage.setItem('theme', theme);
}
applyTheme(storedTheme);
toggleBtn.addEventListener('click', ()=>{
  const newTheme = (localStorage.getItem('theme')==='dark') ? 'light':'dark';
  applyTheme(newTheme);
});
</script>
</body>
</html>
