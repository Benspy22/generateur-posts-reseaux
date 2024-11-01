<?php
/*
Plugin Name: Générateur de Posts pour Réseaux Sociaux
Plugin URI: http://votre-site.com
Description: Un plugin qui génère des posts pour les réseaux sociaux en utilisant une IA.
Version: 1.1
Author: Votre Nom
Author URI: http://votre-site.com
License: GPL2
Text Domain: gprs
*/

// Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit('Accès direct interdit.');
}

// Définir les constantes du plugin
define('GPRS_VERSION', '1.1');
define('GPRS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GPRS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GPRS_MIN_PHP_VERSION', '7.4');
define('GPRS_MIN_WP_VERSION', '5.6');

class GPRS_Generator {
    private static $instance = null;
    private $api_key;
    private $last_error = null;
    
    private $default_params = [
        'max_tokens' => 500,
        'temperature' => 0.7,
        'model' => 'gpt-3.5-turbo',
        'presence_penalty' => 0.3,
        'frequency_penalty' => 0.3
    ];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
        
        // Hooks d'initialisation
        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_action('admin_init', [$this, 'check_requirements']);
        
        // Hooks d'administration
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('admin_notices', [$this, 'display_admin_notices']);
        }
        
        // Hooks AJAX
        add_action('wp_ajax_gprs_generate_post', [$this, 'handle_ajax_generate_post']);
        add_action('wp_ajax_gprs_check_connection', [$this, 'handle_ajax_check_connection']);

        // Hooks d'activation/désactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Ajouter le support des shortcodes
        add_shortcode('generateur_posts', [$this, 'render_frontend_generator']);
    }

    public function init_plugin() {
        load_plugin_textdomain('gprs', false, dirname(plugin_basename(__FILE__)) . '/languages');
        $this->init_options();
    }

    public function render_frontend_generator($atts = [], $content = null) {
        // Vérifier si l'utilisateur a les permissions
        if (!current_user_can('edit_posts')) {
            return '<p>' . __('Vous n\'avez pas les permissions nécessaires pour utiliser ce générateur.', 'gprs') . '</p>';
        }

        // Charger les styles et scripts nécessaires
        wp_enqueue_style('gprs-frontend-style', GPRS_PLUGIN_URL . 'css/frontend.css', [], GPRS_VERSION);
        wp_enqueue_script('jquery');
        wp_enqueue_script('gprs-frontend-script', GPRS_PLUGIN_URL . 'js/frontend.js', ['jquery'], GPRS_VERSION, true);
        
        wp_localize_script('gprs-frontend-script', 'gprsAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gprs_nonce'),
            'debug' => WP_DEBUG,
            'phrases' => [
                'error' => __('Une erreur est survenue', 'gprs'),
                'copied' => __('Copié !', 'gprs'),
                'copy' => __('Copier', 'gprs'),
                'generating' => __('Génération en cours...', 'gprs'),
                'networkError' => __('Erreur de connexion au serveur', 'gprs'),
                'timeout' => __('La requête a pris trop de temps', 'gprs'),
                'missingParams' => __('Veuillez remplir tous les champs requis', 'gprs')
            ]
        ]);

        // Démarrer la mise en cache
        ob_start();

        // Inclure le template frontend
        $template_path = GPRS_PLUGIN_DIR . 'templates/frontend-generator.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            return '<p>' . __('Template non trouvé.', 'gprs') . '</p>';
        }

        // Retourner le contenu mis en cache
        return ob_get_clean();
    }
    private function init_options() {
        $default_options = [
            'version' => GPRS_VERSION,
            'max_tokens' => $this->default_params['max_tokens'],
            'temperature' => $this->default_params['temperature'],
            'model' => $this->default_params['model'],
            'last_error' => null,
            'last_check' => time()
        ];

        add_option('gprs_options', $default_options);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Générateur de Posts', 'gprs'),
            __('Générateur de Posts', 'gprs'),
            'manage_options',
            'gprs',
            [$this, 'render_admin_page'],
            'dashicons-share',
            30
        );
    }

    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_gprs' !== $hook) {
            return;
        }

        // Assurez-vous que jQuery est chargé
        wp_enqueue_script('jquery');

        // Styles
        wp_enqueue_style(
            'gprs-admin-style',
            GPRS_PLUGIN_URL . 'css/admin.css',
            [],
            GPRS_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'gprs-admin-script',
            GPRS_PLUGIN_URL . 'js/admin.js',
            ['jquery'],
            GPRS_VERSION,
            true
        );

        wp_localize_script('gprs-admin-script', 'gprsAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gprs_nonce'),
            'debug' => WP_DEBUG,
            'phrases' => [
                'error' => __('Une erreur est survenue', 'gprs'),
                'copied' => __('Copié !', 'gprs'),
                'copy' => __('Copier', 'gprs'),
                'generating' => __('Génération en cours...', 'gprs'),
                'networkError' => __('Erreur de connexion au serveur', 'gprs'),
                'timeout' => __('La requête a pris trop de temps', 'gprs'),
                'missingParams' => __('Veuillez remplir tous les champs requis', 'gprs'),
                'copyError' => __('Impossible de copier le texte', 'gprs')
            ]
        ]);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions suffisantes pour accéder à cette page.', 'gprs'));
        }

        $template_path = GPRS_PLUGIN_DIR . 'templates/admin-page.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->display_template_error();
        }
    }

    private function display_template_error() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="notice notice-error">
                <p><?php _e('Le template admin-page.php est manquant. Veuillez réinstaller le plugin.', 'gprs'); ?></p>
            </div>
        </div>
        <?php
    }

    public function handle_ajax_generate_post() {
        try {
            if (!check_ajax_referer('gprs_nonce', 'nonce', false)) {
                throw new Exception(__('Erreur de sécurité.', 'gprs'));
            }

            if (!current_user_can('edit_posts')) {
                throw new Exception(__('Permissions insuffisantes.', 'gprs'));
            }

            // Log pour le débogage
            if (WP_DEBUG) {
                error_log('GPRS: Début de la génération du post');
                error_log('GPRS: Données reçues: ' . print_r($_POST, true));
            }

            $prompt = $this->get_validated_param('prompt');
            $tone = $this->get_validated_param('tone');
            $length = $this->get_validated_param('length', 'moyen');
            $hashtags = $this->get_validated_param('hashtags', 'peu');

            $result = $this->generate_post($prompt, $tone, $length, $hashtags);

            wp_send_json_success(['content' => $result]);

        } catch (Exception $e) {
            $this->log_error($e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    private function get_validated_param($param_name, $default = '') {
        if (!isset($_POST[$param_name])) {
            if ($default !== '') {
                return $default;
            }
            throw new Exception(sprintf(
                __('Le paramètre %s est requis.', 'gprs'),
                $param_name
            ));
        }

        $value = sanitize_text_field($_POST[$param_name]);
        if (empty($value) && $default === '') {
            throw new Exception(sprintf(
                __('Le paramètre %s ne peut pas être vide.', 'gprs'),
                $param_name
            ));
        }

        return $value ?: $default;
    }

    public function handle_ajax_check_connection() {
        check_ajax_referer('gprs_nonce', 'nonce');
        wp_send_json_success();
    }

    private function generate_post($prompt, $tone, $length, $hashtags) {
        if (empty($this->api_key)) {
            throw new Exception(__('Clé API OpenAI manquante', 'gprs'));
        }

        $context = $this->build_context($prompt, $tone, $length, $hashtags);
        $response = $this->call_openai_api($context);
        return $this->format_response($response);
    }

    private function build_context($prompt, $tone, $length, $hashtags) {
    $word_count = [
        'court' => 50,
        'moyen' => 100,
        'long' => 200
    ][$length] ?? 100;

    // Modifions la gestion des hashtags
    $hashtag_count = [
        'aucun' => 0,
        'peu' => 3,
        'beaucoup' => 5
    ][$hashtags] ?? 3;

    $context = "Tu es un expert en communication pour le club de football de Le Roeulx. ";
    $context .= sprintf(
        "Génère un post de %d mots environ avec un ton %s. ",
        $word_count,
        $tone
    );
    
    // Ne demander des hashtags que si hashtags n'est pas 'aucun'
    if ($hashtags !== 'aucun' && $hashtag_count > 0) {
        $context .= sprintf(
            "Inclus %d hashtags pertinents à la fin du message. ",
            $hashtag_count
        );
    } else {
        $context .= "Ne pas inclure de hashtags dans le message. ";
    }

    return [
        [
            'role' => 'system',
            'content' => $context
        ],
        [
            'role' => 'user',
            'content' => $prompt
        ]
    ];
}

    private function call_openai_api($messages) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode(array_merge(
                $this->default_params,
                ['messages' => $messages]
            ))
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['error'])) {
            throw new Exception($result['error']['message']);
        }

        return $result['choices'][0]['message']['content'] ?? '';
    }

 private function format_response($content) {
    // Améliorer la ponctuation et les espacements
    $content = preg_replace('/\s+/', ' ', $content); // Normaliser les espaces
    $content = preg_replace('/\s*([.,!?])\s*/', '$1 ', $content); // Corriger la ponctuation
    
    // Séparer les hashtags sur des lignes distinctes
    $content = preg_replace('/(#[^\s]+)/', "\n\n$1", $content);
    
    // Séparer en paragraphes aux points
    $content = preg_replace('/([.!?])\s+(?=[A-Z])/', "$1\n\n", $content);
    
    // Ajouter les emojis
    $content = $this->add_emojis($content);
    
    // Assurer que les phrases sont complètes
    $content = $this->ensure_complete_sentences($content);
    
    // Ajouter un saut de ligne avant les hashtags si ce n'est pas déjà fait
    if (strpos($content, '#') !== false) {
        $parts = preg_split('/(#[^\s]+)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $content = '';
        $isFirstHashtag = true;
        
        foreach ($parts as $part) {
            if (strpos($part, '#') === 0) {
                if ($isFirstHashtag) {
                    $content .= "\n\n" . $part;
                    $isFirstHashtag = false;
                } else {
                    $content .= "\n" . $part;
                }
            } else {
                $content .= $part;
            }
        }
    }
    
    // Nettoyer les sauts de ligne multiples
    $content = preg_replace("/\n{3,}/", "\n\n", $content);
    
    // Convertir les sauts de ligne en HTML
    $content = nl2br(trim($content));
    
    return wp_kses_post($content);
}

    private function add_emojis($text) {
        $emojis = [
            'victoire' => '🏆',
            'équipe' => '⚽',
            'match' => '⚽',
            'football' => '⚽',
            'supporters' => '🎉',
            'entrainement' => '💪',
            'champion' => '🥇',
            'compétition' => '🎮',
            'but' => '🥅',
            'joueur' => '👨‍⚽',
            'joueuse' => '👩‍⚽',
            'ambiance' => '🔥',
            'performance' => '📈',
            'motivation' => '💪',
            'esprit_déquipe' => '🤝',
            'succès' => '✨',
            'weekend' => '📅'
        ];

        foreach ($emojis as $keyword => $emoji) {
            $pattern = '/\b' . str_replace('_', ' ', preg_quote($keyword, '/')) . '\b/ui';
            $text = preg_replace($pattern, '$0 ' . $emoji, $text);
        }

        return $text;
    }

    private function ensure_complete_sentences($content) {
        $content = trim($content);
        if (!preg_match('/[.!?]$/', $content)) {
            if (preg_match('/\b(et|ou|mais|donc|car)\s*$/i', $content)) {
                $content .= ' nous vous tiendrons informés de la suite.';
            }
            $content .= '.';
        }
        return $content;
    }

    private function log_error($message, $context = []) {
        if (WP_DEBUG) {
            error_log(sprintf(
                '[GPRS Error] %s | Context: %s',
                $message,
                wp_json_encode($context)
            ));
        }
    }

    public function check_requirements() {
        if (!$this->are_requirements_met()) {
            add_action('admin_notices', [$this, 'display_admin_notices']);
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }

    private function are_requirements_met() {
        if (version_compare(PHP_VERSION, GPRS_MIN_PHP_VERSION, '<')) {
            $this->last_error = sprintf(
                __('Le Générateur de Posts requiert PHP %s ou supérieur.', 'gprs'),
                GPRS_MIN_PHP_VERSION
            );
            return false;
        }

        global $wp_version;
        if (version_compare($wp_version, GPRS_MIN_WP_VERSION, '<')) {
            $this->last_error = sprintf(
                __('Le Générateur de Posts requiert WordPress %s ou supérieur.', 'gprs'),
                GPRS_MIN_WP_VERSION
            );
            return false;
        }

        return true;
    }

    public function display_admin_notices() {
        if ($this->last_error) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html($this->last_error)
            );
        }
    }

    public function activate() {
        if (!$this->are_requirements_met()) {
            wp_die($this->last_error);
        }

        $upload_dir = wp_upload_dir();
        $gprs_dir = $upload_dir['basedir'] . '/gprs-cache';
        
        if (!file_exists($gprs_dir)) {
            wp_mkdir_p($gprs_dir);
        }

        $this->init_options();
        flush_rewrite_rules();
    }

    public function deactivate() {
        $upload_dir = wp_upload_dir();
        $gprs_dir = $upload_dir['basedir'] . '/gprs-cache';
        
        if (file_exists($gprs_dir)) {
            $this->recursive_rmdir($gprs_dir);
        }
    }

    private function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
                        $this->recursive_rmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}

// Initialisation du plugin
function GPRS_init() {
    return GPRS_Generator::get_instance();
}

// Démarrer le plugin
add_action('plugins_loaded', 'GPRS_init');