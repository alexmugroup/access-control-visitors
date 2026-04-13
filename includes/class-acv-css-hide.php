<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс для удаления из контента элементов с указанными CSS-классами
 * для неавторизованных посетителей.
 */
class ACV_CSS_Hide {

    /**
     * Конструктор: регистрирует хуки.
     */
    public function __construct() {
        // Удаляем элементы из контента для гостей (приоритет 9999 — после всех фильтров)
        add_filter('the_content', array($this, 'filter_content'), 9999);
    }

    /**
     * Фильтр контента: удаляет элементы с классами из опции.
     *
     * @param string $content HTML контента.
     * @return string Обработанный HTML.
     */
    public function filter_content($content) {
        // Только для неавторизованных
        if (is_user_logged_in()) {
            return $content;
        }

        $exclude_classes = get_option('acv_exclude_classes', '');
        if (empty($exclude_classes)) {
            return $content;
        }

        $classes_array = array_map('trim', explode(',', $exclude_classes));
        $classes_array = array_filter($classes_array);
        if (empty($classes_array)) {
            return $content;
        }

        return $this->remove_elements_by_classes($content, $classes_array);
    }

    /**
     * Удаляет из HTML элементы, содержащие указанные CSS-классы.
     *
     * @param string $html   Исходный HTML.
     * @param array  $classes Массив классов для удаления.
     * @return string Обработанный HTML.
     */
    private function remove_elements_by_classes($html, $classes) {
        if (empty($classes) || empty($html)) {
            return $html;
        }

        // Создаём DOMDocument
        $dom = new DOMDocument();
        // Подавляем ошибки парсинга невалидного HTML
        libxml_use_internal_errors(true);
        // Загружаем HTML как фрагмент, обёрнутый в div, чтобы избежать добавления <html>/<body>
        $dom->loadHTML('<div>' . mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        // Формируем корректное XPath-условие: элемент должен содержать хотя бы один из указанных классов
        $conditions = [];
        foreach ($classes as $class) {
            $class_esc = str_replace("'", '"', $class);
            $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' $class_esc ')";
        }
        $query = "//*[" . implode(' or ', $conditions) . "]";
        $elements = $xpath->query($query);

        foreach ($elements as $element) {
            if ($element->parentNode) {
                $element->parentNode->removeChild($element);
            }
        }

        // Извлекаем содержимое обратно из обёртки
        $body = $dom->getElementsByTagName('div')->item(0);
        $clean_html = '';
        if ($body) {
            foreach ($body->childNodes as $child) {
                $clean_html .= $dom->saveHTML($child);
            }
        }

        return $clean_html;
    }
}