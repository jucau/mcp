import '@testing-library/jest-dom/vitest';

// jsdom lays nothing out, and has no scrolling to offer. Tests of adapters run without any DOM at all.
if (typeof Element !== 'undefined') {
    Element.prototype.scrollIntoView = () => undefined;
}
