'use strict';

const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { net, protocol } = require('electron');

const SCHEME = 'chn-desktop';
const HOST = 'app';
const PUBLIC_FOLDERS = ['pages', 'assets'];

function registerScheme() {
    protocol.registerSchemesAsPrivileged([
        { scheme: SCHEME, privileges: { standard: true, secure: true } }
    ]);
}

function parse(value) {
    try {
        return new URL(value);
    } catch {
        return null;
    }
}

function isLocalUrl(value) {
    const url = typeof value === 'string' ? parse(value) : null;
    return url !== null && url.protocol === `${SCHEME}:` && url.host === HOST;
}

function pageUrl(name) {
    return `${SCHEME}://${HOST}/pages/${name}.html`;
}

function resolveFile(requestUrl) {
    if (!isLocalUrl(requestUrl)) {
        return null;
    }
    let pathname;
    try {
        pathname = decodeURIComponent(new URL(requestUrl).pathname);
    } catch {
        return null;
    }
    const file = path.resolve(__dirname, `.${pathname}`);
    const relative = path.relative(__dirname, file);
    const [folder] = relative.split(path.sep);
    if (relative.startsWith('..') || path.isAbsolute(relative) || !PUBLIC_FOLDERS.includes(folder)) {
        return null;
    }
    return file;
}

function notFound() {
    return new Response('Not found', { status: 404, headers: { 'content-type': 'text/plain' } });
}

function handleRequests() {
    protocol.handle(SCHEME, async (request) => {
        const file = request.method === 'GET' ? resolveFile(request.url) : null;
        if (file === null) {
            return notFound();
        }
        try {
            return await net.fetch(pathToFileURL(file).href);
        } catch {
            return notFound();
        }
    });
}

module.exports = { registerScheme, handleRequests, isLocalUrl, pageUrl };
