<?php
/**
 * Test direct de l'API SunoAPI.org
 * Placez ce fichier dans votre dossier WordPress et accédez-y directement
 */

// Votre clé API
$api_key = '9c82e5c4333ed87256ff75433aee18b9';

// Endpoints à tester
$endpoints = array(
    'https://api.sunoapi.org/api/v1/get_limit',
    'https://api.sunoapi.org/api/account/info',
    'https://api.sunoapi.org/get_limit',
    'https://apibox.erweima.ai/api/v1/get_limit',
);

echo "<h2>Test API SunoAPI.org</h2>";
echo "<p>Clé API : " . substr($api_key, 0, 10) . "...</p>";

foreach ($endpoints as $url) {
    echo "<h3>Test : $url</h3>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
        'Accept: application/json'
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "<p style='color: red;'>Erreur cURL : $error</p>";
    } else {
        echo "<p>Code HTTP : <strong>$http_code</strong></p>";
        
        if ($http_code === 200) {
            echo "<p style='color: green;'>✅ SUCCÈS !</p>";
            echo "<pre style='background: #f0f0f0; padding: 10px;'>" . htmlspecialchars($response) . "</pre>";
        } else {
            echo "<p style='color: red;'>❌ Échec</p>";
            echo "<pre style='background: #ffe0e0; padding: 10px;'>" . htmlspecialchars($response) . "</pre>";
        }
    }
    
    echo "<hr>";
}

// Test de génération
echo "<h2>Test de génération de musique</h2>";

$generate_data = array(
    'customMode' => true,
    'input' => array(
        'gpt_description_prompt' => 'Une chanson pop joyeuse sur le soleil',
        'make_instrumental' => false
    )
);

$generate_endpoints = array(
    'https://api.sunoapi.org/api/v1/generate',
    'https://apibox.erweima.ai/api/v1/generate'
);

foreach ($generate_endpoints as $url) {
    echo "<h3>Test génération : $url</h3>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($generate_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
        'Accept: application/json'
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "<p style='color: red;'>Erreur cURL : $error</p>";
    } else {
        echo "<p>Code HTTP : <strong>$http_code</strong></p>";
        echo "<pre style='background: #f0f0f0; padding: 10px;'>" . htmlspecialchars($response) . "</pre>";
    }
    
    echo "<hr>";
}

// Info PHP
echo "<h2>Info serveur</h2>";
echo "<p>PHP Version : " . phpversion() . "</p>";
echo "<p>cURL : " . (function_exists('curl_init') ? 'Activé' : 'Désactivé') . "</p>";
echo "<p>allow_url_fopen : " . (ini_get('allow_url_fopen') ? 'Activé' : 'Désactivé') . "</p>";
