<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс фронтенда плагина
 * Управляет ограничением контента и комментариев для неавторизованных посетителей
 */
class ACV_Frontend {
    
    /**
     * Конструктор - регистрация хуков WordPress
     */
    public function __construct() {
        add_filter('the_content', array($this, 'restrict_content'), 999);
        add_filter('comments_open', array($this, 'disable_comments'), 999, 2);
        add_filter('comments_array', array($this, 'hide_comments'), 999, 2);
        add_action('wp_footer', array($this, 'remove_comment_form'));
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
        
        preg_match_all('/(<[^>]+>|[^<]+)/u', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $token = $match[0];
            
            if (preg_match('/^<(\w+)[^>]*>/u', $token, $tag_match)) {
                $tag_name = $tag_match[1];
                $result .= $token;
                
                $void_tags = array('br', 'hr', 'img', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'param', 'source', 'track', 'wbr');
                if (!in_array(strtolower($tag_name), $void_tags) && !preg_match('/\/>/u', $token)) {
                    $tag_stack[] = $tag_name;
                }
            }
            elseif (preg_match('/^<\/(\w+)>/u', $token, $tag_match)) {
                $result .= $token;
                array_pop($tag_stack);
            }
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
        
        while (!empty($tag_stack)) {
            $tag = array_pop($tag_stack);
            $result .= '</' . $tag . '>';
        }
        
        if ($char_count >= $max_chars) {
            $result .= '...';
        }
        
        return $result;
    }
    
    /**
     * Ограничение контента для неавторизованных посетителей
     * 
     * @param string $content Исходный контент поста из WordPress
     * @return string Изменённый контент с ограничением доступа
     */
    public function restrict_content($content) {
        if (is_user_logged_in()) {
            return $content;
        }
        
        $h2_pattern = '/<h2[^>]*>.*?<\/h2>/i';
        
        if (preg_match($h2_pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $first_h2_position = $matches[0][1];
            $content_before_h2 = substr($content, 0, $first_h2_position);
            $content = $content_before_h2 . $this->get_access_message();
        } else {
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
     */
    public function remove_comment_form() {
        if (is_user_logged_in()) {
            return;
        }
        
        remove_action('comment_form', 'comment_form');
    }
}