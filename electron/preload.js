'use strict';

const { contextBridge, ipcRenderer } = require('electron');

if (window.location.protocol === 'chn-desktop:') {
    contextBridge.exposeInMainWorld('chnDesktop', {
        context: () => ipcRenderer.invoke('chn:context'),
        retry: () => ipcRenderer.invoke('chn:retry'),
        quit: () => ipcRenderer.invoke('chn:quit'),
        openSettings: () => ipcRenderer.invoke('chn:open-settings'),
        saveServerUrl: (value) => ipcRenderer.invoke('chn:save-server-url', String(value)),
        close: () => ipcRenderer.invoke('chn:close'),
        fitContent: (height) => ipcRenderer.invoke('chn:fit', Number(height))
    });
}
