<?php
// ParlReg — Translation Helper

class Translator {
    private static array $strings = [];
    private static string $lang   = 'en';

    public static function setLang(string $lang): void {
        self::$lang = in_array($lang, ['en', 'fr'], true) ? $lang : 'en';
        $file = LANG_PATH . '/' . self::$lang . '.php';
        self::$strings = file_exists($file) ? require $file : [];
    }

    public static function getLang(): string {
        return self::$lang;
    }

    /** Translate a key with optional sprintf placeholders */
    public static function t(string $key, mixed ...$args): string {
        $str = self::$strings[$key] ?? $key;
        return $args ? sprintf($str, ...$args) : $str;
    }
}

// Global shorthand
function t(string $key, mixed ...$args): string {
    return Translator::t($key, ...$args);
}
