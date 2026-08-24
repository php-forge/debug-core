export const PANEL_FEATURE_MARKERS = Object.freeze({
  db: ".yii-debug-db-explain-toggle, [data-yii-debug-n1-filter]",
  phpinfo: "[data-yii-debug-phpinfo-search]",
  userswitch:
    "#debug-userswitch__filter, #debug-userswitch__reset-identity-button",
});

const defaultLoaders = {
  db: () => import("../panels/db.js"),
  phpinfo: () => import("../panels/phpinfo-search.js"),
  userswitch: () => import("../panels/userswitch.js"),
};

/**
 * Loads panel-only behavior when its server-rendered marker exists.
 */
export function loadPanelFeatures(root, loaders = defaultLoaders) {
  var pending = [];

  Object.keys(PANEL_FEATURE_MARKERS).forEach(function (name) {
    if (!root.querySelector(PANEL_FEATURE_MARKERS[name])) {
      return;
    }

    pending.push(Promise.resolve().then(loaders[name]));
  });

  return Promise.all(pending);
}
