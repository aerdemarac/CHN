'use strict';

const { app, BrowserWindow, Menu, dialog, session, shell } = require('electron');
const config = require('./config.json');

const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];
const EXTERNAL_PROTOCOLS = ['tel:', 'mailto:'];
const ABORTED = -3;

const serverUrl = readServerUrl(process.env.CHN_SERVER_URL || config.serverUrl);
const startUrl = new URL(config.startPage, serverUrl).href;

let mainWindow = null;
let failureDialogOpen = false;

function readServerUrl(value) {
    const url = new URL(value);
    const isLocalHttp = url.protocol === 'http:' && LOCAL_HOSTS.includes(url.hostname);
    if (url.protocol !== 'https:' && !isLocalHttp) {
        throw new Error('The server URL must use https unless it points to this computer.');
    }
    if (!url.pathname.endsWith('/')) {
        url.pathname += '/';
    }
    url.search = '';
    url.hash = '';
    return url;
}

function parseUrl(value) {
    try {
        return new URL(value);
    } catch {
        return null;
    }
}

function isAppUrl(value) {
    const url = parseUrl(value);
    return url !== null && url.origin === serverUrl.origin && url.pathname.startsWith(serverUrl.pathname);
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
        return permission === 'geolocation' && parseUrl(origin)?.origin === serverUrl.origin;
    });
}

async function showLoadFailure(url) {
    if (failureDialogOpen || mainWindow === null) {
        return;
    }
    failureDialogOpen = true;
    const { response } = await dialog.showMessageBox(mainWindow, {
        type: 'error',
        title: app.getName(),
        message: 'The server could not be reached.',
        detail: 'Check that the CHN server is running, then try again.',
        buttons: ['Try again', 'Quit'],
        defaultId: 0,
        cancelId: 1,
        noLink: true
    });
    failureDialogOpen = false;
    if (response === 1) {
        app.quit();
    } else if (mainWindow !== null) {
        mainWindow.loadURL(isAppUrl(url) ? url : startUrl).catch(() => {});
    }
}

function navigate(direction) {
    const history = mainWindow?.webContents.navigationHistory;
    if (direction === 'back' && history?.canGoBack()) {
        history.goBack();
    } else if (direction === 'forward' && history?.canGoForward()) {
        history.goForward();
    }
}

function buildMenu() {
    const view = [
        { label: 'Back', accelerator: 'Alt+Left', click: () => navigate('back') },
        { label: 'Forward', accelerator: 'Alt+Right', click: () => navigate('forward') },
        { role: 'reload' },
        { type: 'separator' },
        { role: 'resetZoom' },
        { role: 'zoomIn' },
        { role: 'zoomOut' },
        { type: 'separator' },
        { role: 'togglefullscreen' }
    ];
    if (!app.isPackaged) {
        view.push({ type: 'separator' }, { role: 'toggleDevTools' });
    }
    Menu.setApplicationMenu(Menu.buildFromTemplate([
        { label: 'File', submenu: [{ role: 'quit' }] },
        { role: 'editMenu' },
        { label: 'View', submenu: view }
    ]));
}

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1366,
        height: 800,
        minWidth: 1024,
        minHeight: 640,
        backgroundColor: '#F4F7FA',
        autoHideMenuBar: true,
        webPreferences: {
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: true,
            webSecurity: true
        }
    });
    mainWindow.on('closed', () => {
        mainWindow = null;
    });
    mainWindow.webContents.on('did-fail-load', (event, code, description, url, isMainFrame) => {
        if (isMainFrame && code !== ABORTED) {
            showLoadFailure(url);
        }
    });
    mainWindow.loadURL(startUrl).catch(() => {});
}

if (app.requestSingleInstanceLock()) {
    app.on('second-instance', () => {
        if (mainWindow === null) {
            return;
        }
        if (mainWindow.isMinimized()) {
            mainWindow.restore();
        }
        mainWindow.focus();
    });
    app.on('web-contents-created', (event, contents) => guardWebContents(contents));
    app.on('window-all-closed', () => app.quit());
    app.whenReady().then(() => {
        guardPermissions();
        buildMenu();
        createWindow();
    });
} else {
    app.quit();
}
