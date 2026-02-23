# Script PowerShell pour tester l'API de risque IA
# Usage: .\test_api_risk.ps1

Write-Host "🧪 TEST API RISK ANALYSIS" -ForegroundColor Cyan
Write-Host "=" * 60

# 1. Vérifier que Symfony est démarré
Write-Host "`n1️⃣ Vérification serveur Symfony..." -ForegroundColor Yellow
$serverCheck = Test-NetConnection -ComputerName localhost -Port 8000 -WarningAction SilentlyContinue

if (-not $serverCheck.TcpTestSucceeded) {
    Write-Host "   ❌ Serveur Symfony non démarré sur port 8000" -ForegroundColor Red
    Write-Host "   → Lancez: symfony server:start" -ForegroundColor Yellow
    exit 1
}
Write-Host "   ✅ Serveur actif sur http://localhost:8000" -ForegroundColor Green

# 2. Récupérer une demande pour test
Write-Host "`n2️⃣ Recherche d'une demande dans la base..." -ForegroundColor Yellow

$queryResult = php bin/console doctrine:query:sql "SELECT id FROM demande_aide ORDER BY id DESC LIMIT 1" 2>&1

if ($queryResult -match "(\d+)") {
    $demandeId = $matches[1]
    Write-Host "   ✅ Demande trouvée: ID = $demandeId" -ForegroundColor Green
} else {
    Write-Host "   ❌ Aucune demande trouvée en base" -ForegroundColor Red
    exit 1
}

# 3. Appel API
Write-Host "`n3️⃣ Appel API /api/demandes/$demandeId/risk..." -ForegroundColor Yellow

try {
    $response = Invoke-RestMethod -Uri "http://localhost:8000/api/demandes/$demandeId/risk" -Method Get -TimeoutSec 30
    
    Write-Host "   ✅ Réponse reçue!" -ForegroundColor Green
    Write-Host "`n📊 RÉSULTATS:" -ForegroundColor Cyan
    Write-Host "   • Demande ID: $($response.demandeId)"
    Write-Host "   • Score déterministe: $($response.scoreDeterministe)/100"
    
    if ($null -ne $response.scoreIa) {
        Write-Host "   • Score IA: $($response.scoreIa)/100" -ForegroundColor Magenta
        Write-Host "   • Score final: $($response.scoreFinal)/100" -ForegroundColor Green
    } else {
        Write-Host "   • Score IA: N/A (fallback actif)" -ForegroundColor Yellow
        Write-Host "   • Score final: $($response.scoreFinal)/100"
    }
    
    Write-Host "   • Niveau risque: $($response.level)"
    
    if ($response.pathologieProbable) {
        Write-Host "`n🤖 ANALYSE IA:" -ForegroundColor Magenta
        Write-Host "   • Pathologie: $($response.pathologieProbable)"
        Write-Host "   • Justification: $($response.justificationIa)"
    }
    
    Write-Host "`n   Facteurs détectés:" -ForegroundColor Cyan
    foreach ($factor in $response.factors) {
        Write-Host "     - $factor"
    }
    
} catch {
    Write-Host "   ❌ Erreur API: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host "`n" + ("=" * 60)
Write-Host "✅ Test terminé!" -ForegroundColor Green
