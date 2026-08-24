function fragmentId(locationValue) {
  var hash = locationValue && locationValue.hash ? locationValue.hash : "";

  try {
    return hash.length > 1 ? decodeURIComponent(hash.slice(1)) : "";
  } catch {
    return "";
  }
}

export function headingSlug(value) {
  var slug = String(value || "")
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");

  return slug || "section";
}

export function headingLabel(heading) {
  var skipped =
    ".yii-debug-section-count, .yii-debug-badge, .yii-debug-panel-heading-kind, " +
    '.yii-debug-heading-permalink, [data-yii-debug-count], [aria-hidden="true"]';

  function textFrom(node) {
    if (node.nodeType === 3) {
      return node.textContent || "";
    }

    if (node.nodeType !== 1 || (node.matches && node.matches(skipped))) {
      return "";
    }

    return Array.from(node.childNodes || [])
      .map(textFrom)
      .join(" ");
  }

  var label = Array.from(heading.childNodes || [])
    .map(textFrom)
    .join(" ");

  return (label || heading.textContent || "").replace(/\s+/g, " ").trim();
}

function availableId(documentValue, base, heading) {
  var candidate = base;
  var suffix = 2;

  while (
    documentValue.getElementById(candidate) &&
    documentValue.getElementById(candidate) !== heading
  ) {
    candidate = base + "-" + suffix;
    suffix += 1;
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
  var enhanced = 0;

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
    enhanced += 1;
  }

  return enhanced;
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
