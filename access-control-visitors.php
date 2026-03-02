<?php
/**
 * Plugin Name: Access Control for Visitors
 * Plugin URI: https://efeto.ru
 * Description: Плагин для управления доступом к материалам для неавторизованных посетителей сайта на ВордПресс
 * Version: 0.2
 * Author: efeto.ru
 * Author URI: https://efeto.ru
 * License: GPL v2 or later
 * Text Domain: access-control-visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Основной класс плагина
 * Управляет ограничением контента и комментариев для неавторизованных посетителей
 */
class ACV_AccessControl {
    
    /**
     * Конструктор - регистрация хуков WordPress
     * 
     * Подключает хуки для:
     * - Админ-панели (меню, настройки)
     * - Фронтенда (контент, комментарии)
     */
    public function __construct() {
        // Админ-панель
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Фронтенд
        add_filter('the_content', array($this, 'restrict_content'), 999);
        add_filter('comments_open', array($this, 'disable_comments'), 999, 2);
        add_filter('comments_array', array($this, 'hide_comments'), 999, 2);
        add_action('wp_footer', array($this, 'remove_comment_form'));
    }
    
    /**
     * Добавление пункта меню в админ-панели
     * 
     * Создаёт раздел "Настройки доступа" в меню настроек WordPress
     */
    public function add_admin_menu() {
        add_options_page(
            'Настройки доступа',
            'Настройки доступа',
            'manage_options',
            'acv-access-control',
            array($this, 'render_settings_page'),
            'dashicons-lock',
            100
        );
    }
    
    /**
     * Регистрация настроек плагина
     * 
     * Регистрирует 3 поля в WordPress options:
     * - acv_login_url: URL страницы входа
     * - acv_register_url: URL страницы регистрации
     * - acv_char_limit: лимит символов для обрезки
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
    
    /**
     * Получение URL страницы входа
     * 
     * @return string URL для входа
     */
    private function get_login_url() {
        $custom_url = get_option('acv_login_url');
        return !empty($custom_url) ? $custom_url : wp_login_url();
    }
    
    /**
     * Получение URL страницы регистрации
     * 
     * @return string URL для регистрации
     */
    private function get_register_url() {
        $custom_url = get_option('acv_register_url');
        return !empty($custom_url) ? $custom_url : wp_registration_url();
    }
    
    /**
     * Получение лимита символов
     * 
     * @return int Количество символов
     */
    private function get_char_limit() {
        return intval(get_option('acv_char_limit', 500));
    }
    
    /**
     * Обрезает HTML-контент с сохранением структуры тегов
     * 
     * @param string $html Исходный HTML
     * @param int $max_chars Максимум символов текста
     * @return string Обрезанный HTML с сохранёнными тегами
     */
    private function truncate_html($html, $max_chars) {
        $char_count = 0;
        $result = '';
        $tag_stack = array();
        
        // Разбиваем на теги и текст
        preg_match_all('/(<[^>]+>|[^<]+)/u', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $token = $match[0];
            
            // Если это открывающий тег
            if (preg_match('/^<(\w+)[^>]*>/u', $token, $tag_match)) {
                $tag_name = $tag_match[1];
                $result .= $token;
                
                // Добавляем в стек если не void-тег
                $void_tags = array('br', 'hr', 'img', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'param', 'source', 'track', 'wbr');
                if (!in_array(strtolower($tag_name), $void_tags) && !preg_match('/\/>/u', $token)) {
                    $tag_stack[] = $tag_name;
                }
            }
            // Если это закрывающий тег
            elseif (preg_match('/^<\/(\w+)>/u', $token, $tag_match)) {
                $result .= $token;
                array_pop($tag_stack);
            }
            // Если это текст
            else {
                $text = $token;
                $text_len = mb_strlen($text);
                
                if ($char_count + $text_len <= $max_chars) {
                    $result .= $text;
                    $char_count += $text_len;
                } else {
                    $remaining = $max_chars - $char_count;
                    $result .= mb_substr($text, 0, $remaining);
                    $char_count = $max_chars;
                    break;
                }
            }
        }
        
        // Закрываем все открытые теги
        while (!empty($tag_stack)) {
            $tag = array_pop($tag_stack);
            $result .= '</' . $tag . '>';
        }
        
        // Добавляем многоточие
        if ($char_count >= $max_chars) {
            $result .= '...';
        }
        
        return $result;
    }
    
    /**
     * Ограничение контента для неавторизованных посетителей
     * 
     * Логика работы:
     * 1. Авторизованные пользователи получают полный контент без изменений
     * 2. Если в контенте есть тег H2 - показываем всё до первого H2
     * 3. Если H2 нет - показываем первые N символов текста с сохранением HTML-форматирования
     * 4. В конце добавляем сообщение с ссылками на вход и регистрацию
     * 
     * @param string $content Исходный контент поста из WordPress
     * @return string Изменённый контент с ограничением доступа
     */
    public function restrict_content($content) {
        // Авторизованные пользователи видят полный контент
        if (is_user_logged_in()) {
            return $content;
        }
        
        // Поиск первого тега H2 в контенте
        $h2_pattern = '/<h2[^>]*>.*?<\/h2>/i';
        
        if (preg_match($h2_pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            // H2 найден - обрезаем контент до позиции первого H2
            $first_h2_position = $matches[0][1];
            $content_before_h2 = substr($content, 0, $first_h2_position);
            $content = $content_before_h2 . $this->get_access_message();
        } else {
            // H2 не найден - берём первые N символов с сохранением HTML-форматирования
            $char_limit = $this->get_char_limit();
            $content = $this->truncate_html($content, $char_limit) . $this->get_access_message();
        }
        
        return $content;
    }
    
    /**
     * Формирование HTML-сообщения с ссылками на вход и регистрацию
     * 
     * @return string HTML-блок с сообщением и двумя ссылками
     */
    private function get_access_message() {
        $login_url = $this->get_login_url();
        $register_url = $this->get_register_url();
        
        $login_link = sprintf(
            '<a href="%s">войти в систему</a>',
            esc_url($login_url)
        );
        
        $register_link = sprintf(
            '<a href="%s">зарегистрироваться</a>',
            esc_url($register_url)
        );
        
        $message = sprintf(
            '<div class="acv-access-message"><p>Для доступа к полной версии материала %s. Если ещё нет аккаунта %s.</p></div>',
            $login_link,
            $register_link
        );
        
        return $message;
    }
    
    /**
     * Отключение возможности добавления комментариев для гостей
     * 
     * @param bool $open Текущий статус возможности комментирования
     * @param int $post_id ID поста
     * @return bool false для гостей, исходное значение для авторизованных
     */
    public function disable_comments($open, $post_id) {
        if (is_user_logged_in()) {
            return $open;
        }
        return false;
    }
    
    /**
     * Скрытие массива существующих комментариев от гостей
     * 
     * @param array $comments Массив комментариев поста
     * @param int $post_id ID поста
     * @return array Пустой массив для гостей, исходный для авторизованных
     */
    public function hide_comments($comments, $post_id) {
        if (is_user_logged_in()) {
            return $comments;
        }
        return array();
    }
    
    /**
     * Удаление формы комментариев из HTML-вывода
     * 
     * Вызывается на хуке wp_footer, когда все хуки уже зарегистрированы
     * remove_action удаляет стандартную форму комментариев WordPress
     */
    public function remove_comment_form() {
        if (is_user_logged_in()) {
            return;
        }
        
        remove_action('comment_form', 'comment_form');
    }
}

// Инициализация плагина - создание экземпляра класса
new ACV_AccessControl();