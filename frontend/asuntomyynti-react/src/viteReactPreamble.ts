// Vite React SWC requires this flag to be set before transformed React modules execute.
const viteWindow = window as any;
viteWindow.$RefreshReg$ = () => {};
viteWindow.$RefreshSig$ = () => (type: unknown) => type;
viteWindow.__vite_plugin_react_preamble_installed__ = true;
