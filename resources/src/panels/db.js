import { ajax, on } from "../core/dom.js";

const EXPLAIN_ALL_SELECTOR =
  ".yii-debug-db-explain-all-toggle, .yii-debug-db-explain-all a";
const EXPLAIN_OPEN_SELECTOR =
  ".yii-debug-db-explain.is-open, .yii-debug-db-explain.is-loading";
const EXPLAIN_ERROR_MESSAGE = "Unable to load the EXPLAIN output. Try again.";

export const EXPLAIN_CONCURRENCY = 3;

export function updateNPlusOneBanner(root, result, clearFilter) {
  var banner = root.querySelector(".yii-debug-active-filters");

  if (!banner && result.activeGroup) {
    var anchor =
      root.querySelector(".yii-debug-db-n1-summary") ||
      root.querySelector(".yii-debug-grid-db");

    if (!anchor) {
      return;
    }

    banner = root.createElement("div");
    banner.className = "yii-debug-active-filters";
    banner.setAttribute("role", "group");
    banner.setAttribute("aria-label", "Active filters");
    banner.innerHTML =
      '<span class="yii-debug-active-filters-label"></span>' +
      '<span class="yii-debug-active-filters-list"></span>' +
      '<a class="yii-debug-active-filters-clear" href="#" ' +
      'aria-label="Clear all active filters" title="Clear all filters and show every row">Clear all</a>';
    banner
      .querySelector(".yii-debug-active-filters-clear")
      .addEventListener("click", clearFilter);
    anchor.before(banner);
  }

  if (!banner) {
    return;
  }

  var pill = banner.querySelector("[data-yii-debug-n1-pill]");

  if (result.activeGroup) {
    if (!pill) {
      pill = root.createElement("a");
      pill.className = "yii-debug-active-filter-pill";
      pill.setAttribute("data-yii-debug-n1-pill", "true");
      pill.setAttribute("href", "#");
      pill.setAttribute("title", "Remove this filter");
      pill.innerHTML =
        '<span class="yii-debug-active-filter-attr">N+1</span>' +
        '<span class="yii-debug-active-filter-sep">:</span>' +
        '<span class="yii-debug-active-filter-value"></span>' +
        '<span class="yii-debug-active-filter-x" aria-hidden="true">×</span>';
      pill.addEventListener("click", clearFilter);
      banner.querySelector(".yii-debug-active-filters-list").appendChild(pill);
    }

    var label = result.visible + " similar queries";
    pill.querySelector(".yii-debug-active-filter-value").textContent = label;
    pill.setAttribute("aria-label", "Remove N+1: " + label + " filter");
  } else if (pill) {
    pill.remove();
  }

  var count = banner.querySelectorAll(".yii-debug-active-filter-pill").length;

  if (count === 0) {
    banner.remove();
  } else {
    banner.querySelector(".yii-debug-active-filters-label").textContent =
      count + " filter" + (count === 1 ? "" : "s") + " active";
  }
}

export function formatExplainProgress(completed, total) {
  return "Explaining " + completed + "/" + total;
}

export function updateExplainAllControl(control, expanded, progress) {
  if (progress && progress.completed < progress.total) {
    var progressLabel = formatExplainProgress(
      progress.completed,
      progress.total,
    );

    control.textContent = progressLabel;
    control.setAttribute("aria-label", progressLabel + " query plans");
    control.setAttribute("aria-busy", "true");
  } else {
    control.textContent = expanded ? "Collapse all" : "Explain all";
    control.setAttribute(
      "aria-label",
      expanded ? "Collapse all query plans" : "Explain all queries",
    );
    control.removeAttribute("aria-busy");
  }

  control.setAttribute("aria-expanded", expanded ? "true" : "false");
}

export function applyNPlusOneFilter(root, groupId) {
  var markers = Array.from(root.querySelectorAll("[data-yii-debug-n1-group]"));
  var groupByRow = new Map();

  markers.forEach(function (marker) {
    var row = marker.closest("tr");

    if (row) {
      groupByRow.set(row, marker.getAttribute("data-yii-debug-n1-group"));
    }
  });

  var rows = Array.from(root.querySelectorAll(".yii-debug-grid-db tbody tr"));

  if (rows.length === 0) {
    rows = Array.from(groupByRow.keys());
  }

  var valid = markers.some(function (marker) {
    return marker.getAttribute("data-yii-debug-n1-group") === groupId;
  });
  var active = valid ? groupId : null;
  var visible = 0;

  markers.forEach(function (marker) {
    if (marker.getAttribute("data-yii-debug-n1-group") !== active) {
      marker.classList.remove("yii-debug-deep-link-target");
    }
  });

  rows.forEach(function (row) {
    var matched = active === null || groupByRow.get(row) === active;

    row.hidden = !matched;
    visible += matched ? 1 : 0;
  });

  root.querySelectorAll("[data-yii-debug-n1-filter]").forEach(function (link) {
    if (link.getAttribute("data-yii-debug-n1-filter") === active) {
      link.setAttribute("aria-current", "true");
    } else {
      link.removeAttribute("aria-current");
    }
  });

  var clear = root.querySelector("[data-yii-debug-n1-clear]");
  var status = root.querySelector("[data-yii-debug-n1-status]");

  if (clear) {
    clear.hidden = active === null;
  }
  if (status) {
    status.textContent =
      active === null
        ? "Showing all database queries."
        : "Showing " + visible + " potential N+1 queries.";
  }

  return { activeGroup: active, total: rows.length, visible: visible };
}

(function () {
  "use strict";

  var active = new Map();
  var batch = null;
  var batchSequence = 0;
  var pending = [];
  var tasks = new Map();

  function containerFor(toggle) {
    return toggle.closest(".yii-debug-db-explain");
  }

  function explainTextFor(container) {
    return container
      ? container.querySelector(".yii-debug-db-explain-text")
      : null;
  }

  function syncExplainAllControls(expanded) {
    var controls = document.querySelectorAll(EXPLAIN_ALL_SELECTOR);
    var progress = batch
      ? { completed: batch.completed, total: batch.total }
      : null;
    var isExpanded =
      typeof expanded === "boolean"
        ? expanded
        : document.querySelectorAll(EXPLAIN_OPEN_SELECTOR).length > 0;

    for (var i = 0; i < controls.length; i++) {
      updateExplainAllControl(controls[i], isExpanded, progress);
    }
  }

  function finishBatchTask(task) {
    if (!batch || task.batchId !== batch.id) {
      return;
    }

    batch.completed += 1;

    if (batch.completed >= batch.total) {
      batch = null;
    }
  }

  function finishTask(task, outcome, xhr) {
    if (task.finished) {
      return;
    }

    task.finished = true;
    tasks.delete(task.toggle);
    active.delete(task.toggle);
    task.container.classList.remove("is-loading", "is-requesting");
    task.target.removeAttribute("aria-busy");

    if (outcome === "success") {
      task.target.innerHTML = xhr.responseText;
      task.target.dataset.loaded = "1";
      task.container.classList.add("is-open");
      task.toggle.setAttribute("aria-expanded", "true");
    } else if (outcome === "error") {
      task.target.classList.add("is-error");
      task.target.setAttribute("role", "alert");
      task.target.textContent = EXPLAIN_ERROR_MESSAGE;
      task.toggle.setAttribute("aria-expanded", "false");
    } else {
      task.container.classList.remove("is-open");
      task.toggle.setAttribute("aria-expanded", "false");
    }

    finishBatchTask(task);
    drainQueue();
    syncExplainAllControls();
  }

  function startTask(task) {
    task.container.classList.add("is-requesting");
    active.set(task.toggle, task);
    task.request = ajax(task.toggle.href, {
      success: function (xhr) {
        finishTask(task, "success", xhr);
      },
      error: function (xhr) {
        finishTask(task, "error", xhr);
      },
      abort: function (xhr) {
        finishTask(task, "abort", xhr);
      },
    });
  }

  function drainQueue() {
    while (active.size < EXPLAIN_CONCURRENCY && pending.length > 0) {
      var task = pending.shift();

      if (task && !task.finished && !task.cancelled) {
        startTask(task);
      }
    }
  }

  function enqueue(toggle, batchId) {
    var existing = tasks.get(toggle);

    if (existing) {
      // A repeated click while the request is in flight must not restart it or
      // detach it from the batch that originally enqueued it.
      return existing;
    }

    var container = containerFor(toggle);
    var target = explainTextFor(container);
    var task = {
      batchId: batchId,
      cancelled: false,
      container: container,
      finished: false,
      request: null,
      target: target,
      toggle: toggle,
    };

    tasks.set(toggle, task);
    pending.push(task);
    container.classList.add("is-loading");
    target.classList.remove("is-error");
    target.removeAttribute("role");
    target.setAttribute("aria-busy", "true");
    target.textContent = "";
    drainQueue();

    return task;
  }

  function abortTask(task) {
    task.cancelled = true;

    if (task.request && typeof task.request.abort === "function") {
      task.request.abort();
    }

    finishTask(task, "abort", task.request);
  }

  function collapseAll() {
    batch = null;
    pending = [];

    Array.from(tasks.values()).forEach(abortTask);

    document
      .querySelectorAll(".yii-debug-db-explain-toggle")
      .forEach(function (toggle) {
        var container = containerFor(toggle);
        var target = explainTextFor(container);

        if (container) {
          container.classList.remove("is-open", "is-loading", "is-requesting");
        }
        if (target) {
          target.removeAttribute("aria-busy");
        }
        toggle.setAttribute("aria-expanded", "false");
      });

    syncExplainAllControls(false);
  }

  function expandAll() {
    var toggles = Array.from(
      document.querySelectorAll(".yii-debug-db-explain-toggle"),
    );

    batch = {
      completed: 0,
      id: ++batchSequence,
      total: toggles.length,
    };

    toggles.forEach(function (toggle) {
      var container = containerFor(toggle);
      var target = explainTextFor(container);

      if (!container || !target) {
        batch.completed += 1;

        return;
      }

      if (target.dataset.loaded === "1") {
        container.classList.add("is-open");
        toggle.setAttribute("aria-expanded", "true");
        batch.completed += 1;

        return;
      }

      enqueue(toggle, batch.id);
    });

    if (batch && batch.completed >= batch.total) {
      batch = null;
    }

    syncExplainAllControls(true);
  }

  on(
    document.querySelectorAll(".yii-debug-db-explain-toggle"),
    "click",
    function (event) {
      event.preventDefault();

      var container = containerFor(this);
      var target = explainTextFor(container);

      if (!container || !target) {
        return;
      }

      if (container.classList.contains("is-open")) {
        container.classList.remove("is-open");
        this.setAttribute("aria-expanded", "false");
        syncExplainAllControls();

        return;
      }

      if (target.dataset.loaded === "1") {
        container.classList.add("is-open");
        this.setAttribute("aria-expanded", "true");
        syncExplainAllControls();

        return;
      }

      enqueue(this, null);
      syncExplainAllControls();
    },
  );

  on(
    document.querySelectorAll(EXPLAIN_ALL_SELECTOR),
    "click",
    function (event) {
      event.preventDefault();

      var anyOpen =
        batch !== null ||
        document.querySelectorAll(EXPLAIN_OPEN_SELECTOR).length > 0;

      if (anyOpen) {
        collapseAll();
      } else {
        expandAll();
      }
    },
  );

  syncExplainAllControls();

  function updateNPlusOneLocation(groupId) {
    if (!window.history) {
      return;
    }

    var url = new URL(window.location.href);
    var isGroupHash = Array.from(
      document.querySelectorAll("[data-yii-debug-n1-group]"),
    ).some(function (marker) {
      return url.hash === "#" + marker.getAttribute("data-yii-debug-n1-group");
    });

    if (groupId || isGroupHash) {
      url.hash = groupId || "";

      if (url.href !== window.location.href) {
        window.history.pushState(null, "", url.href);
      }
    }
  }

  function clearNPlusOneFilter(event) {
    event.preventDefault();
    var result = applyNPlusOneFilter(document, null);
    updateNPlusOneLocation(null);
    updateNPlusOneBanner(document, result, clearNPlusOneFilter);
  }

  on(
    document.querySelectorAll("[data-yii-debug-n1-filter]"),
    "click",
    function (event) {
      var groupId =
        this.getAttribute("aria-current") === "true"
          ? null
          : this.getAttribute("data-yii-debug-n1-filter");

      event.preventDefault();
      var result = applyNPlusOneFilter(document, groupId);
      updateNPlusOneLocation(result.activeGroup);
      updateNPlusOneBanner(document, result, clearNPlusOneFilter);

      var target = result.activeGroup
        ? document.getElementById(result.activeGroup)
        : null;
      if (target && typeof target.scrollIntoView === "function") {
        target.scrollIntoView({ block: "center" });
      }
    },
  );

  on(
    document.querySelectorAll("[data-yii-debug-n1-clear]"),
    "click",
    clearNPlusOneFilter,
  );
})();
