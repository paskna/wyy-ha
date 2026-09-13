if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const serviceWorkerUrl = document.querySelector('meta[name="app-sw-url"]')?.content;
        const basePath = document.querySelector('meta[name="app-base-path"]')?.content || '/';
        const scope = basePath === '' ? '/' : `${basePath.replace(/\/$/, '')}/`;

        if (!serviceWorkerUrl) {
            return;
        }

        navigator.serviceWorker.register(serviceWorkerUrl, {scope}).catch(() => {
            // Offline support is a progressive enhancement.
        });
    });
}
