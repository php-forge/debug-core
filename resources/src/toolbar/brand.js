export function renderYiiBrand(version, configUrl, logoHtml, escapeHtml) {
  var content =
    logoHtml +
    (version
      ? '<span class="brand-version">' + escapeHtml(version) + "</span>"
      : "");
  var title = version
    ? "Yii " + version + " — open configuration"
    : "Open configuration";

  if (!configUrl) {
    return (
      '<span class="brand-link brand-link-yii brand-static" title="' +
      escapeHtml(title) +
      '">' +
      content +
      "</span>"
    );
  }

  var url = escapeHtml(configUrl);

  return (
    '<a class="brand-link brand-link-yii" href="' +
    url +
    '" data-debug-url="' +
    url +
    '" title="' +
    escapeHtml(title) +
    '">' +
    content +
    "</a>"
  );
}

export function renderPhpBrand(version, phpInfoUrl, iconHtml, escapeHtml) {
  if (!version) {
    return "";
  }

  var content =
    iconHtml + '<span class="brand-version">' + escapeHtml(version) + "</span>";

  if (!phpInfoUrl) {
    return (
      '<span class="brand-link brand-link-php brand-static" title="' +
      escapeHtml("PHP " + version + " — phpinfo unavailable") +
      '">' +
      content +
      "</span>"
    );
  }

  return (
    '<a class="brand-link brand-link-php" href="' +
    escapeHtml(phpInfoUrl) +
    '" target="_blank" rel="noopener" title="' +
    escapeHtml("PHP " + version + " — open phpinfo in a new tab") +
    '">' +
    content +
    "</a>"
  );
}
