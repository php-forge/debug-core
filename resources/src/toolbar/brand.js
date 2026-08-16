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
