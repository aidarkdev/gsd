export default {
    events: {
        'click [data-action="increment-clicks"]': (part) => {
            part.set('clicks', Number(part.state.clicks ?? 0) + 1);
        },
    },

    state: {
        clicks: (part, value) => {
            part.refs.clicks.textContent = String(value);
        },
    },
};
