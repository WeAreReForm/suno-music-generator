<?php
/**
 * Script Helper pour récupérer le token Suno
 * 
 * Ce script aide à obtenir le token/cookie nécessaire pour l'API officielle Suno
 * quand vous utilisez Google OAuth ou d'autres méthodes de connexion.
 */

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récupérer votre Token Suno</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 10px;
        }
        .step {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-left: 4px solid #6366f1;
            border-radius: 5px;
        }
        .step h3 {
            margin-top: 0;
            color: #6366f1;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            color: #d73a49;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            margin: 10px 0;
        }
        button {
            background: #6366f1;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #4f46e5;
        }
        .token-display {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            padding: 15px;
            border-radius: 5px;
            word-break: break-all;
            font-family: monospace;
            margin: 15px 0;
        }
        img {
            max-width: 100%;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 Récupérer votre Token Suno</h1>
        
        <div class="step warning">
            <h3>⚠️ Important</h3>
            <p>Si vous utilisez <strong>Google, Discord ou Apple</strong> pour vous connecter à Suno, vous n'avez pas de mot de passe traditionnel. Vous devez récupérer votre token d'authentification.</p>
        </div>

        <h2>📋 Méthode 1 : Via les Cookies (Recommandé)</h2>
        
        <div class="step">
            <h3>Étape 1 : Connectez-vous à Suno</h3>
            <p>Ouvrez <a href="https://suno.com" target="_blank">https://suno.com</a> et connectez-vous avec votre méthode habituelle (Google, Discord, etc.)</p>
        </div>
        
        <div class="step">
            <h3>Étape 2 : Ouvrez les DevTools</h3>
            <p>Appuyez sur <code>F12</code> ou faites un clic droit → "Inspecter"</p>
        </div>
        
        <div class="step">
            <h3>Étape 3 : Trouvez le Cookie</h3>
            <p><strong>Dans Chrome :</strong></p>
            <ol>
                <li>Allez dans l'onglet <code>Application</code></li>
                <li>Dans le menu de gauche : <code>Storage</code> → <code>Cookies</code> → <code>https://suno.com</code></li>
                <li>Cherchez le cookie nommé <code>__session</code> ou <code>__client</code></li>
                <li>Copiez la valeur (colonne "Value")</li>
            </ol>
            
            <p><strong>Dans Firefox :</strong></p>
            <ol>
                <li>Allez dans l'onglet <code>Storage</code></li>
                <li>Ouvrez <code>Cookies</code> → <code>https://suno.com</code></li>
                <li>Trouvez <code>__session</code></li>
            </ol>
        </div>

        <h2>📋 Méthode 2 : Via le Network</h2>
        
        <div class="step">
            <h3>Étape 1 : Surveillez le réseau</h3>
            <ol>
                <li>Dans les DevTools, allez dans l'onglet <code>Network</code></li>
                <li>Cochez <code>Preserve log</code></li>
                <li>Filtrez par <code>Fetch/XHR</code></li>
            </ol>
        </div>
        
        <div class="step">
            <h3>Étape 2 : Déclenchez une requête</h3>
            <ol>
                <li>Sur Suno.com, cliquez sur "Create" ou générez une chanson</li>
                <li>Dans Network, cherchez une requête vers <code>api/generate</code> ou <code>api/feed</code></li>
                <li>Cliquez sur la requête</li>
                <li>Dans l'onglet <code>Headers</code>, trouvez <code>Authorization: Bearer xxxxx</code></li>
                <li>Copiez le token (la partie après "Bearer ")</li>
            </ol>
        </div>

        <div class="step success">
            <h3>✅ Testez votre Token</h3>
            <form id="token-test-form">
                <label for="token">Collez votre token/cookie ici :</label>
                <input type="text" id="token" placeholder="Collez le token ou cookie ici..." />
                <button type="submit">Tester le Token</button>
            </form>
            <div id="test-result"></div>
        </div>

        <h2>🔐 Utilisation dans WordPress</h2>
        
        <div class="step">
            <h3>Configuration dans le Plugin</h3>
            <p>Une fois que vous avez votre token :</p>
            <ol>
                <li>Allez dans <strong>WordPress Admin → Réglages → Suno Music</strong></li>
                <li>Choisissez <strong>"API Officielle Suno"</strong></li>
                <li>Dans le champ <strong>"Token Suno"</strong>, collez votre token</li>
                <li>Sauvegardez et testez avec <code>[suno_test_api]</code></li>
            </ol>
        </div>

        <div class="step warning">
            <h3>⏰ Durée de vie du Token</h3>
            <p>Les tokens Suno expirent généralement après <strong>24-48 heures</strong>. Vous devrez le renouveler périodiquement.</p>
            <p><strong>Astuce :</strong> Nous travaillons sur une solution automatique pour rafraîchir le token.</p>
        </div>
    </div>

    <script>
    document.getElementById('token-test-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const token = document.getElementById('token').value.trim();
        const resultDiv = document.getElementById('test-result');
        
        if (!token) {
            resultDiv.innerHTML = '<p style="color: red;">⚠️ Veuillez entrer un token</p>';
            return;
        }
        
        resultDiv.innerHTML = '<p>🔄 Test en cours...</p>';
        
        try {
            // Test basique de format
            if (token.startsWith('Bearer ')) {
                resultDiv.innerHTML = '<div class="token-display"><strong>Token détecté :</strong><br>' + 
                                    token.substring(0, 20) + '...' + token.substring(token.length - 10) + 
                                    '</div><p style="color: green;">✅ Format Bearer Token valide</p>';
            } else if (token.length > 100) {
                resultDiv.innerHTML = '<div class="token-display"><strong>Cookie détecté :</strong><br>' + 
                                    token.substring(0, 30) + '...' + token.substring(token.length - 10) + 
                                    '</div><p style="color: green;">✅ Format Cookie valide</p>';
            } else {
                resultDiv.innerHTML = '<p style="color: orange;">⚠️ Token court - vérifiez que vous avez copié la valeur complète</p>';
            }
            
            // Note pour l'utilisateur
            resultDiv.innerHTML += '<p><strong>Prochaine étape :</strong> Copiez ce token dans les réglages WordPress du plugin.</p>';
            
        } catch (error) {
            resultDiv.innerHTML = '<p style="color: red;">❌ Erreur : ' + error.message + '</p>';
        }
    });
    </script>
</body>
</html>
