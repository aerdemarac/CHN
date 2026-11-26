'use strict';

const path = require('node:path');
const { app, BrowserWindow, Menu, dialog, ipcMain, session, shell } = require('electron');
const { createServerConfig } = require('./server-config');
const { DEFAULT_LANGUAGE, normalizeLanguage, strings, translator } = require('./i18n');
const local = require('./local-protocol');

const APP_ID = 'com.chn.desktop';
const EXTERNAL_PROTOCOLS = ['tel:', 'mailto:'];
const ABORTED = -3;
const LANGUAGE_COOKIE = 'chn_lang';
const SUPPORT_EMAIL = 'cyprushealthcarenetwork@gmail.com';
const ICON = path.join(__dirname, 'assets', process.platform === 'win32' ? 'icon.ico' : 'icon.png');
const PRELOAD = path.join(__dirname, 'preload.js');
const SPLASH_MIN_MS = 600;
const SPLASH_MAX_MS = 20000;
const DIALOG_MIN_HEIGHT = 160;
const DIALOG_MAX_HEIGHT = 900;
const DIALOG_SHOW_FALLBACK_MS = 1500;

let server = null;
let language = DEFAULT_LANGUAGE;
let mainWindow = null;
let mainRevealed = false;
let splashWindow = null;
let splashShownAt = 0;
let settingsWindow = null;
let aboutWindow = null;
let lastFailure = null;

function parseUrl(value) {
    try {
        return new URL(value);
    } catch {
        return null;
    }
}

function isAppUrl(value) {
    const url = parseUrl(value);
    return url !== null && url.origin === server.url.origin && url.pathname.startsWith(server.url.pathname);
}

function openExternal(value) {
    const url = parseUrl(value);
    if (url !== null && EXTERNAL_PROTOCOLS.includes(url.protocol)) {
        shell.openExternal(url.href).catch(() => {});
    }
}

function guardNavigation(event) {
    if (isAppUrl(event.url)) {
        return;
    }
    event.preventDefault();
    if (event.isMainFrame) {
        openExternal(event.url);
    }
}

function guardWebContents(contents) {
    contents.on('will-frame-navigate', guardNavigation);
    contents.on('will-redirect', guardNavigation);
    contents.on('will-attach-webview', (event) => event.preventDefault());
    contents.setWindowOpenHandler(({ url }) => {
        if (isAppUrl(url)) {
            contents.loadURL(url).catch(() => {});
        } else {
            openExternal(url);
        }
        return { action: 'deny' };
    });
}

function guardPermissions() {
    const isAllowed = (permission, url) => permission === 'geolocation' && isAppUrl(url);

    session.defaultSession.setPermissionRequestHandler((contents, permission, callback, details) => {
        callback(isAllowed(permission, details.requestingUrl));
    });
    session.defaultSession.setPermissionCheckHandler((contents, permission, origin) => {
        return permission === 'geolocation' && parseUrl(origin)?.origin === server.url.origin;
    });
}

function secureWebPreferences() {
    return {
        contextIsolation: true,
        nodeIntegration: false,
        sandbox: true,
        webSecurity: true,
        preload: PRELOAD
    };
}

async function readLanguage() {
    try {
        const [cookie] = await session.defaultSession.cookies.get({ url: server.url.href, name: LANGUAGE_COOKIE });
        return normalizeLanguage(cookie?.value);
    } catch {
        return DEFAULT_LANGUAGE;
    }
}

async function refreshLanguage() {
    const next = await readLanguage();
    if (next !== language) {
        language = next;
        buildMenu();
    }
}

function watchLanguage() {
    session.defaultSession.cookies.on('changed', (event, cookie) => {
        if (cookie.name === LANGUAGE_COOKIE) {
            refreshLanguage();
        }
    });
}

function navigate(direction) {
    const history = mainWindow?.webContents.navigationHistory;
    if (direction === 'back' && history?.canGoBack()) {
        history.goBack();
    } else if (direction === 'forward' && history?.canGoForward()) {
        history.goForward();
    }
}

function reloadMain() {
    if (mainWindow === null) {
        return;
    }
    if (local.isLocalUrl(mainWindow.webContents.getURL())) {
        retry();
    } else {
        mainWindow.webContents.reload();
    }
}

function retry() {
    const url = lastFailure?.url ?? server.startUrl;
    lastFailure = null;
    mainWindow?.loadURL(url).catch(() => {});
}

function pruneLocalHistory(contents) {
    const history = contents.navigationHistory;
    const active = history.getActiveIndex();
    for (let index = history.length() - 1; index >= 0; index -= 1) {
        if (index !== active && local.isLocalUrl(history.getEntryAtIndex(index)?.url)) {
            history.removeEntryAtIndex(index);
        }
    }
}

function buildMenu() {
    const t = translator(language);
    const view = [
        { label: t('menu.back'), accelerator: 'Alt+Left', click: () => navigate('back') },
        { label: t('menu.forward'), accelerator: 'Alt+Right', click: () => navigate('forward') },
        { label: t('menu.reload'), accelerator: 'CmdOrCtrl+R', click: reloadMain },
        { type: 'separator' },
        { role: 'resetZoom', label: t('menu.reset_zoom') },
        { role: 'zoomIn', label: t('menu.zoom_in') },
        { role: 'zoomOut', label: t('menu.zoom_out') },
        { type: 'separator' },
        { role: 'togglefullscreen', label: t('menu.fullscreen') }
    ];
    if (!app.isPackaged) {
        view.push({ type: 'separator' }, { role: 'toggleDevTools', label: t('menu.devtools') });
    }
    Menu.setApplicationMenu(Menu.buildFromTemplate([
        {
            label: t('menu.file'),
            submenu: [
                { label: t('menu.server_settings'), accelerator: 'CmdOrCtrl+,', click: openSettings },
                { type: 'separator' },
                { role: 'quit', label: t('menu.quit') }
            ]
        },
        {
            label: t('menu.edit'),
            submenu: [
                { role: 'undo', label: t('menu.undo') },
                { role: 'redo', label: t('menu.redo') },
                { type: 'separator' },
                { role: 'cut', label: t('menu.cut') },
                { role: 'copy', label: t('menu.copy') },
                { role: 'paste', label: t('menu.paste') },
                { role: 'selectAll', label: t('menu.select_all') }
            ]
        },
        { label: t('menu.view'), submenu: view },
        {
            label: t('menu.help'),
            submenu: [{ label: t('menu.about'), click: openAbout }]
        }
    ]));
}

function revealMainWindow() {
    if (mainRevealed || mainWindow === null) {
        return;
    }
    mainRevealed = true;
    const elapsed = Date.now() - splashShownAt;
    const wait = splashShownAt > 0 ? Math.max(0, SPLASH_MIN_MS - elapsed) : 0;
    setTimeout(() => {
        mainWindow?.show();
        splashWindow?.destroy();
    }, wait);
}

function createSplashWindow() {
    splashWindow = new BrowserWindow({
        width: 440,
        height: 300,
        frame: false,
        resizable: false,
        minimizable: false,
        maximizable: false,
        fullscreenable: false,
        center: true,
        show: false,
        backgroundColor: '#FFFFFF',
        icon: ICON,
        title: app.getName(),
        webPreferences: secureWebPreferences()
    });
    splashWindow.once('ready-to-show', () => {
        if (!mainRevealed) {
            splashShownAt = Date.now();
            splashWindow?.show();
        }
    });
    splashWindow.on('closed', () => {
        splashWindow = null;
    });
    splashWindow.loadURL(local.pageUrl('splash')).catch(() => {});
}

function createMainWindow() {
    mainWindow = new BrowserWindow({
        width: 1366,
        height: 800,
        minWidth: 1024,
        minHeight: 640,
        show: false,
        backgroundColor: '#F4F7FA',
        autoHideMenuBar: true,
        icon: ICON,
        title: app.getName(),
        webPreferences: secureWebPreferences()
    });
    mainWindow.on('closed', () => {
        mainWindow = null;
    });
    mainWindow.webContents.on('did-fail-load', (event, code, description, url, isMainFrame) => {
        if (!isMainFrame || code === ABORTED || local.isLocalUrl(url)) {
            return;
        }
        lastFailure = { url: isAppUrl(url) ? url : server.startUrl, code, description };
        mainWindow?.loadURL(local.pageUrl('offline')).catch(() => {});
    });
    mainWindow.webContents.on('did-finish-load', revealMainWindow);
    mainWindow.webContents.on('did-navigate', () => {
        if (mainWindow !== null) {
            pruneLocalHistory(mainWindow.webContents);
        }
    });
    setTimeout(revealMainWindow, SPLASH_MAX_MS);
    mainWindow.loadURL(server.startUrl).catch(() => {});
}

function createDialogWindow(page, width) {
    const parent = mainRevealed && mainWindow !== null ? mainWindow : undefined;
    const win = new BrowserWindow({
        parent,
        modal: parent !== undefined,
        width,
        height: 400,
        useContentSize: true,
        resizable: false,
        minimizable: false,
        maximizable: false,
        fullscreenable: false,
        show: false,
        backgroundColor: '#F4F7FA',
        icon: ICON,
        title: app.getName(),
        webPreferences: secureWebPreferences()
    });
    win.setMenu(null);
    setTimeout(() => {
        if (!win.isDestroyed() && !win.isVisible()) {
            win.show();
        }
    }, DIALOG_SHOW_FALLBACK_MS);
    win.loadURL(local.pageUrl(page)).catch(() => {});
    return win;
}

function openSettings() {
    if (settingsWindow !== null) {
        settingsWindow.focus();
        return;
    }
    settingsWindow = createDialogWindow('settings', 560);
    settingsWindow.on('closed', () => {
        settingsWindow = null;
    });
}

function openAbout() {
    if (aboutWindow !== null) {
        aboutWindow.focus();
        return;
    }
    aboutWindow = createDialogWindow('about', 500);
    aboutWindow.on('closed', () => {
        aboutWindow = null;
    });
}

function focusExistingWindow() {
    const target = mainRevealed ? mainWindow : splashWindow;
    if (target === null || target === undefined) {
        return;
    }
    if (target.isMinimized()) {
        target.restore();
    }
    target.focus();
}

function registerIpc() {
    const handle = (channel, listener) => {
        ipcMain.handle(channel, (event, ...args) => {
            if (!local.isLocalUrl(event.senderFrame?.url)) {
                throw new Error('Forbidden');
            }
            return listener(event, ...args);
        });
    };
    const isMainSender = (event) => mainWindow !== null && event.sender === mainWindow.webContents;
    const isDialogSender = (event) => {
        const win = BrowserWindow.fromWebContents(event.sender);
        return win !== null && (win === settingsWindow || win === aboutWindow);
    };

    handle('chn:context', async () => {
        language = await readLanguage();
        return {
            lang: language,
            strings: strings(language),
            productName: app.getName(),
            version: app.getVersion(),
            electronVersion: process.versions.electron,
            chromeVersion: process.versions.chrome,
            serverUrl: server.url.href,
            defaultServerUrl: server.defaultUrl.href,
            serverLocked: server.locked,
            supportEmail: SUPPORT_EMAIL,
            failure: lastFailure === null ? null : { code: lastFailure.code, description: lastFailure.description }
        };
    });
    handle('chn:retry', (event) => {
        if (isMainSender(event)) {
            retry();
        }
    });
    handle('chn:quit', () => app.quit());
    handle('chn:open-settings', () => openSettings());
    handle('chn:save-server-url', async (event, value) => {
        if (BrowserWindow.fromWebContents(event.sender) !== settingsWindow) {
            return { ok: false, error: 'invalid' };
        }
        try {
            server.save(value);
        } catch (error) {
            return { ok: false, error: error.code ?? 'invalid' };
        }
        lastFailure = null;
        settingsWindow?.close();
        mainWindow?.loadURL(server.startUrl).catch(() => {});
        await refreshLanguage();
        return { ok: true };
    });
    handle('chn:close', (event) => {
        if (isDialogSender(event)) {
            BrowserWindow.fromWebContents(event.sender)?.close();
        }
    });
    handle('chn:fit', (event, height) => {
        if (!isDialogSender(event) || !Number.isFinite(height)) {
            return;
        }
        const win = BrowserWindow.fromWebContents(event.sender);
        const [width] = win.getContentSize();
        const fitted = Math.min(Math.max(Math.ceil(height), DIALOG_MIN_HEIGHT), DIALOG_MAX_HEIGHT);
        win.setContentSize(width, fitted);
        if (!win.isVisible()) {
            win.center();
            win.show();
        }
    });
}

function startApp() {
    try {
        server = createServerConfig(app.getPath('userData'));
    } catch (error) {
        dialog.showErrorBox(app.getName(), `The server URL is not valid (${error.code ?? error.message}). Check config.json or CHN_SERVER_URL.`);
        app.quit();
        return;
    }
    local.handleRequests();
    guardPermissions();
    registerIpc();
    watchLanguage();
    buildMenu();
    createSplashWindow();
    createMainWindow();
    refreshLanguage();
}

local.registerScheme();

if (app.requestSingleInstanceLock()) {
    if (process.platform === 'win32') {
        app.setAppUserModelId(APP_ID);
    }
    app.on('second-instance', focusExistingWindow);
    app.on('web-contents-created', (event, contents) => guardWebContents(contents));
    app.on('window-all-closed', () => app.quit());
    app.whenReady().then(startApp);
} else {
    app.quit();
}
