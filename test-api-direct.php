<?php
/**
 * Test direct de génération Suno
 * Ajoutez ce fichier à la racine WordPress et accédez-y directement
 */

// Charger WordPress
require_once('wp-load.php');

// Vérifier si admin
if (!current_user_can('manage_options')) {
    die('Accès refusé - Admin uniquement');
}

$api_key = get_option('suno_api_key', '');

if (empty($api_key)) {
    die('Clé API non configurée');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Direct API Suno</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test-section { background: #f5f5f5; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        pre { background: white; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <h1>🔧 Test Direct API Suno</h1>
    
    <?php if (isset($_POST['test'])): ?>
        <?php
        // Données de test
        $test_data = array(
            'prompt' => 'Une chanson pop joyeuse',
            'customMode' => false,
            'instrumental' => false,
            'model' => 'V3_5'
        );
        
        echo '<div class="test-section">';
        echo '<h3>📤 Données envoyées :</h3>';
        echo '<pre>' . json_encode($test_data, JSON_PRETTY_PRINT) . '</pre>';
        echo '</div>';
        
        // Appel API
        $response = wp_remote_post('https://apibox.erweima.ai/api/v1/generate', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($test_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            echo '<div class="test-section error">';
            echo '<h3>❌ Erreur WordPress :</h3>';
            echo '<p>' . $response->get_error_message() . '</p>';
            echo '</div>';
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $headers = wp_remote_retrieve_headers($response);
            
            echo '<div class="test-section">';
            echo '<h3>📥 Réponse API :</h3>';
            echo '<p><strong>Code HTTP :</strong> ' . $status_code . '</p>';
            echo '<p><strong>Headers :</strong></p>';
            echo '<pre>' . print_r($headers, true) . '</pre>';
            echo '<p><strong>Body brut :</strong></p>';
            echo '<pre>' . htmlspecialchars($body) . '</pre>';
            
            if ($body) {
                $data = json_decode($body, true);
                echo '<p><strong>Body décodé :</strong></p>';
                echo '<pre>' . json_encode($data, JSON_PRETTY_PRINT) . '</pre>';
                
                // Chercher le task_id
                echo '<div class="test-section ' . (isset($data['task_id']) || isset($data['taskId']) || isset($data['data']['taskId']) ? 'success' : 'error') . '">';
                echo '<h4>🔍 Recherche du task_id :</h4>';
                
                $task_id = null;
                
                // Différents emplacements possibles
                $possible_locations = array(
                    '$data["task_id"]' => $data['task_id'] ?? null,
                    '$data["taskId"]' => $data['taskId'] ?? null,
                    '$data["data"]["task_id"]' => $data['data']['task_id'] ?? null,
                    '$data["data"]["taskId"]' => $data['data']['taskId'] ?? null,
                    '$data["data"]' => (isset($data['data']) && is_string($data['data'])) ? $data['data'] : null,
                    '$data["id"]' => $data['id'] ?? null,
                    '$data["data"]["id"]' => $data['data']['id'] ?? null,
                );
                
                foreach ($possible_locations as $location => $value) {
                    echo "<p>$location = " . ($value ? "<strong>$value</strong>" : "null") . "</p>";
                    if ($value && !$task_id) {
                        $task_id = $value;
                    }
                }
                
                if ($task_id) {
                    echo '<p style="color: green; font-weight: bold;">✅ Task ID trouvé : ' . $task_id . '</p>';
                } else {
                    echo '<p style="color: red; font-weight: bold;">❌ Aucun task_id trouvé !</p>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        ?>
    <?php endif; ?>
    
    <form method="post">
        <button type="submit" name="test" value="1" style="background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">
            🚀 Lancer le test de génération
        </button>
    </form>
    
    <div class="test-section">
        <h3>ℹ️ Informations :</h3>
        <p><strong>Clé API :</strong> <?php echo substr($api_key, 0, 10); ?>... (<?php echo strlen($api_key); ?> caractères)</p>
        <p><strong>Endpoint :</strong> https://apibox.erweima.ai/api/v1/generate</p>
        <p><strong>Méthode :</strong> POST</p>
    </div>
</body>
</html>