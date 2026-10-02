/* OmniGoCRM React runtime loader for WordPress shared hosting. */
(function () {
  if (window.__omnigocrmReactLoaded) return;
  window.__omnigocrmReactLoaded = true;

  function addScript(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  function boot() {
    var root = document.getElementById('omnigocrm-app');
    if (!root) return;
    fetch(window.OmniGoCRMConfig.appUrl, { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('Unable to load React app source.'); return r.text(); })
      .then(function (source) {
        source = source.replace(/^import[^\\n]*\\n/gm, '').replace(/export default function App/, 'function App');
        var transformed = Babel.transform(source, { presets: ['react'] }).code;
        new Function('React', 'ReactDOM', transformed + '\\nReactDOM.createRoot(document.getElementById("omnigocrm-app")).render(React.createElement(App));')(window.React, window.ReactDOM);
      })
      .catch(function (err) {
        root.innerHTML = '<div style="padding:24px;background:#fff3f3;border:1px solid #f0b8b8;border-radius:12px;color:#8b1e1e"><strong>OmniGoCRM React failed to load.</strong><br>' + String(err.message || err) + '</div>';
      });
  }

  Promise.all([
    addScript('https://unpkg.com/react@18/umd/react.production.min.js'),
    addScript('https://unpkg.com/react-dom@18/umd/react-dom.production.min.js'),
    addScript('https://unpkg.com/@babel/standalone@7.28.4/babel.min.js')
  ]).then(boot).catch(function () {
    var root = document.getElementById('omnigocrm-app');
    if (root) root.innerHTML = '<div style="padding:24px;background:#fff3f3;border:1px solid #f0b8b8;border-radius:12px;color:#8b1e1e"><strong>OmniGoCRM React dependencies could not be loaded.</strong></div>';
  });
})();
