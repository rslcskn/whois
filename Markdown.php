<?php

class Markdown {
    public static function parse($markdown) {
        $html = $markdown;

        // Başlıkları dönüştür
        $html = preg_replace('/^# (.*?)$/m', '<h2 class="text-2xl font-bold text-gray-900 mb-6">$1</h2>', $html);
        $html = preg_replace('/^### (.*?)$/m', '<h3 class="text-lg font-semibold text-gray-900 mb-2 mt-6">$1</h3>', $html);
        
        // Paragrafları dönüştür
        $html = preg_replace('/^(?!<h[23]>).*$/m', '<p class="text-gray-600 mb-4">$0</p>', $html);
        
        // Boş paragrafları temizle
        $html = str_replace('<p class="text-gray-600 mb-4"></p>', '', $html);
        
        return $html;
    }
    
    public static function parseFile($filePath) {
        if (!file_exists($filePath)) {
            return '';
        }
        
        $markdown = file_get_contents($filePath);
        return self::parse($markdown);
    }
} 