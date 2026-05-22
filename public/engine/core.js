const mountedIds = new Set();
const internals = new WeakMap();
const macroState = new Map();

let bakedCache;

export function escape(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function mount(partModule, params) {
    if (!params || typeof params.id !== 'string' || params.id === '') {
        throw new Error('Part params.id is required');
    }

    if (mountedIds.has(params.id)) {
        throw new Error(`Duplicate part id "${params.id}"`);
    }

    const handlers = partModule.handlers ?? {};
    const stateHandlers = handlers.state ?? {};
    const part = {
        id: params.id,
        state: initialState(params),
        refs: {},
        root: null,
        private: {},
        templates: partModule.templates ?? { default: partModule.template },
        set: null,
    };

    internals.set(part, {
        destroyed: false,
        handlers,
        listeners: [],
        mirrorFields: new Set(Object.keys(params.subscribe ?? {})),
        ownedPaths: [],
        subscriptions: [],
        expose: params.expose ?? [],
    });

    part.set = (keyOrValues, value) => setState(part, keyOrValues, value);

    mountedIds.add(part.id);
    registerOwners(part);
    registerSubscriptions(part, stateHandlers, params.subscribe ?? {});
    publishInitialOwners(part);

    part.root = parseTemplate(partModule.template(part.state, part), part.id);
    part.refs = collectRefs(part.root, part.id);

    attachEvents(part);
    insertRoot(part, params);
    handlers.onMount?.(part);

    return part;
}

export function destroy(part) {
    const data = internals.get(part);

    if (!data) {
        return;
    }

    if (data.destroyed) {
        console.warn(`Part "${part.id}" is already destroyed`);
        return;
    }

    data.destroyed = true;

    try {
        data.handlers.onDestroy?.(part);
    } catch (error) {
        console.error(error);
    }

    for (const [type, listener] of data.listeners) {
        part.root?.removeEventListener(type, listener);
    }

    for (const subscription of data.subscriptions) {
        const path = macroState.get(subscription.remotePath);

        if (path) {
            path.subscribers = path.subscribers.filter((entry) => entry.instance !== part);
        }
    }

    for (const pathName of data.ownedPaths) {
        const path = macroState.get(pathName);

        if (!path) {
            continue;
        }

        for (const subscriber of [...path.subscribers]) {
            notifySubscriber(subscriber, undefined, path.value);
        }

        macroState.delete(pathName);
    }

    mountedIds.delete(part.id);
    part.root?.remove();
}

function initialState(params) {
    const baked = readBaked();

    if (Object.prototype.hasOwnProperty.call(baked, params.id)) {
        return baked[params.id];
    }

    return params.microState ?? {};
}

function readBaked() {
    if (bakedCache !== undefined) {
        return bakedCache;
    }

    const node = document.getElementById('__BAKED__');

    if (!node) {
        bakedCache = {};
        return bakedCache;
    }

    bakedCache = JSON.parse(node.textContent || '{}');
    return bakedCache;
}

function setState(part, keyOrValues, value) {
    const data = internals.get(part);

    if (!data || data.destroyed) {
        console.warn(`Cannot set state on destroyed part "${part.id}"`);
        return;
    }

    let values;

    if (typeof keyOrValues === 'string') {
        values = { [keyOrValues]: value };
    } else if (keyOrValues && typeof keyOrValues === 'object') {
        values = keyOrValues;
    } else {
        throw new Error('set expects a key/value pair or an object');
    }

    const changes = [];

    for (const [key, newValue] of Object.entries(values)) {
        if (data.mirrorFields.has(key)) {
            throw new Error(`Cannot set mirror field "${key}" in part "${part.id}"`);
        }

        const oldValue = part.state[key];

        if (oldValue !== newValue) {
            changes.push([key, newValue, oldValue]);
        }
    }

    for (const [key, newValue] of changes) {
        part.state[key] = newValue;
    }

    for (const [key, newValue, oldValue] of changes) {
        publish(part, key, newValue, oldValue);
    }

    for (const [key, newValue, oldValue] of changes) {
        data.handlers.state?.[key]?.(part, newValue, oldValue);
    }
}

function registerOwners(part) {
    const data = internals.get(part);

    for (const field of data.expose) {
        const pathName = `${part.id}.${field}`;

        if (macroState.has(pathName)) {
            throw new Error(`MacroState path "${pathName}" is already owned`);
        }

        macroState.set(pathName, {
            value: part.state[field],
            owner: part,
            subscribers: [],
        });
        data.ownedPaths.push(pathName);
    }
}

function registerSubscriptions(part, stateHandlers, subscriptions) {
    const data = internals.get(part);

    for (const [localName, remotePath] of Object.entries(subscriptions)) {
        if (typeof stateHandlers[localName] !== 'function') {
            console.warn(`Part "${part.id}" subscribes "${localName}" without a state handler`);
            continue;
        }

        const path = macroState.get(remotePath);

        if (!path) {
            console.warn(`Part "${part.id}" subscribes unknown MacroState path "${remotePath}"`);
            continue;
        }

        if (path.owner === part) {
            throw new Error(`Part "${part.id}" cannot subscribe to its own path "${remotePath}"`);
        }

        const subscriber = { instance: part, localName };
        path.subscribers.push(subscriber);
        data.subscriptions.push({ remotePath, localName });
        part.state[localName] = path.value;
    }
}

function publishInitialOwners(part) {
    const data = internals.get(part);

    for (const field of data.expose) {
        publish(part, field, part.state[field]);
    }
}

function publish(part, field, value) {
    const pathName = `${part.id}.${field}`;
    const path = macroState.get(pathName);

    if (!path || path.owner !== part) {
        return;
    }

    const oldPathValue = path.value;
    path.value = value;

    for (const subscriber of [...path.subscribers]) {
        notifySubscriber(subscriber, value, oldPathValue);
    }
}

function notifySubscriber(subscriber, value, oldPathValue) {
    const part = subscriber.instance;
    const data = internals.get(part);

    if (!data || data.destroyed) {
        return;
    }

    const oldValue = part.state[subscriber.localName] ?? oldPathValue;

    if (oldValue === value) {
        return;
    }

    part.state[subscriber.localName] = value;
    data.handlers.state?.[subscriber.localName]?.(part, value, oldValue);
}

function parseTemplate(html, id) {
    if (typeof html !== 'string') {
        throw new Error(`Template for part "${id}" must return a string`);
    }

    const template = document.createElement('template');
    template.innerHTML = html;

    if (template.content.children.length !== 1) {
        throw new Error(
            `Template for part "${id}" must return exactly one root element, got ${template.content.children.length}`
        );
    }

    return template.content.firstElementChild;
}

function collectRefs(root, id) {
    const refs = {};
    const nodes = root.hasAttribute('data-ref')
        ? [root, ...root.querySelectorAll('[data-ref]')]
        : [...root.querySelectorAll('[data-ref]')];

    for (const node of nodes) {
        const name = node.getAttribute('data-ref');

        if (Object.prototype.hasOwnProperty.call(refs, name)) {
            throw new Error(`Duplicate data-ref="${name}" in part "${id}"`);
        }

        refs[name] = node;
    }

    return refs;
}

function attachEvents(part) {
    const data = internals.get(part);
    const events = data.handlers.events ?? {};
    const groups = new Map();

    for (const [key, handler] of Object.entries(events)) {
        const match = key.match(/^(\S+)\s+(.+)$/);

        if (!match) {
            console.warn(`Invalid event key "${key}" in part "${part.id}"`);
            continue;
        }

        const [, type, selector] = match;

        if (!groups.has(type)) {
            groups.set(type, []);
        }

        groups.get(type).push([selector, handler]);
    }

    for (const [type, entries] of groups) {
        const listener = (event) => {
            for (const [selector, handler] of entries) {
                const target = event.target;

                if (!(target instanceof Element)) {
                    return;
                }

                const match = target.closest(selector);

                if (match && part.root.contains(match)) {
                    handler(part, event);
                    break;
                }
            }
        };

        part.root.addEventListener(type, listener);
        data.listeners.push([type, listener]);
    }
}

function insertRoot(part) {
    const anchor = findMountAnchor(part.id);

    if (!anchor) {
        throw new Error(`Missing mount anchor for part "${part.id}"`);
    }

    anchor.replaceWith(part.root);
}

function findMountAnchor(id) {
    for (const node of document.querySelectorAll('[data-mount-id]')) {
        if (node.getAttribute('data-mount-id') === id) {
            return node;
        }
    }

    return null;
}
