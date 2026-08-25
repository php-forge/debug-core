var SKIPPED_HEADING_CONTENT =
  ".yii-debug-section-count, .yii-debug-badge, .yii-debug-panel-heading-kind, " +
  '.yii-debug-heading-permalink, [data-yii-debug-count], [aria-hidden="true"]';

export function fragmentId(locationValue) {
  var hash = locationValue && locationValue.hash ? locationValue.hash : "";

  try {
    return hash.length > 1 ? decodeURIComponent(hash.slice(1)) : "";
  } catch {
    return "";
  }
}

export function headingSlug(value) {
  // The preceding replace collapses separator runs, so at most a single dash
  // can lead or trail the slug.
  var slug = String(value || "")
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");

  return slug || "section";
}

function headingTextFrom(node) {
  if (node.nodeType === 3) {
    return node.textContent || "";
  }

  if (
    node.nodeType !== 1 ||
    (node.matches && node.matches(SKIPPED_HEADING_CONTENT))
  ) {
    return "";
  }

  return node.childNodes
    ? Array.from(node.childNodes).map(headingTextFrom).join(" ")
    : "";
}

export function headingLabel(heading) {
  var label = heading.childNodes
    ? Array.from(heading.childNodes).map(headingTextFrom).join(" ")
    : "";

  return (label || heading.textContent || "").replace(/\s+/g, " ").trim();
}

function availableId(documentValue, base, heading) {
  var candidate = base;
  var suffix = 2;
  var existing = documentValue.getElementById(candidate);

  while (existing && existing !== heading) {
    candidate = base + "-" + suffix;
    suffix += 1;
    existing = documentValue.getElementById(candidate);
  }

  return candidate;
}

/**
 * Adds stable, discoverable fragment permalinks to panel section headings.
 */
export function enhanceSectionPermalinks(root) {
  var headings = root.querySelectorAll(
    "#yii-debug-main h2, #yii-debug-main h3",
  );

  for (var i = 0; i < headings.length; i++) {
    var heading = headings[i];

    if (
      heading.getAttribute("data-yii-debug-permalink-bound") === "true" ||
      heading.classList.contains("yii-debug-sr-only") ||
      heading.closest('a, button, summary, [aria-hidden="true"]')
    ) {
      continue;
    }

    var label = headingLabel(heading);

    if (!label) {
      continue;
    }

    if (!heading.id) {
      heading.id = availableId(
        heading.ownerDocument,
        "yii-debug-section-" + headingSlug(label),
        heading,
      );
    }

    var link = heading.ownerDocument.createElement("a");
    link.className = "yii-debug-heading-permalink";
    link.href = "#" + encodeURIComponent(heading.id);
    link.textContent = "#";
    link.setAttribute("aria-label", "Permalink to " + label);
    link.setAttribute("title", "Permalink to " + label);
    heading.appendChild(link);
    heading.classList.add("yii-debug-has-permalink");
    heading.setAttribute("data-yii-debug-permalink-bound", "true");
  }
}

/**
 * Reveals disclosure ancestors and highlights the active fragment target.
 */
export function revealDeepLink(root, locationValue, scroll) {
  var id = fragmentId(locationValue);
  var target = id && root.getElementById ? root.getElementById(id) : null;

  if (!target) {
    return null;
  }

  var previous = root.querySelectorAll(".yii-debug-deep-link-target");

  for (var i = 0; i < previous.length; i++) {
    previous[i].classList.remove("yii-debug-deep-link-target");
  }

  var details = target.closest ? target.closest("details") : null;

  while (details) {
    details.open = true;
    details = details.parentElement
      ? details.parentElement.closest("details")
      : null;
  }

  target.classList.add("yii-debug-deep-link-target");

  if (scroll && typeof target.scrollIntoView === "function") {
    target.scrollIntoView({ block: "start" });
  }

  return target;
}

export function initSectionPermalinks(root, windowValue) {
  enhanceSectionPermalinks(root);

  function reveal() {
    windowValue.setTimeout(function () {
      revealDeepLink(root, windowValue.location, true);
    }, 0);
  }

  windowValue.addEventListener("hashchange", reveal);
  windowValue.addEventListener("popstate", reveal);
  reveal();
}
