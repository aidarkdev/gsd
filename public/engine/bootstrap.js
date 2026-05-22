import { mount } from './core.js';

const manifest = readJson('__MOUNTS__', { instances: [] });

for (const params of manifest.instances ?? []) {
    if (!params.id || !params.part) {
        console.warn('Skipping invalid mount params', params);
        continue;
    }

    const partModule = await import(params.part);
    mount(partModule.default, params);
}

function readJson(id, fallback) {
    const node = document.getElementById(id);

    if (!node) {
        return fallback;
    }

    return JSON.parse(node.textContent || JSON.stringify(fallback));
}
