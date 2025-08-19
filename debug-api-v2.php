<?php
/**
 * Script de diagnostic complet pour l'API Suno
 * À exécuter directement : https://votre-site.fr/wp-content/plugins/suno-music-generator/debug-api-v2.php
 */

// Charger WordPress si nécessaire
if (!defined('ABSPATH')) {
    $wp_load = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once($wp_load);
    }
}

// Configuration
$api_key = get_option('suno_api_key', '');

// Si pas de clé en base, permettre de la passer en paramètre pour test
if (empty($api_key) && isset($_GET['key'])) {
    $api_key = sanitize_text_field($_GET['key']);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Diagnostic API Suno v2.0.0</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
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
        .test-section {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border-left: 4px solid #6366f1;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .endpoint-test {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
        }
        .status-success { background: #28a745; color: white; }
        .status-error { background: #dc3545; color: white; }
        .status-warning { background: #ffc107; color: black; }
        button {
            background: #6366f1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #4f46e5;
        }
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #6366f1;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostic API Suno - Version 2.0.0</h1>
        
        <?php if (empty($api_key)): ?>
            <div class="error">
                <strong>❌ Aucune clé API configurée</strong><br>
                Options :<br>
                1. Configurez la clé dans WordPress : Réglages > Suno Music<br>
                2. Ou ajoutez ?key=VOTRE_CLE à l'URL pour tester<br>
                Exemple : <?php echo $_SERVER['REQUEST_URI']; ?>?key=sk-xxxxx
            </div>
        <?php else: ?>
            <div class="info">
                <strong>🔑 Clé API détectée</strong><br>
                Longueur : <?php echo strlen($api_key); ?> caractères<br>
                Début : <?php echo substr($api_key, 0, 10); ?>...<br>
                Type : <?php echo strpos($api_key, 'sk-') === 0 ? 'Format standard (sk-)' : 'Format personnalisé'; ?>
            </div>
        <?php endif; ?>
        
        <div class="test-section">
            <h2>📡 Test 1 : Connectivité réseau</h2>
            <?php
            $test_urls = [
                'Google' => 'https://www.google.com',
                'WordPress.org' => 'https://wordpress.org',
                'SunoAPI.org' => 'https://sunoapi.org',
                'API SunoAPI' => 'https://api.sunoapi.org'
            ];
            
            foreach ($test_urls as $name => $url) {
                echo "<div class='endpoint-test'>";
                echo "<strong>$name</strong> : $url<br>";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                
                $start = microtime(true);
                curl_exec($ch);
                $time = round((microtime(true) - $start) * 1000);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($code > 0) {
                    echo "<span class='status-badge status-success'>✅ HTTP $code - {$time}ms</span>";
                } else {
                    echo "<span class='status-badge status-error'>❌ Erreur : $error</span>";
                }
                echo "</div>";
            }
            ?>
        </div>
        
        <?php if (!empty($api_key)): ?>
        
        <div class="test-section">
            <h2>🔐 Test 2 : Authentification API</h2>
            <?php
            $auth_endpoints = [
                'https://api.sunoapi.org/api/v1/get_limit',
                'https://api.sunoapi.org/api/get_limit',
                'https://apibox.erweima.ai/api/v1/get_limit',
                'https://sunoapi.org/api/v1/get_limit'
            ];
            
            $working_endpoint = null;
            
            foreach ($auth_endpoints as $endpoint) {
                echo "<div class='endpoint-test'>";
                echo "<strong>Endpoint :</strong> $endpoint<br>";
                
                $headers = [
                    'Authorization: Bearer ' . $api_key,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                echo "Code HTTP : <strong>$code</strong><br>";
                
                if ($code === 200) {
                    echo "<span class='status-badge status-success'>✅ Authentification réussie</span><br>";
                    $working_endpoint = $endpoint;
                    
                    $data = json_decode($response, true);
                    if ($data) {
                        echo "<details><summary>Voir la réponse</summary>";
                        echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
                        echo "</details>";
                    }
                } elseif ($code === 401) {
                    echo "<span class='status-badge status-error'>❌ Clé API invalide</span>";
                } elseif ($code === 403) {
                    echo "<span class='status-badge status-error'>❌ Accès refusé</span>";
                } elseif ($code === 429) {
                    echo "<span class='status-badge status-warning'>⚠️ Limite de taux atteinte</span>";
                } elseif ($code === 404) {
                    echo "<span class='status-badge status-warning'>⚠️ Endpoint non trouvé (peut être normal)</span>";
                } else {
                    echo "<span class='status-badge status-error'>❌ Erreur : $error</span>";
                }
                
                if (!empty($response) && $code !== 200) {
                    echo "<br>Réponse : <code>" . htmlspecialchars(substr($response, 0, 200)) . "</code>";
                }
                
                echo "</div>";
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>🎵 Test 3 : Génération de musique (simulation)</h2>
            <?php if ($working_endpoint): ?>
                <div class="info">
                    <strong>✅ Endpoint fonctionnel trouvé !</strong><br>
                    Nous allons utiliser : <?php echo $working_endpoint; ?>
                </div>
                
                <div id="generation-test">
                    <button onclick="testGeneration()">🚀 Lancer un test de génération</button>
                    <div id="generation-result"></div>
                </div>
                
                <script>
                function testGeneration() {
                    const resultDiv = document.getElementById('generation-result');
                    resultDiv.innerHTML = '<div class="info">⏳ Test en cours... <span class="loader"></span></div>';
                    
                    // Test via fetch
                    const testData = {
                        customMode: true,
                        input: {
                            gpt_description_prompt: "A happy test song",
                            make_instrumental: false
                        }
                    };
                    
                    fetch('test-generation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            api_key: '<?php echo $api_key; ?>',
                            data: testData
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            resultDiv.innerHTML = '<div class="success">✅ ' + data.message + '</div>';
                        } else {
                            resultDiv.innerHTML = '<div class="error">❌ ' + data.message + '</div>';
                        }
                    })
                    .catch(error => {
                        resultDiv.innerHTML = '<div class="error">❌ Erreur : ' + error + '</div>';
                    });
                }
                </script>
            <?php else: ?>
                <div class="error">
                    ❌ Aucun endpoint fonctionnel trouvé. Vérifiez votre clé API.
                </div>
            <?php endif; ?>
        </div>
        
        <?php endif; ?>
        
        <div class="test-section">
            <h2>🔧 Test 4 : Configuration PHP</h2>
            <?php
            $php_checks = [
                'Version PHP' => PHP_VERSION . ' (Requis: 7.4+)',
                'cURL' => extension_loaded('curl') ? '✅ Activé' : '❌ Désactivé',
                'JSON' => extension_loaded('json') ? '✅ Activé' : '❌ Désactivé',
                'allow_url_fopen' => ini_get('allow_url_fopen') ? '✅ Activé' : '⚠️ Désactivé',
                'SSL' => extension_loaded('openssl') ? '✅ Activé' : '⚠️ Désactivé',
                'Max execution time' => ini_get('max_execution_time') . ' secondes',
                'Memory limit' => ini_get('memory_limit')
            ];
            
            echo "<table style='width: 100%;'>";
            foreach ($php_checks as $check => $value) {
                echo "<tr>";
                echo "<td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>$check</strong></td>";
                echo "<td style='padding: 8px; border-bottom: 1px solid #ddd;'>$value</td>";
                echo "</tr>";
            }
            echo "</table>";
            ?>
        </div>
        
        <div class="test-section">
            <h2>📊 Test 5 : Base de données WordPress</h2>
            <?php
            if (function_exists('get_option')) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'suno_generations';
                
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                
                if ($table_exists) {
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                    $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
                    $completed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
                    
                    echo "<div class='success'>";
                    echo "✅ Table existante : $table_name<br>";
                    echo "Total : $count générations<br>";
                    echo "En cours : $pending<br>";
                    echo "Terminées : $completed";
                    echo "</div>";
                    
                    // Dernière génération
                    $last = $wpdb->get_row("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 1");
                    if ($last) {
                        echo "<div class='info'>";
                        echo "<strong>Dernière génération :</strong><br>";
                        echo "Date : " . $last->created_at . "<br>";
                        echo "Statut : " . $last->status . "<br>";
                        echo "Task ID : " . substr($last->task_id, 0, 20) . "...";
                        echo "</div>";
                    }
                } else {
                    echo "<div class='warning'>⚠️ Table non trouvée. Réactivez le plugin pour la créer.</div>";
                }
            } else {
                echo "<div class='error'>❌ WordPress non chargé</div>";
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>💡 Recommandations</h2>
            <?php
            $recommendations = [];
            
            if (empty($api_key)) {
                $recommendations[] = "🔑 Configurez votre clé API dans WordPress ou testez avec ?key=VOTRE_CLE";
            }
            
            if (!extension_loaded('curl')) {
                $recommendations[] = "⚠️ Activez l'extension cURL PHP pour de meilleures performances";
            }
            
            if (ini_get('max_execution_time') < 60) {
                $recommendations[] = "⏱️ Augmentez max_execution_time à 60 secondes minimum";
            }
            
            if (!$working_endpoint && !empty($api_key)) {
                $recommendations[] = "❌ Vérifiez que votre clé API est valide sur sunoapi.org";
                $recommendations[] = "💰 Vérifiez que vous avez des crédits disponibles";
            }
            
            if (empty($recommendations)) {
                echo "<div class='success'>✅ Tout semble correctement configuré !</div>";
            } else {
                echo "<ul>";
                foreach ($recommendations as $rec) {
                    echo "<li>$rec</li>";
                }
                echo "</ul>";
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>📝 Commande cURL pour test manuel</h2>
            <pre>curl -X POST "https://api.sunoapi.org/api/v1/generate" \
  -H "Authorization: Bearer VOTRE_CLE_API" \
  -H "Content-Type: application/json" \
  -d '{
    "customMode": true,
    "input": {
      "gpt_description_prompt": "A happy pop song about sunshine",
      "make_instrumental": false
    }
  }'</pre>
        </div>
    </div>
</body>
</html>
