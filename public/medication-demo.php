<?php
// Simple standalone demo page (bypasses Symfony) for Monday validation
function fetch($url) {
    $opts = ["http" => ["timeout" => 5]];
    $context = stream_context_create($opts);
    $res = @file_get_contents($url, false, $context);
    return $res ?: null;
}

$fhir_raw = fetch('https://r4.smarthealthit.org/Patient?_count=1');
$rx_raw = fetch('https://rxnav.nlm.nih.gov/REST/rxcui.json?name=metformin');
$fda_raw = fetch('https://api.fda.gov/drug/label.json?search=openfda.brand_name:metformin&limit=1');

$fhir = $fhir_raw ? json_decode($fhir_raw, true) : null;
$rx = $rx_raw ? json_decode($rx_raw, true) : null;
$fda = $fda_raw ? json_decode($fda_raw, true) : null;

?><!doctype html>
<html>
<head><meta charset="utf-8"><title>Medication Demo (standalone)</title></head>
<body>
<h1>Medication Integration Demo (standalone)</h1>
<h2>FHIR (sample Patient)</h2>
<?php if (!$fhir): ?>
  <p><strong>Status:</strong> Could not load patient data — please try again.</p>
<?php elseif (isset($fhir['entry']) && count($fhir['entry'])>0): ?>
  <p><strong>Status:</strong> Patient data found.</p>
<?php else: ?>
  <p><strong>Status:</strong> No patient data returned from the FHIR sandbox.</p>
<?php endif; ?>
<pre><?php echo htmlspecialchars($fhir_raw ?: json_encode($fhir)); ?></pre>
<h2>RxNav (RxNorm lookup)</h2>
<?php if (!$rx): ?>
  <p><strong>Status:</strong> RxNorm lookup failed — try a different name.</p>
<?php elseif (isset($rx['idGroup']['rxnormId']) && count($rx['idGroup']['rxnormId'])>0): ?>
  <p><strong>Status:</strong> Drug found in RxNorm.</p>
<?php else: ?>
  <p><strong>Status:</strong> No RxNorm match found for that drug.</p>
<?php endif; ?>
<pre><?php echo htmlspecialchars($rx_raw ?: json_encode($rx)); ?></pre>
<h2>openFDA (label)</h2>
<?php if (!$fda): ?>
  <p><strong>Status:</strong> openFDA request failed or rate-limited.</p>
<?php elseif (isset($fda['results']) && count($fda['results'])>0): ?>
  <p><strong>Status:</strong> Label information found.</p>
<?php else: ?>
  <p><strong>Status:</strong> No label data returned from openFDA.</p>
<?php endif; ?>
<pre><?php echo htmlspecialchars($fda_raw ?: json_encode($fda)); ?></pre>

<hr>
<h2>Dosage Assistant (openFDA)</h2>
<p>Enter a drug name to see suggested dosage, warnings and precautions. The suggestion is informational — clinician chooses final dosage.</p>
<label for="drugName">Drug name:</label>
<input id="drugName" value="metformin" style="width:300px" />
<button id="searchDrug">Lookup</button>
<div id="dosageBox" style="margin-top:1em;padding:10px;border:1px solid #ccc;max-width:800px;">
  <p id="dosageStatus"><strong>Idle.</strong></p>
  <p><strong>Suggested dosage:</strong> <span id="suggestedDosage">—</span></p>
  <p><strong>Warnings:</strong> <span id="warnings">—</span></p>
  <p><strong>Precautions:</strong> <span id="precautions">—</span></p>
  <p>
    <label for="finalDosage"><strong>Clinician final dosage (editable):</strong></label>
    <input id="finalDosage" style="width:360px" />
    <button id="confirmDosage">Confirm</button>
  </p>
  <p id="confirmResult" style="color:green;font-weight:bold"></p>
</div>

<h2>Mocks</h2>
<button id="rtbcBtn">Run Mock RTBC</button>
<pre id="rtbcRes"></pre>
<button id="eprescribeBtn">Send Mock E-prescribe</button>
<pre id="eprescribeRes"></pre>

<script>
function firstSentence(text){
  if(!text) return null;
  var s = text.trim();
  var idx = s.indexOf('.');
  if(idx===-1) return s;
  return s.substring(0, idx+1).trim();
}

function extractSuggestedDosage(dosageText){
  if(!dosageText) return null;
  var t = dosageText.join ? dosageText.join(' ') : dosageText;
  // Try to find patterns like '500 mg twice daily' or '500mg twice daily'
  var m = t.match(/\d+\s?mg.*?(twice|once|daily|every|per day|weekly)/i);
  if(m) return m[0];
  // fallback: take the first sentence of the dosage section
  return firstSentence(t).slice(0,400);
}

function renderLabelResult(name, label){
  var suggested = '—';
  var warnings = '—';
  var precautions = '—';
  if(label){
    var dosage = label.dosage_and_administration || label.dosage_and_administration_table || null;
    suggested = extractSuggestedDosage(dosage) || 'No structured dosage found';
    warnings = (label.warnings && label.warnings.length) ? label.warnings.join(' ') : (label.boxed_warning ? label.boxed_warning.join(' ') : 'None found');
    precautions = (label.precautions && label.precautions.length) ? label.precautions.join(' ') : 'None found';
  }
  document.getElementById('dosageStatus').innerHTML = '<strong>Results for:</strong> ' + (name||'');
  document.getElementById('suggestedDosage').textContent = suggested;
  document.getElementById('warnings').textContent = warnings;
  document.getElementById('precautions').textContent = precautions;
  document.getElementById('finalDosage').value = suggested.replace(/\n/g,' ').slice(0,400);
}

document.getElementById('searchDrug').addEventListener('click', function(){
  var name = document.getElementById('drugName').value.trim();
  if(!name) return;
  document.getElementById('dosageStatus').innerHTML = '<strong>Looking up</strong> ' + name + '...';
  // Try openFDA brand_name search first, then substance_name
  var q1 = 'openfda.brand_name:"' + name + '"';
  var q2 = 'openfda.substance_name:"' + name + '"';
  var base = 'https://api.fda.gov/drug/label.json?limit=1&search=';
  fetch(base + encodeURIComponent(q1)).then(r=>r.json()).then(function(j){
    if(j && j.results && j.results.length){ renderLabelResult(name, j.results[0]); }
    else {
      // try substance_name
      fetch(base + encodeURIComponent(q2)).then(r2=>r2.json()).then(function(j2){
        if(j2 && j2.results && j2.results.length) renderLabelResult(name, j2.results[0]);
        else renderLabelResult(name, null);
      }).catch(function(){ renderLabelResult(name, null); });
    }
  }).catch(function(){
    // network or rate limit
    renderLabelResult(name, null);
  });
  // also call RxNav for RxCUI (best-effort)
  fetch('https://rxnav.nlm.nih.gov/REST/rxcui.json?name=' + encodeURIComponent(name)).then(r=>r.json()).then(function(rx){
    if(rx && rx.idGroup && rx.idGroup.rxnormId) console.log('RxNorm IDs', rx.idGroup.rxnormId);
  }).catch(()=>{});
});

document.getElementById('confirmDosage').addEventListener('click', function(){
  var val = document.getElementById('finalDosage').value.trim();
  var name = document.getElementById('drugName').value.trim();
  if(!val) return;
  document.getElementById('confirmResult').textContent = 'Confirmed: ' + name + ' — ' + val;
});

document.getElementById('rtbcBtn').addEventListener('click', function(){
  fetch('/mock_rtbc.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({medication:{rxcui:'1049630', name:'metformin'}})})
    .then(r=>r.json()).then(j=>document.getElementById('rtbcRes').textContent=JSON.stringify(j,null,2)).catch(e=>document.getElementById('rtbcRes').textContent='Error')
});
document.getElementById('eprescribeBtn').addEventListener('click', function(){
  fetch('/mock_eprescribe.php', {method:'POST', headers:{'Content-Type':'application/xml'}, body:'<Prescription/>'})
    .then(r=>r.text()).then(t=>document.getElementById('eprescribeRes').textContent=t).catch(e=>document.getElementById('eprescribeRes').textContent='Error')
});
</script>
</body>
</html>
