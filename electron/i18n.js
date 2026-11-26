'use strict';

const LANGUAGES = ['en', 'tr', 'ru'];
const DEFAULT_LANGUAGE = 'en';

const dictionaries = Object.fromEntries(
    LANGUAGES.map((lang) => [lang, require(`./lang/${lang}.json`)])
);

function normalizeLanguage(value) {
    return LANGUAGES.includes(value) ? value : DEFAULT_LANGUAGE;
}

function strings(lang) {
    return { ...dictionaries[DEFAULT_LANGUAGE], ...dictionaries[normalizeLanguage(lang)] };
}

function translator(lang) {
    const messages = strings(lang);
    return (key) => messages[key] ?? key;
}

module.exports = { DEFAULT_LANGUAGE, normalizeLanguage, strings, translator };
