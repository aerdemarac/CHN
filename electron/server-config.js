'use strict';

const fs = require('node:fs');
const path = require('node:path');
const config = require('./config.json');

const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];
const SETTINGS_FILE = 'settings.json';

class ServerUrlError extends Error {
    constructor(code) {
        super(`Invalid server URL: ${code}`);
        this.code = code;
    }
}

function normalizeServerUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') {
        throw new ServerUrlError('required');
    }
    let url;
    try {
        url = new URL(value.trim());
    } catch {
        throw new ServerUrlError('invalid');
    }
    if (url.username !== '' || url.password !== '') {
        throw new ServerUrlError('invalid');
    }
    const isLocalHttp = url.protocol === 'http:' && LOCAL_HOSTS.includes(url.hostname);
    if (url.protocol !== 'https:' && !isLocalHttp) {
        throw new ServerUrlError('protocol');
    }
    if (!url.pathname.endsWith('/')) {
        url.pathname += '/';
    }
    url.search = '';
    url.hash = '';
    return url;
}

function createServerConfig(userDataDir) {
    const settingsPath = path.join(userDataDir, SETTINGS_FILE);
    const envValue = process.env.CHN_SERVER_URL;
    const locked = typeof envValue === 'string' && envValue !== '';
    const defaultUrl = normalizeServerUrl(config.serverUrl);

    function readStoredUrl() {
        try {
            const stored = JSON.parse(fs.readFileSync(settingsPath, 'utf8'));
            return normalizeServerUrl(stored.serverUrl);
        } catch {
            return null;
        }
    }

    let current = locked ? normalizeServerUrl(envValue) : (readStoredUrl() ?? defaultUrl);

    return {
        locked,
        defaultUrl,
        get url() {
            return current;
        },
        get startUrl() {
            return new URL(config.startPage, current).href;
        },
        save(value) {
            if (locked) {
                throw new ServerUrlError('locked');
            }
            const url = normalizeServerUrl(value);
            if (url.href === defaultUrl.href) {
                fs.rmSync(settingsPath, { force: true });
            } else {
                fs.mkdirSync(userDataDir, { recursive: true });
                fs.writeFileSync(settingsPath, `${JSON.stringify({ serverUrl: url.href }, null, 4)}\n`);
            }
            current = url;
            return url;
        }
    };
}

module.exports = { createServerConfig, normalizeServerUrl, ServerUrlError };
