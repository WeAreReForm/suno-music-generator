<?php
/**
 * Test direct de l'API Suno avec votre token
 * 
 * Usage : Uploadez ce fichier sur votre serveur WordPress et accédez-y
 */

// Token à tester (remplacez par le vôtre)
$token = 'eyJhbGciOiJSUzI1NiIsImNhdCI6ImNsX0I3ZDRQRDExMUFBQSIsImtpZCI6Imluc18yT1o2eU1EZzhscWRKRWloMXJvemY4T3ptZG4iLCJ0eXAiOiJKV1QifQ.eyJhdWQiOiJzdW5vLWFwaSIsImF6cCI6Imh0dHBzOi8vc3Vuby5jb20iLCJleHAiOjE3NTU3NzAwODIsImZ2YSI6WzQsLTFdLCJodHRwczovL3N1bm8uYWkvY2xhaW1zL2NsZXJrX2lkIjoidXNlcl8ydHo4ZzVjZDdTOURBVGVTcm9LSkZWVTV1Z1kiLCJodHRwczovL3N1bm8uYWkvY2xhaW1zL2VtYWlsIjoiZ29mb3JyZWZvcm1AZ21haWwuY29tIiwiaHR0cHM6Ly9zdW5vLmFpL2NsYWltcy9waG9uZSI6bnVsbCwiaWF0IjoxNzU1NzY2NDgyLCJpc3MiOiJodHRwczovL2NsZXJrLnN1bm8uY29tIiwianRpIjoiNTNkYjRmZDhlMTYzNzMwMzMwMmYiLCJuYmYiOjE3NTU3NjY0NzIsInNpZCI6InNlc3NfMzFhZGVVNUZ2Q1U0M1JScElLMFVxc3dYaTF0Iiwic3ViIjoidXNlcl8ydHo4ZzVjZDdTOURBVGVTcm9LSkZWVTV1Z1kifQ.SZomY0sFG0HM5vfKPVBfzKaev1MlLyk-QH296M-aMaoODkK3u0sPw8xix-xO0OocQRJFGIlh7cviWTlTSIcv1CpM8e4BXZUnoZSjUYtB6I6uRp59IqL72exyINrqt5MnLBBQgbE7N9qEZO_btIC_sYcbuLuOLiUUIkZJUKkDI8mNanMhpzfbFXkdcmSCMhbwKNFq_iFO8h8Mm1aAk0K5bdqUJt8N7iP-CiSuBWn_uIvBJvDB-aLVT9mJpO50OqvcxZaHSFDjJ9ojAXd0k_MRsPZ63GVhdneTViDcqB92DC2T8l-hJpAyExijqOmpOyS2r9lUvrZfNvF9CiH1p23pVA';

// URL de l'API Suno
$api_base = 'https://studio-api.suno.ai';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Token Suno</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .test-result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        button {
            background: #6366f1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
        }
        button:hover {
            background: #4f46e5;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #6366f1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Test de votre Token Suno</h1>
        
        <div class="test-result info">
            <strong>📊 Informations du Token :</strong><br>
            <?php
            // Décoder le JWT pour afficher les infos (sans validation)
            $token_parts = explode('.', $token);
            if (count($token_parts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[1])), true);
                echo "Email : " . ($payload['https://suno.ai/claims/email'] ?? 'Non trouvé') . "<br>";
                echo "User ID : " . ($payload['https://suno.ai/claims/clerk_id'] ?? 'Non trouvé') . "<br>";
                echo "Expiration : " . date('Y-m-d H:i:s', $payload['exp'] ?? 0) . "<br>";
                $days_left = round(($payload['exp'] - time()) / 86400);
                echo "Validité : " . ($days_left > 0 ? "$days_left jours restants" : "Expiré") . "<br>";
            }
            ?>
        </div>
        
        <h2>📋 Tests API</h2>
        
        <button onclick="testBilling()">💳 Tester les Crédits</button>
        <button onclick="testGenerate()">🎵 Générer une Chanson Test</button>
        <button onclick="testFeed()">📜 Récupérer mes Créations</button>
        
        <div id="test-results"></div>
        
        <h2>🚀 Configuration WordPress</h2>
        <div class="test-result info">
            <strong>Pour utiliser ce token dans WordPress :</strong><br>
            1. Allez dans <strong>Réglages → Suno Music</strong><br>
            2. Mode : <strong>API Officielle Suno</strong><br>
            3. Méthode : <strong>Token/Cookie</strong><br>
            4. Collez dans "Token Bearer" : <code>Bearer <?php echo substr($token, 0, 30); ?>...</code><br>
            5. Sauvegardez et testez avec <code>[suno_test_api]</code>
        </div>
    </div>
    
    <script>
    const token = '<?php echo $token; ?>';
    const apiBase = '<?php echo $api_base; ?>';
    
    function showResult(html) {
        document.getElementById('test-results').innerHTML = html;
    }
    
    function showLoading() {
        showResult('<div class="spinner"></div><p style="text-align:center;">Test en cours...</p>');
    }
    
    async function testBilling() {
        showLoading();
        try {
            const response = await fetch(apiBase + '/api/billing/info/', {
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            });
            
            const data = await response.json();
            
            if (response.ok) {
                showResult(`
                    <div class="test-result success">
                        <h3>✅ Connexion Réussie!</h3>
                        <strong>Informations de compte :</strong><br>
                        ${data.total_credits_left !== undefined ? `Crédits totaux : ${data.total_credits_left}<br>` : ''}
                        ${data.credits_left !== undefined ? `Crédits restants : ${data.credits_left}<br>` : ''}
                        ${data.period_credits_left !== undefined ? `Crédits période : ${data.period_credits_left}<br>` : ''}
                        ${data.monthly_limit !== undefined ? `Limite mensuelle : ${data.monthly_limit}<br>` : ''}
                        ${data.monthly_usage !== undefined ? `Usage mensuel : ${data.monthly_usage}<br>` : ''}
                        <br>
                        <strong>Token valide et fonctionnel ! 🎉</strong>
                    </div>
                `);
            } else {
                throw new Error('Erreur ' + response.status);
            }
        } catch (error) {
            showResult(`
                <div class="test-result error">
                    <h3>❌ Erreur</h3>
                    ${error.message}<br>
                    Le token pourrait être expiré ou invalide.
                </div>
            `);
        }
    }
    
    async function testGenerate() {
        showLoading();
        try {
            const response = await fetch(apiBase + '/api/generate/v2/', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    prompt: 'A happy pop song about sunshine',
                    mv: 'chirp-v3-5',
                    instrumental: false
                })
            });
            
            const data = await response.json();
            
            if (response.ok && data.clips) {
                const clipIds = data.clips.map(c => c.id).join(', ');
                showResult(`
                    <div class="test-result success">
                        <h3>✅ Génération Lancée!</h3>
                        <strong>Clips créés :</strong> ${clipIds}<br>
                        <strong>Status :</strong> ${data.clips[0].status}<br>
                        <br>
                        La génération prend 30-60 secondes.<br>
                        <strong>L'API fonctionne parfaitement ! 🎵</strong>
                    </div>
                `);
            } else {
                throw new Error(data.detail || 'Erreur de génération');
            }
        } catch (error) {
            showResult(`
                <div class="test-result error">
                    <h3>❌ Erreur de Génération</h3>
                    ${error.message}<br>
                    Vérifiez vos crédits Suno.
                </div>
            `);
        }
    }
    
    async function testFeed() {
        showLoading();
        try {
            const response = await fetch(apiBase + '/api/feed/', {
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            });
            
            const data = await response.json();
            
            if (response.ok) {
                const count = Array.isArray(data) ? data.length : 0;
                showResult(`
                    <div class="test-result success">
                        <h3>✅ Récupération Réussie!</h3>
                        <strong>Nombre de créations :</strong> ${count}<br>
                        ${count > 0 ? `
                            <strong>Dernière création :</strong><br>
                            Titre : ${data[0].title || 'Sans titre'}<br>
                            Date : ${new Date(data[0].created_at).toLocaleDateString()}<br>
                        ` : 'Aucune création pour le moment'}
                        <br>
                        <strong>Connexion API confirmée ! 📜</strong>
                    </div>
                `);
            } else {
                throw new Error('Erreur ' + response.status);
            }
        } catch (error) {
            showResult(`
                <div class="test-result error">
                    <h3>❌ Erreur</h3>
                    ${error.message}
                </div>
            `);
        }
    }
    
    // Test automatique au chargement
    window.onload = () => {
        testBilling();
    };
    </script>
</body>
</html>
