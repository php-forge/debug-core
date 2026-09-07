/** Keeps capture-specific shell metadata and links aligned with the History cursor. */
export function updateHistoryCapture(root, snapshot, locationValue) {
  if (!snapshot.tag) {
    return;
  }

  var links = root.querySelectorAll(
    '.yii-debug-nav-link[href], a.yii-debug-brand-chip-config[href]',
  );

  for (var i = 0; i < links.length; i++) {
    var target = new URL(links[i].getAttribute("href"), locationValue);
    if (target.origin !== new URL(locationValue).origin || !target.searchParams.has("tag")) {
      continue;
    }
    target.searchParams.set("tag", snapshot.tag);
    links[i].setAttribute("href", target.pathname + target.search + target.hash);
  }

  var header = root.querySelector(".yii-debug-brand-bar");
  if (!header) {
    return;
  }

  var chip = header.querySelector(".yii-debug-brand-chip-mem");
  if (!snapshot.memory) {
    if (chip) {
      chip.remove();
    }
    return;
  }

  if (!chip) {
    chip = root.createElement("span");
    chip.className = "yii-debug-brand-chip yii-debug-brand-chip-mem";
    var label = root.createElement("span");
    label.className = "yii-debug-brand-label";
    label.textContent = "Memory";
    var value = root.createElement("span");
    value.className = "yii-debug-brand-value";
    chip.append(label, value);
    header.insertBefore(chip, header.querySelector(".yii-debug-brand-chip-config"));
  }
  chip.querySelector(".yii-debug-brand-value").textContent = snapshot.memory;
}
