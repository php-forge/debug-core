import { ajax, on } from "../core/dom.js";

const EXPLAIN_ALL_SELECTOR =
  ".yii-debug-db-explain-all-toggle, .yii-debug-db-explain-all a";
const EXPLAIN_ERROR_MESSAGE = "Unable to load the EXPLAIN output. Try again.";

export const EXPLAIN_CONCURRENCY = 3;

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
  var queuePaused = false;
  var tasks = new Map();

  function containerFor(toggle) {
    return toggle.closest(".yii-debug-db-explain");
  }

  function syncExplainAllControls(expanded) {
    var controls = document.querySelectorAll(EXPLAIN_ALL_SELECTOR);
    var progress = batch
      ? { completed: batch.completed, total: batch.total }
      : null;
    var isExpanded =
      typeof expanded === "boolean"
        ? expanded
        : document.querySelectorAll(
            ".yii-debug-db-explain.is-open, .yii-debug-db-explain.is-loading",
          ).length > 0;

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

      if (
        task.openRequested &&
        task.batchId !== null &&
        batch !== null &&
        task.batchId === batch.id
      ) {
        task.container.classList.add("is-open");
        task.toggle.setAttribute("aria-expanded", "true");
      } else if (task.batchId === null && task.openRequested) {
        task.container.classList.add("is-open");
        task.toggle.setAttribute("aria-expanded", "true");
      } else {
        task.container.classList.remove("is-open");
        task.toggle.setAttribute("aria-expanded", "false");
      }
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
    if (task.finished || task.cancelled) {
      return;
    }

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
    if (queuePaused) {
      return;
    }

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
      // An individual click while Explain All is running must not detach the
      // request from its batch, otherwise batch progress can never complete.
      if (batchId !== null) {
        existing.batchId = batchId;
      }
      existing.openRequested = true;

      return existing;
    }

    var container = containerFor(toggle);
    var target = container
      ? container.querySelector(".yii-debug-db-explain-text")
      : null;

    if (!container || !target) {
      return null;
    }

    var task = {
      batchId: batchId,
      cancelled: false,
      container: container,
      finished: false,
      openRequested: true,
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
    if (!task || task.finished) {
      return;
    }

    task.cancelled = true;

    if (task.request && typeof task.request.abort === "function") {
      task.request.abort();
    }

    finishTask(task, "abort", task.request);
  }

  function collapseAll() {
    if (batch) {
      batch.cancelled = true;
      batch = null;
    }

    queuePaused = true;
    pending = [];

    Array.from(tasks.values()).forEach(abortTask);

    document
      .querySelectorAll(".yii-debug-db-explain-toggle")
      .forEach(function (toggle) {
        var container = containerFor(toggle);
        var target = container
          ? container.querySelector(".yii-debug-db-explain-text")
          : null;

        if (container) {
          container.classList.remove("is-open", "is-loading", "is-requesting");
        }
        if (target) {
          target.removeAttribute("aria-busy");
        }
        toggle.setAttribute("aria-expanded", "false");
      });

    queuePaused = false;
    syncExplainAllControls(false);
  }

  function expandAll() {
    var toggles = Array.from(
      document.querySelectorAll(".yii-debug-db-explain-toggle"),
    );

    batch = {
      cancelled: false,
      completed: 0,
      id: ++batchSequence,
      total: toggles.length,
    };

    toggles.forEach(function (toggle) {
      var container = containerFor(toggle);
      var target = container
        ? container.querySelector(".yii-debug-db-explain-text")
        : null;

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
      var target = container
        ? container.querySelector(".yii-debug-db-explain-text")
        : null;

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
        document.querySelectorAll(
          ".yii-debug-db-explain.is-open, .yii-debug-db-explain.is-loading",
        ).length > 0;

      if (anyOpen) {
        collapseAll();
      } else {
        expandAll();
      }
    },
  );

  syncExplainAllControls();

  on(
    document.querySelectorAll("[data-yii-debug-n1-filter]"),
    "click",
    function (event) {
      var groupId = this.getAttribute("data-yii-debug-n1-filter");

      event.preventDefault();
      applyNPlusOneFilter(document, groupId);

      if (window.history && groupId) {
        var url = new URL(window.location.href);
        url.hash = groupId;
        window.history.pushState(null, "", url.href);
      }

      var target = groupId ? document.getElementById(groupId) : null;
      if (target && typeof target.scrollIntoView === "function") {
        target.scrollIntoView({ block: "center" });
      }
    },
  );

  on(
    document.querySelectorAll("[data-yii-debug-n1-clear]"),
    "click",
    function (event) {
      event.preventDefault();
      applyNPlusOneFilter(document, null);
    },
  );
})();
