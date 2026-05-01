<?php
/*
Plugin Name: Bolão Copa 2026 Elifoot
Description: Bolão da Copa do Mundo 2026 para WordPress, com visual retrô estilo Elifoot 98, participantes ilimitados, ranking ao vivo, resultados e pontuação configurável.
Version: 2.5.5
Author: Gomes & Bebes
License: GPLv2 or later
*/

if (!defined('ABSPATH')) exit;

define('BCE26_PATH', plugin_dir_path(__FILE__));
define('BCE26_URL', plugin_dir_url(__FILE__));
define('BCE26_VERSION', '2.5.5');

register_activation_hook(__FILE__, 'bce26_activate');

add_action('wp_enqueue_scripts', 'bce26_enqueue_assets');
add_action('admin_enqueue_scripts', 'bce26_enqueue_assets');
add_action('admin_menu', 'bce26_admin_menu');

add_shortcode('bolao_copa_2026', 'bce26_shortcode_app');
add_shortcode('bolao_copa_2026_palpites', 'bce26_shortcode_predictions');
add_shortcode('bolao_copa_2026_ranking', 'bce26_shortcode_ranking');


function bce26_maybe_migrate_participants_phone() {
    global $wpdb;
    $t = bce26_tables();
    $table = $t['participants'];

    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if (!$exists) return;

    $has_phone = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'telefone'");
    if (!$has_phone) {
        $wpdb->query("ALTER TABLE {$table} ADD telefone VARCHAR(190) NULL AFTER nome");
    }

    $has_wp_user_id = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'wp_user_id'");
    if (!$has_wp_user_id) {
        $wpdb->query("ALTER TABLE {$table} ADD wp_user_id BIGINT UNSIGNED NULL AFTER telefone");
        $wpdb->query("ALTER TABLE {$table} ADD INDEX wp_user_id (wp_user_id)");
    }

    // Migra dados antigos da coluna email, se ela existir.
    $has_email = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'email'");
    $has_phone = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'telefone'");
    if ($has_email && $has_phone) {
        $wpdb->query("UPDATE {$table} SET telefone = email WHERE (telefone IS NULL OR telefone = '') AND email IS NOT NULL AND email <> ''");
    }
}
add_action('admin_init', 'bce26_maybe_migrate_participants_phone');
add_action('init', 'bce26_maybe_migrate_participants_phone');

function bce26_tables() {
    global $wpdb;
    return [
        'matches' => $wpdb->prefix . 'bce26_matches',
        'participants' => $wpdb->prefix . 'bce26_participants',
        'predictions' => $wpdb->prefix . 'bce26_predictions',
        'rules' => $wpdb->prefix . 'bce26_scoring_rules',
    ];
}

function bce26_activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = bce26_tables();
    $charset = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE {$t['matches']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        match_number INT NOT NULL,
        fase VARCHAR(40) NOT NULL,
        rodada INT NULL,
        grupo VARCHAR(5) NULL,
        time_a VARCHAR(120) NOT NULL,
        time_b VARCHAR(120) NOT NULL,
        data_jogo DATE NOT NULL,
        hora_brasilia TIME NULL,
        cidade VARCHAR(120) NULL,
        pais VARCHAR(80) NULL,
        estadio VARCHAR(160) NULL,
        resultado_a INT NULL,
        resultado_b INT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'open',
        PRIMARY KEY (id),
        UNIQUE KEY match_number (match_number)
    ) $charset;");

    dbDelta("CREATE TABLE {$t['participants']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nome VARCHAR(120) NOT NULL,
        telefone VARCHAR(190) NULL,
        wp_user_id BIGINT UNSIGNED NULL,
        access_key VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY nome (nome),
        UNIQUE KEY access_key (access_key),
        KEY wp_user_id (wp_user_id)
    ) $charset;");

    dbDelta("CREATE TABLE {$t['predictions']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        participant_id BIGINT UNSIGNED NOT NULL,
        match_id BIGINT UNSIGNED NOT NULL,
        palpite_a INT NOT NULL,
        palpite_b INT NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY participant_match (participant_id, match_id),
        KEY match_id (match_id)
    ) $charset;");

    dbDelta("CREATE TABLE {$t['rules']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        fase VARCHAR(40) NOT NULL,
        pontos_exato INT NOT NULL DEFAULT 5,
        pontos_resultado INT NOT NULL DEFAULT 2,
        pontos_diferenca INT NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY fase (fase)
    ) $charset;");

    bce26_seed_matches();
    bce26_seed_rules();

    if (get_option('bce26_lock_minutes') === false) {
        update_option('bce26_lock_minutes', 5);
    }

    if (get_option('bce26_open_days_before') === false) {
        update_option('bce26_open_days_before', 7);
    }

    if (get_option('bce26_prizes') === false) {
        update_option('bce26_prizes', [1 => '🥇 1º lugar: Combo completo', 2 => '🥈 2º lugar: Burger da casa', 3 => '🥉 3º lugar: Batata + Refri']);
    }

    if (get_option('bce26_theme') === false) {
        update_option('bce26_theme', ['dark'=>'#0a2a0a','green'=>'#1a7a1a','yellow'=>'#f5c518','lime'=>'#7fff00','border'=>'#4aaa4a','white'=>'#e8e8e8','gray'=>'#b0b0b0','red'=>'#cc2200']);
    }

    if (get_option('bce26_whatsapp') === false) {
        update_option('bce26_whatsapp', ['enabled'=>'1','number'=>'','message'=>'Estou participando do Bolão Copa 2026! Minha posição: {position}º | Pontos: {points}.']);
    }
}

function bce26_seed_matches() {
    global $wpdb;
    $t = bce26_tables();
    $exists = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['matches']}");
    if ($exists > 0) return;

    $matches = include BCE26_PATH . 'includes/data/matches-2026.php';
    foreach ($matches as $m) {
        $wpdb->insert($t['matches'], [
            'match_number' => $m['match_number'],
            'fase' => $m['fase'],
            'rodada' => $m['rodada'],
            'grupo' => $m['grupo'],
            'time_a' => $m['time_a'],
            'time_b' => $m['time_b'],
            'data_jogo' => $m['data_jogo'],
            'hora_brasilia' => $m['hora_brasilia'],
            'cidade' => $m['cidade'],
            'pais' => $m['pais'],
            'estadio' => $m['estadio'],
            'status' => $m['status'],
        ]);
    }
}

function bce26_seed_rules() {
    global $wpdb;
    $t = bce26_tables();
    $exists = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['rules']}");
    if ($exists > 0) return;

    $defaults = [
        'grupos' => [5, 2, 1],
        '32avos' => [7, 3, 2],
        'oitavas' => [8, 4, 2],
        'quartas' => [10, 5, 3],
        'semifinal' => [12, 6, 3],
        'terceiro_lugar' => [10, 5, 2],
        'final' => [15, 7, 4],
    ];
    foreach ($defaults as $fase => $pts) {
        $wpdb->insert($t['rules'], [
            'fase' => $fase,
            'pontos_exato' => $pts[0],
            'pontos_resultado' => $pts[1],
            'pontos_diferenca' => $pts[2],
        ]);
    }
}

function bce26_enqueue_assets() {
    wp_enqueue_style('bce26-elifoot', BCE26_URL . 'assets/elifoot.css', [], BCE26_VERSION);
    wp_enqueue_script('bce26-arcade-js', BCE26_URL . 'assets/arcade.js', [], BCE26_VERSION, true);
}

function bce26_admin_menu() {
    add_menu_page('Bolão Copa 2026', 'Bolão Copa 2026', 'manage_options', 'bce26', 'bce26_admin_matches', 'dashicons-awards', 26);
    add_submenu_page('bce26', 'Jogos e Resultados', 'Jogos e Resultados', 'manage_options', 'bce26', 'bce26_admin_matches');
    add_submenu_page('bce26', 'Participantes', 'Participantes', 'manage_options', 'bce26-participants', 'bce26_admin_participants');
    add_submenu_page('bce26', 'Pontuação', 'Pontuação', 'manage_options', 'bce26-rules', 'bce26_admin_rules');
    add_submenu_page('bce26', 'Textos e Títulos', 'Textos e Títulos', 'manage_options', 'bce26-texts', 'bce26_admin_texts');
    add_submenu_page('bce26', 'Produto e Marca', 'Produto e Marca', 'manage_options', 'bce26-product', 'bce26_admin_product');
}

function bce26_get_matches($only_open_for_betting = false) {
    global $wpdb;
    $t = bce26_tables();
    $rows = $wpdb->get_results("SELECT * FROM {$t['matches']} ORDER BY match_number ASC");
    if (!$only_open_for_betting) return $rows;

    $out = [];
    foreach ($rows as $m) {
        if (bce26_match_is_open_for_prediction($m)) $out[] = $m;
    }
    return $out;
}

function bce26_match_is_open_for_prediction($m) {
    if ($m->status !== 'open') return false;
    if (empty($m->hora_brasilia)) return false;

    $lock = (int) get_option('bce26_lock_minutes', 5);
    $open_days_before = max(0, (int) get_option('bce26_open_days_before', 7));
    $match_ts = strtotime($m->data_jogo . ' ' . $m->hora_brasilia . ' America/Sao_Paulo');
    if (!$match_ts) return false;

    $now = time();
    $opens_at = $match_ts - ($open_days_before * DAY_IN_SECONDS);
    $locks_at = $match_ts - ($lock * 60);

    return ($now >= $opens_at) && ($now < $locks_at);
}

function bce26_get_or_create_participant($nome, $telefone = '') {
    global $wpdb;
    $t = bce26_tables();

    bce26_maybe_migrate_participants_phone();

    $nome = trim(sanitize_text_field($nome));
    $telefone = sanitize_text_field($telefone);
    $current_user_id = is_user_logged_in() ? get_current_user_id() : 0;

    if ($nome === '') {
        return new WP_Error('empty_name', 'Informe um nome.');
    }

    if ($telefone === '') {
        return new WP_Error('invalid_phone', 'Informe um telefone válido. Para Brasil, use DDD com 2 dígitos + número com 9 dígitos.');
    }

    $cookie = isset($_COOKIE['bce26_key']) ? sanitize_text_field($_COOKIE['bce26_key']) : '';

    // Se o usuário WordPress já tem participante vinculado, usa esse registro.
    if ($current_user_id) {
        $linked = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['participants']} WHERE wp_user_id = %d", $current_user_id));
        if ($linked) {
            $name_taken = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$t['participants']} WHERE nome = %s AND id <> %d",
                $nome,
                $linked->id
            ));
            if ($name_taken) {
                return new WP_Error('name_exists', 'Esse nome já está em uso por outro participante. Escolha outro nome.');
            }

            $wpdb->update($t['participants'], [
                'nome' => $nome,
                'telefone' => $telefone,
            ], ['id' => $linked->id]);

            setcookie('bce26_key', $linked->access_key, time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE['bce26_key'] = $linked->access_key;

            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['participants']} WHERE id = %d", $linked->id));
        }
    }

    // Se já existe participante nesse navegador, permite editar o próprio nome e telefone.
    if ($cookie) {
        $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['participants']} WHERE access_key = %s", $cookie));

        if ($current) {
            $name_taken = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$t['participants']} WHERE nome = %s AND id <> %d",
                $nome,
                $current->id
            ));

            if ($name_taken) {
                return new WP_Error('name_exists', 'Esse nome já está em uso por outro participante. Escolha outro nome.');
            }

            $update = [
                'nome' => $nome,
                'telefone' => $telefone,
            ];
            if ($current_user_id) {
                $update['wp_user_id'] = $current_user_id;
            }

            $wpdb->update($t['participants'], $update, ['id' => $current->id]);

            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['participants']} WHERE id = %d", $current->id));
        }
    }

    // Primeiro cadastro: nome continua único.
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['participants']} WHERE nome = %s", $nome));
    if ($existing) {
        return new WP_Error('name_exists', 'Esse nome já está em uso. Use exatamente o mesmo navegador do primeiro envio, faça login na sua conta ou escolha outro nome.');
    }

    $key = wp_generate_password(48, false, false);
    $insert = [
        'nome' => $nome,
        'telefone' => $telefone,
        'access_key' => $key,
        'created_at' => current_time('mysql'),
    ];
    if ($current_user_id) {
        $insert['wp_user_id'] = $current_user_id;
    }

    $wpdb->insert($t['participants'], $insert);

    if (!$wpdb->insert_id) {
        return new WP_Error('participant_insert_failed', 'Não foi possível cadastrar o participante. Tente novamente.');
    }

    setcookie('bce26_key', $key, time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
    $_COOKIE['bce26_key'] = $key;

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['participants']} WHERE id = %d", $wpdb->insert_id));
}

function bce26_current_participant() {
    global $wpdb;
    $t = bce26_tables();

    bce26_maybe_migrate_participants_phone();

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $participant = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['participants']} WHERE wp_user_id = %d", $user_id));
        if ($participant) {
            if (!empty($participant->access_key)) {
                setcookie('bce26_key', $participant->access_key, time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
                $_COOKIE['bce26_key'] = $participant->access_key;
            }
            return $participant;
        }
    }

    $key = isset($_COOKIE['bce26_key']) ? sanitize_text_field($_COOKIE['bce26_key']) : '';
    if (!$key) return null;

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['participants']} WHERE access_key = %s", $key));
}








function bce26_get_texts() {
    $defaults = [
        'top_title' => '⚽ GOMES E BEBES — BOLÃO COPA 2026',
        'top_subtitle' => 'WORDPRESS PLUGIN · ESTILO ELIFOOT 98 · RANKING AO VIVO',
        'brand_title' => 'GOMES E BEBES',
        'brand_copy' => '© 2022 - Melhores pessoas',
        'rules_title' => 'REGRAS DO BOLÃO',
        'card_1_title' => '👤 PARTICIPANTE',
        'card_1_text' => 'Use um nome único. Esse nome será usado no ranking geral do bolão.',
        'card_2_title' => '✍️ PALPITES',
        'card_2_text' => 'Você pode editar cada palpite até {lock_minutes} minutos antes do jogo.',
        'card_3_title' => '🏆 RANKING AO VIVO',
        'card_3_text' => 'A pontuação muda quando os resultados oficiais forem lançados pelo administrador.',
        'card_4_title' => '🎯 CRITÉRIO',
        'card_4_text' => 'Placar exato vale mais. Resultado correto e diferença de gols também contam pontos.',
        'score_title' => 'SISTEMA DE PONTUAÇÃO',
        'score_note' => '💾 Pontuação editável em: Painel WordPress → Bolão Copa 2026 → Pontuação',
        'status_left' => 'ONLINE',
        'status_center' => 'GOMES E BEBES CUP MANAGER',
        'status_right' => '⚙ MODULAR',
        'signup_title' => '🕹️ CADASTRO DO JOGADOR',
        'signup_help' => 'Escolha um nome único para aparecer no ranking. Você pode alterar seu nome e telefone depois. Para acessar em outro dispositivo, crie um acesso WordPress opcional após salvar.',
        'signup_name_label' => 'Nome no ranking',
        'signup_telefone_label' => 'Telefone',
    ];

    $saved = get_option('bce26_texts', []);
    if (!is_array($saved)) {
        $saved = [];
    }

    $texts = array_merge($defaults, array_intersect_key($saved, $defaults));

    // v2.4.3: options individuais têm prioridade sobre o array.
    foreach ($defaults as $key => $default_value) {
        $individual = get_option('bce26_text_' . $key, null);
        if ($individual !== null) {
            $texts[$key] = (string) $individual;
        }
    }

    return $texts;
}

function bce26_text($key) {
    $texts = bce26_get_texts();
    $value = isset($texts[$key]) ? (string) $texts[$key] : '';
    return str_replace('{lock_minutes}', intval(get_option('bce26_lock_minutes', 5)), $value);
}

function bce26_render_header_regras() {
    global $wpdb;
    $t = bce26_tables();
    $rules = $wpdb->get_results("SELECT * FROM {$t['rules']} ORDER BY id ASC");

    ob_start();
    ?>
    <div class="bce26-wrapper bce26-hero">
        <div class="bce26-titlebar2">
            <div>
                <div class="bce26-titlebar-text"><?php echo esc_html(bce26_text('top_title')); ?></div>
                <div class="bce26-titlebar-sub"><?php echo esc_html(bce26_text('top_subtitle')); ?></div>
            </div>
            <div class="bce26-titlebar-badge">v<?php echo esc_html(BCE26_VERSION); ?></div>
        </div>

        <div class="bce26-brand-screen">
            <div class="bce26-brand-title"><?php echo esc_html(bce26_text('brand_title')); ?></div>
            <div class="bce26-brand-copy"><?php echo esc_html(bce26_text('brand_copy')); ?></div>
        </div>

        <div class="bce26-section">
            <div class="bce26-section-title"><?php echo esc_html(bce26_text('rules_title')); ?></div>
            <div class="bce26-feature-grid">
                <div class="bce26-feature-card">
                    <div class="bce26-feature-label"><?php echo esc_html(bce26_text('card_1_title')); ?></div>
                    <div class="bce26-feature-desc"><?php echo esc_html(bce26_text('card_1_text')); ?></div>
                </div>
                <div class="bce26-feature-card">
                    <div class="bce26-feature-label"><?php echo esc_html(bce26_text('card_2_title')); ?></div>
                    <div class="bce26-feature-desc"><?php echo esc_html(bce26_text('card_2_text')); ?></div>
                </div>
                <div class="bce26-feature-card">
                    <div class="bce26-feature-label"><?php echo esc_html(bce26_text('card_3_title')); ?></div>
                    <div class="bce26-feature-desc"><?php echo esc_html(bce26_text('card_3_text')); ?></div>
                </div>
                <div class="bce26-feature-card">
                    <div class="bce26-feature-label"><?php echo esc_html(bce26_text('card_4_title')); ?></div>
                    <div class="bce26-feature-desc"><?php echo esc_html(bce26_text('card_4_text')); ?></div>
                </div>
            </div>
        </div>

        <div class="bce26-section">
            <div class="bce26-section-title"><?php echo esc_html(bce26_text('score_title')); ?></div>
            <div class="bce26-score-rules">
                <?php foreach ($rules as $r): ?>
                    <div class="bce26-score-rule <?php echo ($r->fase === 'final') ? 'bce26-final-card' : ''; ?>">
                        <?php if ($r->fase === 'final'): ?><span class="bce26-cup-8bit" aria-hidden="true"></span><?php endif; ?><span class="bce26-score-phase"><?php echo esc_html(strtoupper(str_replace('_', ' ', $r->fase))); ?></span>
                        <span class="bce26-score-value"><?php echo intval($r->pontos_exato); ?></span>
                        <span class="bce26-score-label">placar exato</span>
                        <span class="bce26-score-mini"><?php echo intval($r->pontos_resultado); ?> resultado · <?php echo intval($r->pontos_diferenca); ?> diferença</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="bce26-note"><?php echo esc_html(bce26_text('score_note')); ?></div>
        </div>

        <div class="bce26-statusbar">
            <span><span class="bce26-live-dot"></span><?php echo esc_html(bce26_text('status_left')); ?></span>
            <span><?php echo esc_html(bce26_text('status_center')); ?></span>
            <span><?php echo esc_html(bce26_text('status_right')); ?></span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


function bce26_get_theme() {
    $defaults = [
        'dark' => '#0a2a0a',
        'green' => '#1a7a1a',
        'yellow' => '#f5c518',
        'lime' => '#7fff00',
        'border' => '#4aaa4a',
        'white' => '#e8e8e8',
        'gray' => '#b0b0b0',
        'red' => '#cc2200',
    ];

    $saved_array = get_option('bce26_theme', []);
    if (!is_array($saved_array)) {
        $saved_array = [];
    }

    $theme = $defaults;

    foreach ($defaults as $key => $default_value) {
        $individual = get_option('bce26_theme_' . $key, null);

        if ($individual !== null && $individual !== '') {
            $color = sanitize_hex_color($individual);
            $theme[$key] = $color ?: $default_value;
            continue;
        }

        if (isset($saved_array[$key]) && $saved_array[$key] !== '') {
            $color = sanitize_hex_color($saved_array[$key]);
            $theme[$key] = $color ?: $default_value;
        }
    }

    return $theme;
}

function bce26_print_theme_vars() {
    $t = bce26_get_theme();
    echo '<style id="bce26-theme-vars">:root{';
    echo '--elifoot-dark:' . esc_html($t['dark']) . ';--elifoot-green:' . esc_html($t['green']) . ';--elifoot-yellow:' . esc_html($t['yellow']) . ';--elifoot-lime:' . esc_html($t['lime']) . ';--elifoot-border:' . esc_html($t['border']) . ';--elifoot-white:' . esc_html($t['white']) . ';--elifoot-gray:' . esc_html($t['gray']) . ';--elifoot-red:' . esc_html($t['red']) . ';';
    echo '}</style>';
}
add_action('wp_head', 'bce26_print_theme_vars', 30);
add_action('admin_head', 'bce26_print_theme_vars', 30);

function bce26_get_prizes() {
    $defaults = [
        1 => '🥇 1º lugar: Combo completo',
        2 => '🥈 2º lugar: Burger da casa',
        3 => '🥉 3º lugar: Batata + Refri',
    ];

    $saved_array = get_option('bce26_prizes', []);
    if (!is_array($saved_array)) {
        $saved_array = [];
    }

    $prizes = $defaults;

    for ($i = 1; $i <= 3; $i++) {
        $individual = get_option('bce26_prize_' . $i, null);

        if ($individual !== null) {
            $prizes[$i] = (string) $individual;
            continue;
        }

        if (isset($saved_array[$i])) {
            $prizes[$i] = (string) $saved_array[$i];
        }
    }

    return $prizes;
}

function bce26_get_whatsapp_config() {
    $defaults = [
        'enabled' => '1',
        'number' => '',
        'message' => 'Estou participando do Bolão Copa 2026! Minha posição: {position}º | Pontos: {points}.',
    ];

    $saved_array = get_option('bce26_whatsapp', []);
    if (!is_array($saved_array)) {
        $saved_array = [];
    }

    $config = $defaults;

    foreach ($defaults as $key => $default_value) {
        $individual = get_option('bce26_whatsapp_' . $key, null);

        if ($individual !== null) {
            $config[$key] = (string) $individual;
            continue;
        }

        if (isset($saved_array[$key])) {
            $config[$key] = (string) $saved_array[$key];
        }
    }

    $config['enabled'] = ($config['enabled'] === '1') ? '1' : '0';
    $config['number'] = preg_replace('/\D+/', '', (string) $config['number']);

    return $config;
}

function bce26_whatsapp_link($participant_name = '', $points = 0, $position = '-') {
    $c = bce26_get_whatsapp_config();
    if (($c['enabled'] ?? '1') !== '1') return '';
    $number = preg_replace('/\D+/', '', $c['number'] ?? '');
    if (!$number) return '';
    $msg = str_replace(['{name}','{points}','{position}'], [$participant_name, (string)$points, (string)$position], (string)$c['message']);
    return 'https://wa.me/' . rawurlencode($number) . '?text=' . rawurlencode($msg);
}

function bce26_render_top3_prizes() {
    $ranking = bce26_get_ranking();
    $prizes = bce26_get_prizes();
    ob_start(); ?>
    <div class="bce26-wrap bce26-top3-wrap"><div class="bce26-window bce26-top3-window">
        <div class="bce26-titlebar">🏆 TOP 3 DO BOLÃO</div>
        <div class="bce26-top3-grid">
        <?php for ($i=1; $i<=3; $i++):
            $item = $ranking[$i-1] ?? null;
            $name = $item ? $item['nome'] : 'VAGA ABERTA';
            $points = $item ? intval($item['pontos']) : 0;
            $exact = $item ? intval($item['exatos']) : 0; ?>
            <div class="bce26-top3-card bce26-top3-pos-<?php echo intval($i); ?>">
                <div class="bce26-top3-medal"><?php echo $i===1?'🥇':($i===2?'🥈':'🥉'); ?></div>
                <div class="bce26-top3-position"><?php echo intval($i); ?>º LUGAR</div>
                <div class="bce26-top3-name"><?php echo esc_html($name); ?></div>
                <div class="bce26-top3-points"><?php echo intval($points); ?> pts</div>
                <div class="bce26-top3-exact"><?php echo intval($exact); ?> exatos</div>
                <div class="bce26-top3-prize"><?php echo esc_html($prizes[$i] ?? ''); ?></div>
            </div>
        <?php endfor; ?>
        </div><div class="bce26-top3-note">🔥 Suba no ranking para entrar na zona de premiação.</div>
    </div></div>
    <?php return ob_get_clean();
}

function bce26_render_whatsapp_share() {
    $participant = bce26_current_participant();
    if (!$participant) return '';
    $ranking = bce26_get_ranking();
    $pos = '-'; $points = 0;
    foreach ($ranking as $i => $row) {
        if ($row['nome'] === $participant->nome) { $pos = $i + 1; $points = $row['pontos']; break; }
    }
    $link = bce26_whatsapp_link($participant->nome, $points, $pos);
    if (!$link) return '';
    return '<div class="bce26-whatsapp-share"><a href="' . esc_url($link) . '" target="_blank" rel="noopener">📲 Compartilhar no WhatsApp</a></div>';
}

function bce26_shortcode_app() {
    return bce26_render_header_regras() . bce26_render_top3_prizes() . bce26_shortcode_predictions() . bce26_shortcode_ranking();
}


function bce26_team_flag_code($team) {
    $team = trim((string) $team);

    $codes = [
        'México' => 'mx',
        'África do Sul' => 'za',
        'República da Coreia' => 'kr',
        'Coreia do Sul' => 'kr',
        'República Tcheca' => 'cz',
        'Canadá' => 'ca',
        'Bósnia e Herzegovina' => 'ba',
        'Catar' => 'qa',
        'Suíça' => 'ch',
        'Estados Unidos' => 'us',
        'Paraguai' => 'py',
        'Austrália' => 'au',
        'Turquia' => 'tr',
        'Brasil' => 'br',
        'Marrocos' => 'ma',
        'Haiti' => 'ht',
        'Escócia' => 'gb-sct',
        'Alemanha' => 'de',
        'Curaçau' => 'cw',
        'Costa do Marfim' => 'ci',
        'Equador' => 'ec',
        'Holanda' => 'nl',
        'Japão' => 'jp',
        'Suécia' => 'se',
        'Tunísia' => 'tn',
        'Bélgica' => 'be',
        'Egito' => 'eg',
        'Irã' => 'ir',
        'Nova Zelândia' => 'nz',
        'Espanha' => 'es',
        'Cabo Verde' => 'cv',
        'Arábia Saudita' => 'sa',
        'Uruguai' => 'uy',
        'França' => 'fr',
        'Senegal' => 'sn',
        'Iraque' => 'iq',
        'Noruega' => 'no',
        'Áustria' => 'at',
        'Jordânia' => 'jo',
        'Argentina' => 'ar',
        'Argélia' => 'dz',
        'Portugal' => 'pt',
        'República Democrática do Congo' => 'cd',
        'Uzbequistão' => 'uz',
        'Colômbia' => 'co',
        'Inglaterra' => 'gb-eng',
        'Croácia' => 'hr',
        'Gana' => 'gh',
        'Panamá' => 'pa',
    ];

    return $codes[$team] ?? '';
}

function bce26_team_flag_img($team) {
    $code = bce26_team_flag_code($team);

    if (!$code) {
        return '<span class="bce26-flag bce26-flag-fallback" aria-hidden="true">🏳️</span>';
    }

    $src = 'https://flagcdn.com/w40/' . strtolower($code) . '.png';

    return '<img class="bce26-flag-img" src="' . esc_url($src) . '" alt="" loading="lazy" decoding="async">';
}

function bce26_team_name_with_flag($team) {
    $team = trim((string) $team);
    return '<span class="bce26-team">' . bce26_team_flag_img($team) . '<span class="bce26-team-name">' . esc_html($team) . '</span></span>';
}

function bce26_match_name_with_flags($match) {
    return '<div class="bce26-matchline">' . bce26_team_name_with_flag($match->time_a) . '<span class="bce26-versus">x</span>' . bce26_team_name_with_flag($match->time_b) . '</div>';
}


function bce26_render_player_hud() {
    $participant = bce26_current_participant();
    if (!$participant) {
        return '<div class="bce26-player-hud bce26-hud-guest"><span>👾 JOGADOR: VISITANTE</span><span>FAÇA SEU PRIMEIRO PALPITE</span><span>PRESS START</span></div>';
    }

    $ranking = bce26_get_ranking();
    $pos = '-';
    $points = 0;

    foreach ($ranking as $i => $row) {
        if ($row['nome'] === $participant->nome) {
            $pos = $i + 1;
            $points = $row['pontos'];
            break;
        }
    }

    return '<div class="bce26-player-hud"><span>👾 JOGADOR: ' . esc_html($participant->nome) . '</span><span>🏆 PONTOS: ' . intval($points) . '</span><span>📊 POSIÇÃO: ' . esc_html($pos) . 'º</span></div>';
}



function bce26_phone_country_options() {
    return [
        '55' => '🇧🇷 +55 Brasil',
        '1' => '🇺🇸 +1 EUA/Canadá',
        '54' => '🇦🇷 +54 Argentina',
        '56' => '🇨🇱 +56 Chile',
        '57' => '🇨🇴 +57 Colômbia',
        '52' => '🇲🇽 +52 México',
        '351' => '🇵🇹 +351 Portugal',
        '34' => '🇪🇸 +34 Espanha',
        '44' => '🇬🇧 +44 Reino Unido',
        '33' => '🇫🇷 +33 França',
        '49' => '🇩🇪 +49 Alemanha',
        '39' => '🇮🇹 +39 Itália',
        '81' => '🇯🇵 +81 Japão',
    ];
}

function bce26_sanitize_phone_with_ddi($ddi, $phone) {
    $ddi = preg_replace('/\D+/', '', (string) $ddi);
    $digits = preg_replace('/\D+/', '', (string) $phone);

    $allowed = array_keys(bce26_phone_country_options());
    if (!in_array($ddi, $allowed, true)) {
        $ddi = '55';
    }

    // Brasil: DDD com 2 dígitos + celular com 9 dígitos.
    if ($ddi === '55') {
        if (strlen($digits) === 13 && substr($digits, 0, 2) === '55') {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) !== 11) {
            return '';
        }
    } else {
        // Internacional: mantém flexível, mas exige de 6 a 14 dígitos.
        if (strlen($digits) < 6 || strlen($digits) > 14) {
            return '';
        }
    }

    return '+' . $ddi . $digits;
}

function bce26_sanitize_br_phone($phone) {
    return bce26_sanitize_phone_with_ddi('55', $phone);
}

function bce26_phone_parts($phone) {
    $digits = preg_replace('/\D+/', '', (string) $phone);
    $parts = [
        'ddi' => '55',
        'number' => '',
    ];

    foreach (array_keys(bce26_phone_country_options()) as $ddi) {
        if (strpos($digits, $ddi) === 0) {
            $parts['ddi'] = $ddi;
            $parts['number'] = substr($digits, strlen($ddi));
            return $parts;
        }
    }

    if (strlen($digits) === 11) {
        $parts['ddi'] = '55';
        $parts['number'] = $digits;
    }

    return $parts;
}

function bce26_format_phone($phone) {
    $parts = bce26_phone_parts($phone);
    if (!$parts['number']) return '';

    if ($parts['ddi'] === '55' && strlen($parts['number']) === 11) {
        return '+55 (' . substr($parts['number'], 0, 2) . ') ' . substr($parts['number'], 2, 5) . '-' . substr($parts['number'], 7, 4);
    }

    return '+' . $parts['ddi'] . ' ' . $parts['number'];
}

function bce26_format_br_phone($phone) {
    return bce26_format_phone($phone);
}




function bce26_current_url() {
    $scheme = is_ssl() ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? parse_url(home_url(), PHP_URL_HOST);
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    return esc_url_raw($scheme . $host . $request_uri);
}

function bce26_login_redirect_to_bolao($redirect_to, $requested_redirect_to, $user) {
    if (!empty($requested_redirect_to)) {
        return $requested_redirect_to;
    }

    if (isset($_REQUEST['bce26_from_bolao']) && $_REQUEST['bce26_from_bolao'] === '1') {
        return home_url(add_query_arg([], $_SERVER['REQUEST_URI'] ?? '/'));
    }

    return $redirect_to;
}
add_filter('login_redirect', 'bce26_login_redirect_to_bolao', 10, 3);

function bce26_login_wp_user_inline($login, $password, $participant = null) {
    global $wpdb;
    $t = bce26_tables();

    $login = sanitize_text_field($login);
    $password = (string) $password;

    if ($login === '' || $password === '') {
        return new WP_Error('missing_login', 'Informe e-mail/usuário e senha.');
    }

    $user = wp_signon([
        'user_login' => $login,
        'user_password' => $password,
        'remember' => true,
    ], is_ssl());

    if (is_wp_error($user)) {
        return new WP_Error('login_failed', 'Não foi possível entrar. Confira e-mail/usuário e senha.');
    }

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    bce26_maybe_migrate_participants_phone();

    $linked = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['participants']} WHERE wp_user_id = %d", $user->ID));

    if ($linked) {
        if (!empty($linked->access_key)) {
            setcookie('bce26_key', $linked->access_key, time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE['bce26_key'] = $linked->access_key;
        }
        return $user;
    }

    // Se está com participante por cookie e a conta ainda não tem participante, vincula.
    if ($participant && !empty($participant->id)) {
        $wpdb->update($t['participants'], ['wp_user_id' => $user->ID], ['id' => $participant->id]);
        return $user;
    }

    return $user;
}

function bce26_generate_unique_username($base) {
    $base = sanitize_user($base, true);
    if (!$base) {
        $base = 'bolao';
    }

    $username = $base;
    $i = 1;

    while (username_exists($username)) {
        $username = $base . '_' . $i;
        $i++;
    }

    return $username;
}

function bce26_create_or_link_wp_user_for_participant($participant, $email, $password) {
    global $wpdb;
    $t = bce26_tables();

    if (!$participant || empty($participant->id)) {
        return new WP_Error('no_participant', 'Salve seus palpites antes de criar o acesso.');
    }

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();

        $already_linked = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['participants']} WHERE wp_user_id = %d AND id <> %d",
            $user_id,
            $participant->id
        ));

        if ($already_linked) {
            return new WP_Error('user_already_linked', 'Essa conta WordPress já está vinculada a outro participante.');
        }

        $wpdb->update($t['participants'], ['wp_user_id' => $user_id], ['id' => $participant->id]);
        return get_user_by('id', $user_id);
    }

    $email = sanitize_email($email);
    if (!$email || !is_email($email)) {
        return new WP_Error('invalid_email', 'Informe um e-mail válido para criar o acesso.');
    }

    if (email_exists($email)) {
        return new WP_Error('email_exists', 'Esse e-mail já possui uma conta. Faça login no WordPress e volte ao bolão para vincular seus palpites.');
    }

    if (strlen((string) $password) < 6) {
        return new WP_Error('weak_password', 'Use uma senha com pelo menos 6 caracteres.');
    }

    $phone_digits = preg_replace('/\D+/', '', (string) ($participant->telefone ?? ''));
    $base = $phone_digits ? 'bolao_' . $phone_digits : 'bolao_' . sanitize_title($participant->nome);
    $username = bce26_generate_unique_username($base);

    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    wp_update_user([
        'ID' => $user_id,
        'display_name' => $participant->nome,
        'nickname' => $participant->nome,
    ]);

    update_user_meta($user_id, 'bce26_phone', $participant->telefone);

    $wpdb->update($t['participants'], ['wp_user_id' => $user_id], ['id' => $participant->id]);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    return get_user_by('id', $user_id);
}

function bce26_render_account_access_panel($participant) {
    $current_url = bce26_current_url();

    $create_box = '';
    $login_box = '';

    if (is_user_logged_in()) {
        if ($participant && !empty($participant->wp_user_id) && intval($participant->wp_user_id) === get_current_user_id()) {
            $user = wp_get_current_user();
            $create_box = '<div class="bce26-account-box bce26-account-box-ok">
                <div class="bce26-account-subtitle">Acesso vinculado</div>
                <div class="bce26-account-status">✅ Seus palpites estão vinculados à conta: <strong>' . esc_html($user->user_email) . '</strong></div>
            </div>';
        } elseif ($participant) {
            $create_box = '<div class="bce26-account-box">
                <div class="bce26-account-subtitle">Vincular conta atual</div>
                <div class="bce26-account-copy">Você está logado no WordPress. Vincule esta conta aos seus palpites para acessar em outro dispositivo.</div>
                <button class="bce26-button bce26-account-button" type="submit" name="bce26_account_action" value="link_logged_user" formnovalidate>Vincular minha conta</button>
            </div>';
        }
    } else {
        if ($participant && empty($participant->wp_user_id)) {
            $create_box = '<div class="bce26-account-box">
                <div class="bce26-account-subtitle">Criar acesso</div>
                <div class="bce26-account-copy">Crie um acesso com e-mail e senha para recuperar seus palpites em outro dispositivo.</div>
                <div class="bce26-account-fields">
                    <label><span>E-mail</span><input type="email" name="bce26_create_email" placeholder="voce@email.com"></label>
                    <label><span>Senha</span><input type="password" name="bce26_create_password" minlength="6" placeholder="mínimo 6 caracteres"></label>
                </div>
                <button class="bce26-button bce26-account-button" type="submit" name="bce26_account_action" value="create_user" formnovalidate>Criar acesso</button>
            </div>';
        } elseif ($participant && !empty($participant->wp_user_id)) {
            $create_box = '<div class="bce26-account-box bce26-account-box-ok">
                <div class="bce26-account-subtitle">Acesso já criado</div>
                <div class="bce26-account-status">🔐 Este participante já tem acesso WordPress. Entre ao lado para carregar os palpites.</div>
            </div>';
        } else {
            $create_box = '<div class="bce26-account-box bce26-account-box-muted">
                <div class="bce26-account-subtitle">Criar acesso</div>
                <div class="bce26-account-copy">Primeiro preencha o cadastro do jogador e salve seus palpites. Depois a opção de criar acesso aparecerá aqui.</div>
            </div>';
        }

        $login_box = '<div class="bce26-account-box">
            <div class="bce26-account-subtitle">Já tenho acesso</div>
            <div class="bce26-account-copy">Entre aqui mesmo para carregar seus palpites neste dispositivo.</div>
            <div class="bce26-account-fields">
                <label><span>E-mail/usuário</span><input type="text" name="bce26_login_user" placeholder="seu e-mail"></label>
                <label><span>Senha</span><input type="password" name="bce26_login_password" placeholder="sua senha"></label>
            </div>
            <button class="bce26-button bce26-account-button" type="submit" name="bce26_account_action" value="login_user" formnovalidate>Entrar</button>
            <div class="bce26-account-login">Esqueceu a senha? <a href="' . esc_url(wp_lostpassword_url($current_url)) . '">Recuperar senha</a></div>
        </div>';
    }

    if (!$create_box && !$login_box) {
        return '';
    }

    return '<div class="bce26-account-panel">
        ' . wp_nonce_field('bce26_account_access', 'bce26_account_nonce', true, false) . '
        <div class="bce26-account-title">🔐 Acesso do participante</div>
        <div class="bce26-account-copy">Opcional: crie ou acesse sua conta para recuperar palpites em outro computador ou celular.</div>
        <div class="bce26-account-grid">' . $create_box . $login_box . '</div>
    </div>';
}

function bce26_shortcode_predictions() {
    global $wpdb;
    $t = bce26_tables();
    $message = '';
    $participant = bce26_current_participant();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bce26_account_action']) && isset($_POST['bce26_account_nonce']) && wp_verify_nonce($_POST['bce26_account_nonce'], 'bce26_account_access')) {
        $account_action = sanitize_text_field(wp_unslash($_POST['bce26_account_action'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['bce26_create_email'] ?? ''));
        $password = (string) wp_unslash($_POST['bce26_create_password'] ?? '');

        if ($account_action === 'login_user') {
            // Login precisa funcionar mesmo sem participante/cookie no navegador.
            $login = sanitize_text_field(wp_unslash($_POST['bce26_login_user'] ?? ''));
            $login_password = (string) wp_unslash($_POST['bce26_login_password'] ?? '');
            $user = bce26_login_wp_user_inline($login, $login_password, $participant);

            if (is_wp_error($user)) {
                $message = '<div class="bce26-alert bce26-error">' . esc_html($user->get_error_message()) . '</div>';
            } else {
                $participant = bce26_current_participant();
                $message = '<div class="bce26-alert bce26-success">Login realizado com sucesso. Seus palpites foram carregados.</div>';
            }
        } else {
            if (!$participant) {
                $message = '<div class="bce26-alert bce26-error">Salve seus palpites antes de criar o acesso WordPress.</div>';
            } else {
                $user = bce26_create_or_link_wp_user_for_participant($participant, $email, $password);

                if (is_wp_error($user)) {
                    $message = '<div class="bce26-alert bce26-error">' . esc_html($user->get_error_message()) . '</div>';
                } else {
                    $participant = bce26_current_participant();
                    $message = '<div class="bce26-alert bce26-success">Acesso WordPress vinculado com sucesso. Agora você consegue recuperar seus palpites em outro dispositivo.</div>';
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['bce26_account_action']) && isset($_POST['bce26_predictions_nonce']) && wp_verify_nonce($_POST['bce26_predictions_nonce'], 'bce26_save_predictions')) {
        $telefone_post = bce26_sanitize_phone_with_ddi(wp_unslash($_POST['bce26_ddi'] ?? '55'), wp_unslash($_POST['bce26_phone'] ?? ($_POST['bce26_telefone'] ?? '')));
        $participant = bce26_get_or_create_participant(wp_unslash($_POST['bce26_nome'] ?? ''), $telefone_post);
        if (is_wp_error($participant)) {
            $message = '<div class="bce26-alert bce26-error">' . esc_html($participant->get_error_message()) . '</div>';
        } else {
            $preds = isset($_POST['pred']) && is_array($_POST['pred']) ? $_POST['pred'] : [];
            $saved = 0;
            foreach ($preds as $match_id => $p) {
                $match_id = absint($match_id);
                $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['matches']} WHERE id = %d", $match_id));
                if (!$m || !bce26_match_is_open_for_prediction($m)) continue;
                if (!isset($p['a'], $p['b']) || $p['a'] === '' || $p['b'] === '') continue;
                $a = max(0, intval($p['a']));
                $b = max(0, intval($p['b']));

                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['predictions']} WHERE participant_id=%d AND match_id=%d", $participant->id, $match_id));
                if ($existing) {
                    $wpdb->update($t['predictions'], ['palpite_a'=>$a,'palpite_b'=>$b,'updated_at'=>current_time('mysql')], ['id'=>$existing]);
                } else {
                    $wpdb->insert($t['predictions'], ['participant_id'=>$participant->id,'match_id'=>$match_id,'palpite_a'=>$a,'palpite_b'=>$b,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')]);
                }
                $saved++;
            }
            $message = '<div class="bce26-alert bce26-success">Palpites salvos: ' . intval($saved) . '.</div>';
        }
    }

    $matches = bce26_get_matches(false);
    $my_preds = [];
    if ($participant) {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['predictions']} WHERE participant_id=%d", $participant->id));
        foreach ($rows as $r) $my_preds[$r->match_id] = $r;
    }

    ob_start();
    ?>
    <div class="bce26-wrap">
        <div class="bce26-window">
            <div class="bce26-titlebar">Bolão Copa 2026 — Palpites</div>
            <?php echo $message; ?>
            <?php echo bce26_render_player_hud(); ?>
            <?php echo bce26_render_whatsapp_share(); ?>
            <form method="post">
                <?php wp_nonce_field('bce26_save_predictions', 'bce26_predictions_nonce'); ?>
                <div class="bce26-signup-panel">
                    <div class="bce26-signup-copy">
                        <div class="bce26-signup-title"><?php echo esc_html(bce26_text('signup_title')); ?></div>
                        <div class="bce26-signup-help"><?php echo esc_html(bce26_text('signup_help')); ?></div>
                    </div>
                    <div class="bce26-player">
                        <label class="bce26-field">
                            <span><?php echo esc_html(bce26_text('signup_name_label')); ?></span>
                            <input name="bce26_nome" required value="<?php echo esc_attr($participant->nome ?? ''); ?>" placeholder="Ex: Técnico Gomes">
                        </label>
                        <label class="bce26-field bce26-phone-field">
                            <span><?php echo esc_html(bce26_text('signup_email_label')); ?></span>
                            <?php $phone_parts = bce26_phone_parts($participant->telefone ?? ''); ?>
                            <div class="bce26-phone-input-wrap bce26-phone-input-wrap-ddi">
                                <select
                                    name="bce26_ddi"
                                    class="bce26-ddi-select"
                                    aria-label="Código do país"
                                >
                                    <?php foreach (bce26_phone_country_options() as $ddi_code => $ddi_label): ?>
                                        <option value="<?php echo esc_attr($ddi_code); ?>" <?php selected($phone_parts['ddi'], $ddi_code); ?>>
                                            <?php echo esc_html($ddi_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input
                                    type="tel"
                                    name="bce26_phone"
                                    required
                                    value="<?php echo esc_attr($phone_parts['number']); ?>"
                                    placeholder="61999999999"
                                    inputmode="numeric"
                                    pattern="[0-9]{6,14}"
                                    minlength="6"
                                    maxlength="14"
                                    title="Para Brasil: DDD com 2 dígitos + número com 9 dígitos. Exemplo: 61999999999"
                                >
                            </div>
                            <small class="bce26-field-help">Brasil: DDD com 2 dígitos + 9 dígitos. Ex: 61999999999</small>
                        </label>
                    </div>
                </div>
                <?php echo bce26_render_account_access_panel($participant); ?>
                <div class="bce26-mobile-controls">
                    <button type="button" class="bce26-filter-btn is-active" data-filter="all">🎮 Todos</button>
                    <button type="button" class="bce26-filter-btn" data-filter="open">🔓 Apenas abertos</button>
                </div>

                <table class="bce26-table">
                    <thead><tr><th class="bce26-col-num">#</th><th class="bce26-col-stage">GRUPO</th><th class="bce26-col-match">JOGO</th><th class="bce26-col-date">DIA</th><th class="bce26-col-local">LOCAL</th><th class="bce26-col-pred">PALPITE</th></tr></thead>
                    <tbody>
                    <?php foreach ($matches as $m):
                        $open = bce26_match_is_open_for_prediction($m);
                        $pred = $my_preds[$m->id] ?? null;
                    ?>
                        <tr class="<?php echo $open ? 'bce26-open' : 'bce26-locked'; ?>" data-open="<?php echo $open ? '1' : '0'; ?>">
                            <td data-label="#"><?php echo intval($m->match_number); ?></td>
                            <td class="bce26-stage-cell bce26-mobile-center" data-label="GRUPO">
                                <span class="bce26-stage-main"><?php echo esc_html(($m->grupo ? 'Grupo ' . $m->grupo : ucfirst($m->fase))); ?></span>
                                <span class="bce26-match-status <?php echo $open ? 'is-open' : 'is-locked'; ?>"><?php echo $open ? '🔓 Aberto' : '🔒 Fechado'; ?></span>
                            </td>
                            <td data-label="JOGO"><?php echo bce26_match_name_with_flags($m); ?></td>
                            <td class="bce26-date-cell bce26-mobile-center" data-label="DIA"><?php echo esc_html(date_i18n('d/m/Y', strtotime($m->data_jogo)) . ($m->hora_brasilia ? ' ' . substr($m->hora_brasilia,0,5) : '')); ?></td>
                            <td class="bce26-local-cell bce26-mobile-center" data-label="LOCAL"><span class="bce26-city"><?php echo esc_html(str_replace(['Nova York/Nova Jersey','Cidade do México'], ['New York','CDMX'], $m->cidade)); ?></span><span class="bce26-country"><?php echo esc_html($m->pais); ?></span></td>
                            <td class="bce26-prediction-cell bce26-mobile-center" data-label="PALPITE">
                                <div class="bce26-prediction">
                                    <input class="bce26-score" type="number" min="0" max="30" name="pred[<?php echo intval($m->id); ?>][a]" value="<?php echo esc_attr($pred->palpite_a ?? ''); ?>" <?php disabled(!$open); ?>>
                                    <span class="bce26-score-separator">x</span>
                                    <input class="bce26-score" type="number" min="0" max="30" name="pred[<?php echo intval($m->id); ?>][b]" value="<?php echo esc_attr($pred->palpite_b ?? ''); ?>" <?php disabled(!$open); ?>>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="bce26-save-anchor" class="bce26-submit-bar"><button class="bce26-button" type="submit">💾 Salvar palpites</button></div>
            </form>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

function bce26_points_for_prediction($pred, $match, $rules_by_fase) {
    if ($match->resultado_a === null || $match->resultado_b === null) return 0;
    $rule = $rules_by_fase[$match->fase] ?? ['pontos_exato'=>5,'pontos_resultado'=>2,'pontos_diferenca'=>1];

    $pa = intval($pred->palpite_a); $pb = intval($pred->palpite_b);
    $ra = intval($match->resultado_a); $rb = intval($match->resultado_b);

    if ($pa === $ra && $pb === $rb) return intval($rule['pontos_exato']);

    $pred_result = $pa <=> $pb;
    $real_result = $ra <=> $rb;
    if ($pred_result === $real_result) return intval($rule['pontos_resultado']);

    if (($pa - $pb) === ($ra - $rb)) return intval($rule['pontos_diferenca']);

    return 0;
}

function bce26_get_ranking() {
    global $wpdb;
    $t = bce26_tables();
    $rules_rows = $wpdb->get_results("SELECT * FROM {$t['rules']}", ARRAY_A);
    $rules = [];
    foreach ($rules_rows as $r) $rules[$r['fase']] = $r;

    $participants = $wpdb->get_results("SELECT * FROM {$t['participants']} ORDER BY nome ASC");
    $ranking = [];
    foreach ($participants as $p) {
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT pr.*, m.fase, m.resultado_a, m.resultado_b
            FROM {$t['predictions']} pr
            JOIN {$t['matches']} m ON m.id = pr.match_id
            WHERE pr.participant_id = %d
        ", $p->id));
        $total = 0; $exact = 0;
        foreach ($rows as $r) {
            $pts = bce26_points_for_prediction($r, $r, $rules);
            $total += $pts;
            if ($r->resultado_a !== null && intval($r->palpite_a) === intval($r->resultado_a) && intval($r->palpite_b) === intval($r->resultado_b)) $exact++;
        }
        $ranking[] = ['nome'=>$p->nome, 'pontos'=>$total, 'exatos'=>$exact, 'palpites'=>count($rows)];
    }
    usort($ranking, function($a,$b){
        if ($b['pontos'] !== $a['pontos']) return $b['pontos'] <=> $a['pontos'];
        return $b['exatos'] <=> $a['exatos'];
    });
    return $ranking;
}

function bce26_shortcode_ranking() {
    $ranking = bce26_get_ranking();
    ob_start();
    ?>
    <div class="bce26-wrap">
        <div class="bce26-window">
            <div class="bce26-titlebar">Ranking ao vivo</div>
            <table class="bce26-table bce26-ranking">
                <thead><tr><th>Pos.</th><th>Participante</th><th>Pontos</th><th>Exatos</th><th>Palpites</th></tr></thead>
                <tbody>
                <?php if (!$ranking): ?>
                    <tr><td colspan="5">Nenhum participante ainda.</td></tr>
                <?php endif; ?>
                <?php foreach ($ranking as $i => $r): ?>
                    <tr><td><?php echo $i+1; ?></td><td><?php echo esc_html($r['nome']); ?></td><td><?php echo intval($r['pontos']); ?></td><td><?php echo intval($r['exatos']); ?></td><td><?php echo intval($r['palpites']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function bce26_admin_matches() {
    if (!current_user_can('manage_options')) return;
    global $wpdb; $t = bce26_tables();

    if (isset($_POST['bce26_save_matches']) && check_admin_referer('bce26_save_matches')) {
        $lock = max(0, intval($_POST['lock_minutes'] ?? 5));
        $open_days_before = max(0, intval($_POST['open_days_before'] ?? 7));
        update_option('bce26_lock_minutes', $lock);
        update_option('bce26_open_days_before', $open_days_before);
        foreach (($_POST['match'] ?? []) as $id => $m) {
            $id = absint($id);
            $wpdb->update($t['matches'], [
                'resultado_a' => ($m['resultado_a'] === '' ? null : intval($m['resultado_a'])),
                'resultado_b' => ($m['resultado_b'] === '' ? null : intval($m['resultado_b'])),
                'status' => sanitize_text_field($m['status']),
                'time_a' => sanitize_text_field($m['time_a']),
                'time_b' => sanitize_text_field($m['time_b']),
                'hora_brasilia' => ($m['hora_brasilia'] === '' ? null : sanitize_text_field($m['hora_brasilia'])),
            ], ['id'=>$id]);
        }
        echo '<div class="updated"><p>Jogos atualizados.</p></div>';
    }

    $matches = bce26_get_matches(false);
    ?>
    <div class="wrap bce26-admin">
        <h1>Bolão Copa 2026 — Jogos e Resultados</h1>
        <form method="post">
            <?php wp_nonce_field('bce26_save_matches'); ?>
            <p><label>Bloquear edição de palpites <input type="number" name="lock_minutes" value="<?php echo esc_attr(get_option('bce26_lock_minutes',5)); ?>" style="width:70px"> minutos antes do jogo.</label></p>
            <p><label>Abrir palpites <input type="number" min="0" name="open_days_before" value="<?php echo esc_attr(get_option('bce26_open_days_before',7)); ?>" style="width:70px"> dias antes da data do jogo.</label></p>
            <table class="widefat striped">
                <thead><tr><th>#</th><th>Fase/Grupo</th><th>Times</th><th>Data</th><th>Hora Brasília</th><th>Local</th><th>Resultado</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($matches as $m): ?>
                    <tr>
                        <td data-label="#"><?php echo intval($m->match_number); ?></td>
                        <td><?php echo esc_html($m->grupo ? 'Grupo '.$m->grupo : $m->fase); ?></td>
                        <td>
                            <input name="match[<?php echo $m->id; ?>][time_a]" value="<?php echo esc_attr($m->time_a); ?>"> x
                            <input name="match[<?php echo $m->id; ?>][time_b]" value="<?php echo esc_attr($m->time_b); ?>">
                        </td>
                        <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($m->data_jogo))); ?></td>
                        <td><input name="match[<?php echo $m->id; ?>][hora_brasilia]" value="<?php echo esc_attr(substr((string)$m->hora_brasilia,0,5)); ?>" style="width:80px"></td>
                        <td class="bce26-local-cell bce26-mobile-center" data-label="LOCAL"><span class="bce26-city"><?php echo esc_html(str_replace(['Nova York/Nova Jersey','Cidade do México'], ['New York','CDMX'], $m->cidade)); ?></span><span class="bce26-country"><?php echo esc_html($m->pais); ?></span></td>
                        <td>
                            <input name="match[<?php echo $m->id; ?>][resultado_a]" value="<?php echo esc_attr($m->resultado_a); ?>" style="width:50px"> x
                            <input name="match[<?php echo $m->id; ?>][resultado_b]" value="<?php echo esc_attr($m->resultado_b); ?>" style="width:50px">
                        </td>
                        <td>
                            <select name="match[<?php echo $m->id; ?>][status]">
                                <?php foreach(['open'=>'Aberto','locked'=>'Bloqueado','finished'=>'Encerrado'] as $k=>$label): ?>
                                    <option value="<?php echo esc_attr($k); ?>" <?php selected($m->status,$k); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><button class="button button-primary" name="bce26_save_matches" value="1">Salvar alterações</button></p>
        </form>
    </div>
    <?php
}

function bce26_admin_participants() {
    if (!current_user_can('manage_options')) return;
    global $wpdb; $t = bce26_tables();

    if (isset($_POST['bce26_delete_participant']) && check_admin_referer('bce26_participants')) {
        $pid = absint($_POST['participant_id']);
        $wpdb->delete($t['predictions'], ['participant_id'=>$pid]);
        $wpdb->delete($t['participants'], ['id'=>$pid]);
        echo '<div class="updated"><p>Participante excluído.</p></div>';
    }
    if (isset($_POST['bce26_save_participant']) && check_admin_referer('bce26_participants')) {
        $pid = absint($_POST['participant_id']);
        $telefone_admin = bce26_sanitize_phone_with_ddi('55', wp_unslash($_POST['telefone'] ?? ''));
        $wpdb->update($t['participants'], ['nome'=>sanitize_text_field(wp_unslash($_POST['nome'] ?? '')), 'telefone'=>$telefone_admin], ['id'=>$pid]);
        echo '<div class="updated"><p>Participante atualizado.</p></div>';
    }

    $participants = $wpdb->get_results("SELECT p.*, COUNT(pr.id) total_palpites FROM {$t['participants']} p LEFT JOIN {$t['predictions']} pr ON pr.participant_id=p.id GROUP BY p.id ORDER BY p.nome ASC");
    ?>
    <div class="wrap"><h1>Participantes</h1>
        <table class="widefat striped">
            <thead><tr><th>Nome</th><th>Telefone</th><th>Conta</th><th>Palpites</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach($participants as $p): ?>
                <tr>
                    <form method="post">
                    <?php wp_nonce_field('bce26_participants'); ?>
                    <input type="hidden" name="participant_id" value="<?php echo intval($p->id); ?>">
                    <td><input name="nome" value="<?php echo esc_attr($p->nome); ?>"></td>
                    <td><input name="telefone" value="<?php echo esc_attr(bce26_format_phone($p->telefone)); ?>" placeholder="+55 (61) 99999-9999"></td>
                    <td>
                        <?php if (!empty($p->wp_user_id) && ($u = get_user_by('id', intval($p->wp_user_id)))): ?>
                            ✅ <?php echo esc_html($u->user_email); ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo intval($p->total_palpites); ?></td>
                    <td>
                        <button class="button" name="bce26_save_participant" value="1">Salvar</button>
                        <button class="button button-link-delete" name="bce26_delete_participant" value="1" onclick="return confirm('Excluir participante e palpites?')">Excluir</button>
                    </td>
                    </form>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function bce26_admin_rules() {
    if (!current_user_can('manage_options')) return;
    global $wpdb; $t = bce26_tables();

    if (isset($_POST['bce26_save_rules']) && check_admin_referer('bce26_save_rules')) {
        foreach (($_POST['rule'] ?? []) as $fase => $r) {
            $wpdb->update($t['rules'], [
                'pontos_exato'=>intval($r['exato']),
                'pontos_resultado'=>intval($r['resultado']),
                'pontos_diferenca'=>intval($r['diferenca']),
            ], ['fase'=>sanitize_text_field($fase)]);
        }
        echo '<div class="updated"><p>Regras salvas.</p></div>';
    }

    $rules = $wpdb->get_results("SELECT * FROM {$t['rules']} ORDER BY id ASC");
    ?>
    <div class="wrap"><h1>Regras de Pontuação</h1>
        <form method="post">
            <?php wp_nonce_field('bce26_save_rules'); ?>
            <table class="widefat striped">
                <thead><tr><th>Fase</th><th>Placar exato</th><th>Resultado correto</th><th>Diferença de gols</th></tr></thead>
                <tbody>
                <?php foreach($rules as $r): ?>
                    <tr>
                        <td><strong><?php echo esc_html($r->fase); ?></strong></td>
                        <td><input type="number" name="rule[<?php echo esc_attr($r->fase); ?>][exato]" value="<?php echo intval($r->pontos_exato); ?>"></td>
                        <td><input type="number" name="rule[<?php echo esc_attr($r->fase); ?>][resultado]" value="<?php echo intval($r->pontos_resultado); ?>"></td>
                        <td><input type="number" name="rule[<?php echo esc_attr($r->fase); ?>][diferenca]" value="<?php echo intval($r->pontos_diferenca); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><button class="button button-primary" name="bce26_save_rules" value="1">Salvar regras</button></p>
        </form>
    </div>
    <?php
}





function bce26_admin_texts() {
    if (!current_user_can('manage_options')) return;

    $texts = bce26_get_texts();
    $labels = [
        'top_title' => 'Título superior',
        'top_subtitle' => 'Subtítulo superior',
        'brand_title' => 'Título grande do cabeçalho',
        'brand_copy' => 'Texto pequeno abaixo do título',
        'rules_title' => 'Título da seção de regras',
        'card_1_title' => 'Card 1 - título',
        'card_1_text' => 'Card 1 - texto',
        'card_2_title' => 'Card 2 - título',
        'card_2_text' => 'Card 2 - texto',
        'card_3_title' => 'Card 3 - título',
        'card_3_text' => 'Card 3 - texto',
        'card_4_title' => 'Card 4 - título',
        'card_4_text' => 'Card 4 - texto',
        'score_title' => 'Título da seção de pontuação',
        'score_note' => 'Observação da pontuação',
        'status_left' => 'Rodapé esquerdo',
        'status_center' => 'Rodapé central',
        'status_right' => 'Rodapé direito',
        'signup_title' => 'Cadastro - título',
        'signup_help' => 'Cadastro - instrução',
        'signup_name_label' => 'Cadastro - label nome',
        'signup_telefone_label' => 'Cadastro - label telefone',
    ];

    $changes = [];
    $received_fields = 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bce26_texts_action'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bce26_save_texts')) {
            echo '<div class="notice notice-error"><p>Erro de segurança. Recarregue a página e tente novamente.</p></div>';
        } else {
            $action = sanitize_text_field(wp_unslash($_POST['bce26_texts_action']));

            if ($action === 'reset') {
                foreach ($labels as $key => $label) {
                    delete_option('bce26_text_' . $key);
                    wp_cache_delete('bce26_text_' . $key, 'options');
                }
                delete_option('bce26_texts');
                wp_cache_delete('bce26_texts', 'options');
                $texts = bce26_get_texts();
                echo '<div class="notice notice-success"><p>Textos restaurados para o padrão.</p></div>';
            }

            if ($action === 'save') {
                $old_texts = $texts;
                $new_array = [];

                foreach ($labels as $key => $label) {
                    $field_name = 'bce26_text_' . $key;

                    if (isset($_POST[$field_name])) {
                        $received_fields++;
                        $new_value = sanitize_textarea_field(wp_unslash($_POST[$field_name]));
                    } else {
                        $new_value = $old_texts[$key] ?? '';
                    }

                    $old_value = isset($old_texts[$key]) ? (string) $old_texts[$key] : '';
                    $new_array[$key] = $new_value;

                    // Salva em option individual. Mais resistente a filtros no array.
                    update_option('bce26_text_' . $key, $new_value, false);
                    wp_cache_delete('bce26_text_' . $key, 'options');

                    if ($old_value !== $new_value) {
                        $changes[] = [
                            'label' => $label,
                            'key' => $key,
                            'old' => $old_value,
                            'new' => $new_value,
                        ];
                    }
                }

                // Mantém também o array antigo para compatibilidade.
                update_option('bce26_texts', $new_array, false);
                wp_cache_delete('bce26_texts', 'options');

                $texts = bce26_get_texts();

                echo '<div class="notice notice-success"><p>Textos salvos com sucesso. Campos recebidos: ' . intval($received_fields) . '. Campos alterados: ' . intval(count($changes)) . '.</p></div>';

                if (!empty($changes)) {
                    echo '<div class="notice notice-info"><p><strong>Alterações realizadas:</strong></p>';
                    echo '<table class="widefat striped" style="max-width:1100px;margin:8px 0 12px;"><thead><tr><th>Campo</th><th>Antes</th><th>Depois</th></tr></thead><tbody>';
                    foreach ($changes as $change) {
                        echo '<tr>';
                        echo '<td><strong>' . esc_html($change['label']) . '</strong><br><code>' . esc_html($change['key']) . '</code></td>';
                        echo '<td>' . nl2br(esc_html($change['old'])) . '</td>';
                        echo '<td>' . nl2br(esc_html($change['new'])) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>Nenhuma diferença detectada entre os textos anteriores e os textos enviados. Se você alterou algo e apareceu isso, o navegador/servidor não enviou o campo alterado.</p></div>';
                }
            }
        }
    }
    ?>
    <div class="wrap bce26-admin-texts">
        <h1>Textos e Títulos do Bolão</h1>
        <p>Edite os textos exibidos no cabeçalho, nas regras e no cadastro do jogador.</p>
        <p>Use <code>{lock_minutes}</code> para mostrar automaticamente o tempo de bloqueio dos palpites.</p>

        <div style="background:#0a2a0a;border:3px solid #4aaa4a;padding:16px;margin:16px 0;color:#e8e8e8;max-width:780px;">
            <div style="font-weight:bold;color:#f5c518;margin-bottom:8px;">Preview rápido</div>
            <div style="font-size:18px;"><?php echo esc_html($texts['top_title'] ?? ''); ?></div>
            <div style="color:#7fff00;"><?php echo esc_html($texts['brand_title'] ?? ''); ?></div>
            <div style="color:#b0b0b0;"><?php echo esc_html($texts['signup_help'] ?? ''); ?></div>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('bce26_save_texts'); ?>
            <input type="hidden" name="bce26_texts_action" value="save">

            <table class="form-table" role="presentation"><tbody>
            <?php foreach ($labels as $key => $label): ?>
                <tr>
                    <th scope="row">
                        <label for="bce26_text_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                        <br><code><?php echo esc_html($key); ?></code>
                    </th>
                    <td>
                        <textarea
                            id="bce26_text_<?php echo esc_attr($key); ?>"
                            name="bce26_text_<?php echo esc_attr($key); ?>"
                            rows="<?php echo (strpos($key, '_text') !== false || strpos($key, '_help') !== false || strpos($key, '_note') !== false) ? 3 : 2; ?>"
                            class="large-text"
                        ><?php echo esc_textarea($texts[$key] ?? ''); ?></textarea>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>

            <p><button class="button button-primary" type="submit">Salvar textos</button></p>
        </form>

        <form method="post" action="" onsubmit="return confirm('Restaurar todos os textos para o padrão?');" style="margin-top:18px;">
            <?php wp_nonce_field('bce26_save_texts'); ?>
            <input type="hidden" name="bce26_texts_action" value="reset">
            <button class="button button-secondary" type="submit">Restaurar textos padrão</button>
        </form>
    </div>
    <?php
}


function bce26_admin_product() {
    if (!current_user_can('manage_options')) return;

    $theme_labels = [
        'dark' => 'Fundo escuro',
        'green' => 'Verde principal',
        'yellow' => 'Amarelo destaque',
        'lime' => 'Verde neon',
        'border' => 'Borda',
        'white' => 'Texto claro',
        'gray' => 'Texto secundário',
        'red' => 'Alerta / live',
    ];

    $prize_labels = [
        1 => '1º lugar',
        2 => '2º lugar',
        3 => '3º lugar',
    ];

    $whatsapp_labels = [
        'enabled' => 'WhatsApp - ativar compartilhamento',
        'number' => 'WhatsApp - número',
        'message' => 'WhatsApp - mensagem',
    ];

    $prizes = bce26_get_prizes();
    $theme = bce26_get_theme();
    $whatsapp = bce26_get_whatsapp_config();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bce26_product_action'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bce26_save_product')) {
            echo '<div class="notice notice-error"><p>Erro de segurança. Recarregue a página e tente novamente.</p></div>';
        } else {
            $action = sanitize_text_field(wp_unslash($_POST['bce26_product_action']));
            $changes = [];

            if ($action === 'save') {
                $old_prizes = bce26_get_prizes();
                $old_theme = bce26_get_theme();
                $old_whatsapp = bce26_get_whatsapp_config();

                $new_prizes = [];
                foreach ($prize_labels as $i => $label) {
                    $field = 'bce26_prize_' . $i;
                    $new_value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : ($old_prizes[$i] ?? '');
                    $new_prizes[$i] = $new_value;

                    update_option('bce26_prize_' . $i, $new_value, false);
                    wp_cache_delete('bce26_prize_' . $i, 'options');

                    if ((string)($old_prizes[$i] ?? '') !== (string)$new_value) {
                        $changes[] = [
                            'field' => 'Premiação - ' . $label,
                            'key' => 'bce26_prize_' . $i,
                            'old' => $old_prizes[$i] ?? '',
                            'new' => $new_value,
                        ];
                    }
                }
                update_option('bce26_prizes', $new_prizes, false);
                wp_cache_delete('bce26_prizes', 'options');

                $new_theme = [];
                foreach ($theme_labels as $key => $label) {
                    $field = 'bce26_theme_' . $key;
                    $new_value = isset($_POST[$field]) ? sanitize_hex_color(wp_unslash($_POST[$field])) : ($old_theme[$key] ?? '');

                    if (!$new_value) {
                        $new_value = $old_theme[$key] ?? '#000000';
                    }

                    $new_theme[$key] = $new_value;

                    update_option('bce26_theme_' . $key, $new_value, false);
                    wp_cache_delete('bce26_theme_' . $key, 'options');

                    if ((string)($old_theme[$key] ?? '') !== (string)$new_value) {
                        $changes[] = [
                            'field' => 'Cor - ' . $label,
                            'key' => 'bce26_theme_' . $key,
                            'old' => $old_theme[$key] ?? '',
                            'new' => $new_value,
                        ];
                    }
                }
                update_option('bce26_theme', $new_theme, false);
                wp_cache_delete('bce26_theme', 'options');

                $new_whatsapp = [
                    'enabled' => isset($_POST['bce26_whatsapp_enabled']) ? '1' : '0',
                    'number' => preg_replace('/\D+/', '', wp_unslash($_POST['bce26_whatsapp_number'] ?? '')),
                    'message' => sanitize_textarea_field(wp_unslash($_POST['bce26_whatsapp_message'] ?? '')),
                ];

                foreach ($whatsapp_labels as $key => $label) {
                    update_option('bce26_whatsapp_' . $key, $new_whatsapp[$key], false);
                    wp_cache_delete('bce26_whatsapp_' . $key, 'options');

                    if ((string)($old_whatsapp[$key] ?? '') !== (string)$new_whatsapp[$key]) {
                        $changes[] = [
                            'field' => $label,
                            'key' => 'bce26_whatsapp_' . $key,
                            'old' => $old_whatsapp[$key] ?? '',
                            'new' => $new_whatsapp[$key],
                        ];
                    }
                }
                update_option('bce26_whatsapp', $new_whatsapp, false);
                wp_cache_delete('bce26_whatsapp', 'options');

                // Recarrega depois de salvar usando exatamente as funções de leitura do frontend.
                $prizes = bce26_get_prizes();
                $theme = bce26_get_theme();
                $whatsapp = bce26_get_whatsapp_config();

                echo '<div class="notice notice-success"><p>Configurações salvas com sucesso. Campos alterados: ' . intval(count($changes)) . '.</p></div>';

                if (!empty($changes)) {
                    echo '<div class="notice notice-info"><p><strong>Alterações realizadas:</strong></p>';
                    echo '<table class="widefat striped" style="max-width:1100px;margin:8px 0 12px;"><thead><tr><th>Campo</th><th>Antes</th><th>Depois</th></tr></thead><tbody>';
                    foreach ($changes as $change) {
                        echo '<tr>';
                        echo '<td><strong>' . esc_html($change['field']) . '</strong><br><code>' . esc_html($change['key']) . '</code></td>';
                        echo '<td>' . nl2br(esc_html($change['old'])) . '</td>';
                        echo '<td>' . nl2br(esc_html($change['new'])) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>Nenhuma diferença detectada entre os valores anteriores e os enviados.</p></div>';
                }

                echo '<div class="notice notice-info"><p><strong>Leitura atual após salvar:</strong></p><ul style="list-style:disc;padding-left:20px;">';
                echo '<li>Prêmio 1: ' . esc_html($prizes[1] ?? '') . '</li>';
                echo '<li>Prêmio 2: ' . esc_html($prizes[2] ?? '') . '</li>';
                echo '<li>Prêmio 3: ' . esc_html($prizes[3] ?? '') . '</li>';
                echo '<li>WhatsApp número: ' . esc_html($whatsapp['number'] ?? '') . '</li>';
                echo '<li>WhatsApp mensagem: ' . esc_html($whatsapp['message'] ?? '') . '</li>';
                echo '</ul></div>';
            }

            if ($action === 'reset_theme') {
                foreach ($theme_labels as $key => $label) {
                    delete_option('bce26_theme_' . $key);
                    wp_cache_delete('bce26_theme_' . $key, 'options');
                }

                delete_option('bce26_theme');
                wp_cache_delete('bce26_theme', 'options');

                $theme = bce26_get_theme();

                echo '<div class="notice notice-success"><p>Cores restauradas para o padrão.</p></div>';
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Produto e Marca</h1>
        <p>Configure prêmios, cores e WhatsApp do bolão.</p>

        <form method="post" action="">
            <?php wp_nonce_field('bce26_save_product'); ?>
            <input type="hidden" name="bce26_product_action" value="save">

            <h2>Premiação Top 3</h2>
            <table class="form-table" role="presentation">
                <tbody>
                <?php foreach ($prize_labels as $i => $label): ?>
                    <tr>
                        <th scope="row"><label for="bce26_prize_<?php echo intval($i); ?>"><?php echo esc_html($label); ?></label></th>
                        <td>
                            <input id="bce26_prize_<?php echo intval($i); ?>" type="text" name="bce26_prize_<?php echo intval($i); ?>" value="<?php echo esc_attr($prizes[$i] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Cores do tema</h2>
            <table class="form-table" role="presentation">
                <tbody>
                <?php foreach ($theme_labels as $key => $label): ?>
                    <tr>
                        <th scope="row"><label for="bce26_theme_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td>
                            <input id="bce26_theme_<?php echo esc_attr($key); ?>" type="color" name="bce26_theme_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($theme[$key]); ?>">
                            <code><?php echo esc_html($theme[$key]); ?></code>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>WhatsApp</h2>
            <p>Use número com DDI e DDD, exemplo: <code>5561999999999</code>.</p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Ativar compartilhamento</th>
                        <td><label><input type="checkbox" name="bce26_whatsapp_enabled" value="1" <?php checked($whatsapp['enabled'] ?? '1', '1'); ?>> Mostrar botão de WhatsApp para participantes</label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bce26_whatsapp_number">Número WhatsApp</label></th>
                        <td><input id="bce26_whatsapp_number" type="text" name="bce26_whatsapp_number" value="<?php echo esc_attr($whatsapp['number'] ?? ''); ?>" class="regular-text" placeholder="5561999999999"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bce26_whatsapp_message">Mensagem</label></th>
                        <td>
                            <textarea id="bce26_whatsapp_message" name="bce26_whatsapp_message" rows="3" class="large-text"><?php echo esc_textarea($whatsapp['message'] ?? ''); ?></textarea>
                            <p class="description">Variáveis: <code>{name}</code>, <code>{points}</code>, <code>{position}</code></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p><button class="button button-primary" type="submit">Salvar configurações</button></p>
        </form>

        <form method="post" action="" style="margin-top:12px;">
            <?php wp_nonce_field('bce26_save_product'); ?>
            <input type="hidden" name="bce26_product_action" value="reset_theme">
            <button class="button button-secondary" type="submit">Restaurar cores padrão</button>
        </form>
    </div>
    <?php
}
