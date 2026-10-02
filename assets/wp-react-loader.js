(function () {
  if (window.__omnigocrmReactLoaded) return;
  window.__omnigocrmReactLoaded = true;

  function setError(root, err) {
    root.innerHTML =
      '<div style="padding:24px;background:#fff3f3;border:1px solid #f0b8b8;border-radius:12px;color:#8b1e1e">' +
      '<strong>OmniGoCRM React failed to load.</strong><br>' +
      String((err && err.message) || err || 'Unknown error') +
      '</div>';
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.onload = resolve;
      script.onerror = function () { reject(new Error('Unable to load ' + src)); };
      document.head.appendChild(script);
    });
  }

  function getReact() {
    return window.wp && window.wp.element ? window.wp.element : null;
  }

  function boot() {
    var root = document.getElementById('omnigocrm-app');
    var React = getReact();
    if (!root) return;

    if (!React || typeof React.createRoot !== 'function') {
      setError(root, new Error('WordPress React runtime (wp.element) is unavailable.'));
      return;
    }

    var ready = window.Babel
      ? Promise.resolve()
      : loadScript('https://unpkg.com/@babel/standalone@7.28.4/babel.min.js');

    ready
      .then(function () {
        return fetch(window.OmniGoCRMConfig.appUrl, {
          credentials: 'same-origin',
          cache: 'no-store'
        });
      })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Unable to load React app source (' + response.status + ').');
        }
        return response.text();
      })
      .then(function (source) {
        // App.jsx is JSX source. Remove module syntax before passing it to the
        // classic WordPress runtime; Babel handles the JSX transform.
        source = source
          .split(/\r?\n/)
          .filter(function (line) {
            return !/^\s*import\b/.test(line);
          })
          .join('\n')
          .replace(/^\s*export\s+default\s+function\s+App\b/m, 'function App');

        var transformed = Babel.transform(source, {
          presets: [['react', { runtime: 'classic' }]],
          sourceType: 'script'
        }).code;

        var getApp = new Function('React', transformed + '\nreturn App;');
        var App = getApp(React);
        React.createRoot(root).render(
          React.createElement(
            React.StrictMode,
            null,
            React.createElement(App)
          )
        );
      })
      .catch(function (err) {
        setError(root, err);
        if (window.console && console.error) {
          console.error('OmniGoCRM React bootstrap failed:', err);
        }
      });
  }

  // wp-element is an enqueued WordPress dependency, so it should already exist.
  boot();
})();
