<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс админ-панели плагина
 * Управляет настройками плагина в WordPress админке
 */
class ACV_Admin {
    
    /**
     * Конструктор - регистрация хуков WordPress
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Добавление пункта меню в админ-панели
     */
    public function add_admin_menu() {
        add_menu_page(
            'Настройки доступа для гостей',
            'Настройки доступа для гостей',
            'manage_options',
            'acv-access-control',
            array($this, 'render_settings_page'),
            'dashicons-lock',
            8,
        );
    }
    
    /**
     * Регистрация настроек плагина
     */
    public function register_settings() {
        register_setting('acv_settings_group', 'acv_login_url');
        register_setting('acv_settings_group', 'acv_register_url');
        register_setting('acv_settings_group', 'acv_char_limit');
        
        add_settings_section(
            'acv_settings_section',
            'Основные настройки',
            array($this, 'settings_section_callback'),
            'acv-access-control'
        );
        
        add_settings_field(
            'acv_login_url',
            'Страница входа',
            array($this, 'render_login_url_field'),
            'acv-access-control',
            'acv_settings_section'
        );
        
        add_settings_field(
            'acv_register_url',
            'Страница регистрации',
            array($this, 'render_register_url_field'),
            'acv-access-control',
            'acv_settings_section'
        );
        
        add_settings_field(
            'acv_char_limit',
            'Лимит символов',
            array($this, 'render_char_limit_field'),
            'acv-access-control',
            'acv_settings_section'
        );
    }
    
    /**
     * Описание секции настроек
     */
    public function settings_section_callback() {
        echo '<p>Настройте параметры ограничения доступа для неавторизованных посетителей.</p>';
    }
    
    /**
     * Поле ввода URL страницы входа
     */
    public function render_login_url_field() {
        $value = get_option('acv_login_url', wp_login_url());
        echo '<input type="url" name="acv_login_url" value="' . esc_url($value) . '" class="regular-text" placeholder="https://site.ru/login">';
        echo '<p class="description">Оставьте пустым для использования стандартной страницы входа WordPress.</p>';
    }
    
    /**
     * Поле ввода URL страницы регистрации
     */
    public function render_register_url_field() {
        $value = get_option('acv_register_url', wp_registration_url());
        echo '<input type="url" name="acv_register_url" value="' . esc_url($value) . '" class="regular-text" placeholder="https://site.ru/register">';
        echo '<p class="description">Оставьте пустым для использования стандартной страницы регистрации WordPress.</p>';
    }
    
    /**
     * Поле ввода лимита символов
     */
    public function render_char_limit_field() {
        $value = get_option('acv_char_limit', 500);
        echo '<input type="number" name="acv_char_limit" value="' . esc_attr($value) . '" class="small-text" min="100" max="5000">';
        echo '<p class="description">Количество символов для отображения, если на странице нет тега H2.</p>';
    }
    
    /**
     * Рендер страницы настроек в админ-панели
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Настройки доступа к материалам</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('acv_settings_group');
                do_settings_sections('acv-access-control');
                submit_button('Сохранить настройки');
                ?>
            </form>
        </div>
        <?php
    }
}