<?php
/**
 * Test simple et direct de l'API Suno
 * Usage : Mettez votre clé API directement dans ce fichier et accédez-y via navigateur
 */

// CONFIGURATION - METTEZ VOTRE CLÉ API ICI
$API_KEY = 'sk-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'; // ← Remplacez par votre vraie clé

// Test simple sans WordPress
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test API Suno - Simple</title>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; background: #e7ffe7; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .error { color: red; background: #ffe7e7; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .info { color: blue; background: #e7f3ff; padding: 10px; margin: 10px 0; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
        h2 { color: #333; border-bottom: 2px solid #6366f1; padding-bottom: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Test Direct API Suno</h1>";

if ($API_KEY === 'sk-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX') {
    echo "<div class='error'>
        <strong>⚠️ ATTENTION</strong><br>
        Vous devez modifier ce fichier et remplacer la clé API par la vôtre.<br>
        Ligne 8 : Remplacez 'sk-XXXXX...' par votre vraie clé API de sunoapi.org
    </div>";
    exit;
}

echo "<div class='info'>
    <strong>Clé API configurée</strong><br>
    Longueur : " . strlen($API_KEY) . " caractères<br>
    Début : " . substr($API_KEY, 0, 15) . "...
</div>";

// Liste des endpoints à tester
$endpoints = [
    'Endpoint 1' => 'https://api.sunoapi.org/api/v1/generate',
    'Endpoint 2' => 'https://api.sunoapi.org/api/generate',
    'Endpoint 3' => 'https://apibox.erweima.ai/api/v1/generate',
    'Endpoint 4' => 'https://sunoapi.org/api/v1/generate'
];

// Formats de données à tester
$test_formats = [
    'Format 1 - CustomMode avec input' => [
        'customMode' => true,
        'input' => [
            'gpt_description_prompt' => 'A cheerful pop song about coding',
            'make_instrumental' => false
        ]
    ],
    'Format 2 - Simple' => [
        'prompt' => 'A cheerful pop song about coding',
        'make_instrumental' => false
    ],
    'Format 3 - Avec model' => [
        'prompt' => 'A cheerful pop song about coding',
        'customMode' => false,
        'instrumental' => false,
        'model' => 'V3_5'
    ]
];

$success = false;
$working_combination = null;

echo "<h2>📡 Test des combinaisons Endpoint + Format</h2>";

foreach ($endpoints as $endpoint_name => $endpoint_url) {
    foreach ($test_formats as $format_name => $data) {
        echo "<div style='margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 3px solid #6366f1;'>";
        echo "<strong>Test : $endpoint_name + $format_name</strong><br>";
        echo "<small>URL : $endpoint_url</small><br>";
        
        // Préparer la requête cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: WordPress/SunoMusicGenerator'
        ]);
        
        // Exécuter la requête
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo "Code HTTP : <strong>$http_code</strong><br>";
        
        if ($curl_error) {
            echo "<div class='error'>Erreur cURL : $curl_error</div>";
        } elseif ($http_code === 200 || $http_code === 201) {
            echo "<div class='success'>✅ SUCCÈS ! Cette combinaison fonctionne !</div>";
            
            $response_data = json_decode($response, true);
            if ($response_data) {
                // Chercher le task_id
                $task_id = null;
                $possible_keys = ['task_id', 'taskId', 'id', 'data.task_id', 'data.taskId', 'data.id', 'data'];
                
                foreach ($possible_keys as $key) {
                    if (strpos($key, '.') !== false) {
                        $parts = explode('.', $key);
                        $temp = $response_data;
                        foreach ($parts as $part) {
                            if (isset($temp[$part])) {
                                $temp = $temp[$part];
                            } else {
                                $temp = null;
                                break;
                            }
                        }
                        if ($temp) {
                            $task_id = $temp;
                            break;
                        }
                    } elseif (isset($response_data[$key])) {
                        $task_id = $response_data[$key];
                        break;
                    }
                }
                
                if ($task_id) {
                    echo "<div class='info'>Task ID trouvé : <strong>$task_id</strong></div>";
                }
                
                echo "<details><summary>Voir la réponse complète</summary>";
                echo "<pre>" . json_encode($response_data, JSON_PRETTY_PRINT) . "</pre>";
                echo "</details>";
                
                $success = true;
                $working_combination = [
                    'endpoint' => $endpoint_url,
                    'format' => $data,
                    'response' => $response_data
                ];
                break 2; // Sortir des deux boucles
            }
        } elseif ($http_code === 401) {
            echo "<div class='error'>❌ Erreur 401 : Clé API invalide ou non autorisée</div>";
        } elseif ($http_code === 402) {
            echo "<div class='error'>❌ Erreur 402 : Crédits insuffisants</div>";
        } elseif ($http_code === 404) {
            echo "<div class='error'>⚠️ Erreur 404 : Endpoint non trouvé</div>";
        } elseif ($http_code === 429) {
            echo "<div class='error'>⚠️ Erreur 429 : Limite de taux dépassée</div>";
        } elseif ($http_code === 500) {
            echo "<div class='error'>❌ Erreur 500 : Erreur serveur API</div>";
        } else {
            echo "<div class='error'>❌ Code HTTP inattendu : $http_code</div>";
            if ($response) {
                echo "<small>Réponse : " . htmlspecialchars(substr($response, 0, 200)) . "</small>";
            }
        }
        
        echo "</div>";
        
        if (!$success) {
            // Pause courte entre les tests pour éviter le rate limiting
            usleep(500000); // 0.5 seconde
        }
    }
}

if ($success && $working_combination) {
    echo "<div class='success' style='font-size: 18px; padding: 20px; margin: 20px 0;'>
        <h2 style='color: green;'>🎉 Configuration fonctionnelle trouvée !</h2>
        <p><strong>Endpoint :</strong> " . $working_combination['endpoint'] . "</p>
        <p><strong>Format de données :</strong> Format testé avec succès</p>
        <p>Copiez ces informations pour configurer votre plugin WordPress.</p>
    </div>";
    
    // Générer le code pour WordPress
    echo "<h2>📝 Code à ajouter dans votre plugin (optionnel)</h2>";
    echo "<pre>
// Dans votre fichier suno-music-generator.php, 
// remplacez la section de génération par :

\$api_url = '" . $working_combination['endpoint'] . "';
\$api_data = " . var_export($working_combination['format'], true) . ";

// Remplacez 'A cheerful pop song about coding' par \$prompt
</pre>";
} else {
    echo "<div class='error' style='font-size: 18px; padding: 20px; margin: 20px 0;'>
        <h2 style='color: red;'>❌ Aucune configuration fonctionnelle trouvée</h2>
        <p>Vérifications à faire :</p>
        <ul>
            <li>Votre clé API est-elle valide ?</li>
            <li>Avez-vous des crédits sur votre compte sunoapi.org ?</li>
            <li>Votre serveur autorise-t-il les connexions HTTPS sortantes ?</li>
            <li>Y a-t-il un pare-feu qui bloque les requêtes ?</li>
        </ul>
    </div>";
}

// Test de connectivité basique
echo "<h2>🌐 Test de connectivité Internet</h2>";
$test_sites = [
    'Google' => 'https://www.google.com',
    'SunoAPI.org' => 'https://sunoapi.org'
];

foreach ($test_sites as $name => $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code > 0) {
        echo "<div class='success'>✅ $name accessible (HTTP $code)</div>";
    } else {
        echo "<div class='error'>❌ $name inaccessible</div>";
    }
}

echo "</body></html>";
