import assert from "node:assert/strict";
import { test } from "vitest";

class ClassList {
  constructor() {
    this.values = new Set();
  }

  add(...names) {
    names.forEach((name) => this.values.add(name));
  }

  contains(name) {
    return this.values.has(name);
  }

  remove(...names) {
    names.forEach((name) => this.values.delete(name));
  }

  toggle(name, force) {
    var next = force === undefined ? !this.values.has(name) : Boolean(force);

    if (next) {
      this.values.add(name);
    } else {
      this.values.delete(name);
    }

    return next;
  }
}

class Element {
  constructor(props = {}) {
    this.attributes = new Map();
    this.classList = new ClassList();
    this.hidden = false;
    this.id = "";
    this.listeners = new Map();
    this.many = {};
    this.parentElement = null;
    this.single = {};
    this.textContent = "";
    Object.assign(this, props);
  }

  addEventListener(type, handler) {
    this.listeners.set(type, handler);
  }

  dispatch(type, event = {}) {
    this.listeners.get(type)(event);
  }

  getAttribute(name) {
    return this.attributes.has(name) ? this.attributes.get(name) : null;
  }

  hasAttribute(name) {
    return this.attributes.has(name);
  }

  querySelector(selector) {
    return this.single[selector] ?? null;
  }

  querySelectorAll(selector) {
    return this.many[selector] ?? [];
  }

  removeAttribute(name) {
    this.attributes.delete(name);
  }

  setAttribute(name, value) {
    this.attributes.set(name, value);
  }
}

function createElement(props = {}, attributes = {}) {
  var element = new Element(props);

  Object.entries(attributes).forEach(([name, value]) => {
    element.setAttribute(name, value);
  });

  return element;
}

var replaceStateCalls = [];
var timers = new Map();
var timerSeq = 0;
var windowListeners = [];

function installWindow(hash = "") {
  replaceStateCalls = [];
  timers = new Map();
  windowListeners = [];
  globalThis.window = {
    addEventListener(type, handler) {
      windowListeners.push({ type, handler });
    },
    clearTimeout(id) {
      timers.delete(id);
    },
    history: {
      replaceState(_state, _title, url) {
        replaceStateCalls.push(url);
      },
    },
    location: { hash },
    setTimeout(callback) {
      timerSeq += 1;
      timers.set(timerSeq, callback);
      return timerSeq;
    },
  };
}

function flushTimers() {
  var callbacks = [...timers.values()];

  timers.clear();
  callbacks.forEach((callback) => callback());

  return callbacks.length;
}

function lastHashChangeHandler() {
  var entries = windowListeners.filter((entry) => entry.type === "hashchange");

  return entries[entries.length - 1].handler;
}

var documentQueryCount = 0;

installWindow();
globalThis.document = {
  querySelector() {
    documentQueryCount += 1;
    return null;
  },
  querySelectorAll() {
    return [];
  },
};

const { initPhpInfoSearch } = await import("../src/panels/phpinfo-search.js");

function createDirectiveRow(key, localValue, masterValue) {
  var keyCell = { textContent: key };
  var localCell = { textContent: localValue, title: "" };
  var masterCell = { textContent: masterValue };
  var row = new Element();

  row.single['th[scope="row"], th.e, td.e'] = keyCell;
  row.single['th[scope="row"]'] = keyCell;
  row.many.td = [localCell, masterCell];

  return { row, localCell };
}

function createFixture() {
  var search = createElement({ focusCalls: 0, value: "" });

  search.focus = function () {
    search.focusCalls += 1;
  };

  var clear = createElement();
  var status = createElement();
  var empty = createElement();

  var headerCore = createElement();

  headerCore.classList.add("h");

  var memory = createDirectiveRow("memory_limit", "128M", "512M");
  var post = createDirectiveRow("post_max_size", "8M", "8M");
  var noLabelRow = new Element();
  var tripleRow = new Element();

  tripleRow.single['th[scope="row"]'] = { textContent: "precision" };
  tripleRow.many.td = [
    { textContent: "14" },
    { textContent: "14" },
    { textContent: "14" },
  ];

  var count1 = createElement({ textContent: "3 directives" });
  var wrap1 = createElement(
    { open: true },
    {
      "data-yii-debug-phpinfo-collapsible": "",
      "data-yii-debug-phpinfo-default-open": "true",
    },
  );

  wrap1.single[".yii-debug-phpinfo-table-section-count"] = count1;
  wrap1.many.tr = [headerCore, memory.row, post.row];

  var headerUpload = createElement();

  headerUpload.classList.add("h");

  var upload = createDirectiveRow("upload_tmp_dir", "/tmp", "/tmp");
  var wrap2 = createElement();

  wrap2.many.tr = [headerUpload, upload.row];

  var coreSection = createElement(
    { id: "phpinfo-core" },
    { "data-section": "Core" },
  );

  coreSection.classList.add("yii-debug-phpinfo-module");
  coreSection.many[".yii-debug-table-wrap"] = [wrap1, wrap2];
  coreSection.many.tr = [
    headerCore,
    memory.row,
    post.row,
    headerUpload,
    upload.row,
    noLabelRow,
    tripleRow,
  ];
  coreSection.many[".yii-debug-phpinfo-table-section.is-directives tr"] = [
    headerCore,
    memory.row,
    post.row,
    noLabelRow,
    tripleRow,
  ];

  var dateSection = createElement(
    { id: "phpinfo-date" },
    { "data-section": "date" },
  );

  dateSection.classList.add("yii-debug-phpinfo-module");

  var overviewSection = createElement(
    { id: "phpinfo-overview" },
    { "data-section": "Overview" },
  );
  var creditsSection = createElement(
    { id: "phpinfo-credits" },
    { "data-section": "Credits" },
  );
  var anonymousSection = createElement({}, { "data-section": "Unlabeled" });

  var mbstring = createElement(
    {
      id: "phpinfo-ext-mbstring",
      scrollCalls: [],
      textContent: "Multibyte string support enabled",
    },
    { "data-section": "mbstring" },
  );

  mbstring.scrollIntoView = function (options) {
    mbstring.scrollCalls.push(options);
  };

  var curl = createElement(
    { id: "phpinfo-ext-curl", textContent: "cURL support enabled" },
    { "data-section": "curl" },
  );

  var groupACount = createElement(
    { textContent: "1 extension" },
    { "data-yii-debug-phpinfo-total": "1 extension" },
  );
  var groupA = createElement();

  groupA.single["[data-yii-debug-phpinfo-extension-group-count]"] = groupACount;
  groupA.many["[data-yii-debug-phpinfo-compact-module]"] = [mbstring];

  var groupB = createElement();

  groupB.many["[data-yii-debug-phpinfo-compact-module]"] = [curl];

  var compactDetails = createElement({ open: true });
  var overviewHero = createElement();
  var configureDetails = createElement();

  var tocOverview = createElement(
    { parentElement: { tagName: "LI", hidden: false } },
    { "data-toc-target": "phpinfo-overview" },
  );
  var tocCore = createElement(
    { parentElement: { tagName: "LI", hidden: false } },
    { "data-toc-target": "phpinfo-core" },
  );
  var tocDate = createElement(
    { parentElement: { tagName: "DIV" } },
    { "data-toc-target": "phpinfo-date" },
  );
  var tocLoose = createElement();

  var tocGroupModules = createElement({ open: false });

  tocGroupModules.many[".yii-debug-phpinfo-toc-link"] = [tocCore, tocDate];

  var tocGroupLoose = createElement({ open: false });

  tocGroupLoose.many[".yii-debug-phpinfo-toc-link"] = [tocLoose];

  var root = new Element();

  root.single["[data-yii-debug-phpinfo-search]"] = search;
  root.single["[data-yii-debug-phpinfo-clear]"] = clear;
  root.single["[data-yii-debug-phpinfo-status]"] = status;
  root.single["[data-yii-debug-phpinfo-empty]"] = empty;
  root.single["[data-yii-debug-phpinfo-extensions]"] = compactDetails;
  root.single["#phpinfo-overview > .yii-debug-disclosure"] = configureDetails;
  root.many[".yii-debug-phpinfo-section"] = [
    overviewSection,
    coreSection,
    dateSection,
    creditsSection,
    anonymousSection,
  ];
  root.many["[data-yii-debug-phpinfo-compact-module]"] = [mbstring, curl];
  root.many[".yii-debug-phpinfo-extension-group"] = [groupA, groupB];
  root.many[
    "#phpinfo-overview .yii-debug-phpinfo-overview-hero-section:not(.yii-debug-phpinfo-extensions)"
  ] = [overviewHero];
  root.many[".yii-debug-phpinfo-toc-link"] = [
    tocOverview,
    tocCore,
    tocDate,
    tocLoose,
  ];
  root.many["[data-yii-debug-phpinfo-toc-group]"] = [
    tocGroupModules,
    tocGroupLoose,
  ];

  return {
    anonymousSection,
    clear,
    compactDetails,
    configureDetails,
    coreSection,
    count1,
    creditsSection,
    curl,
    dateSection,
    empty,
    groupA,
    groupACount,
    groupB,
    headerCore,
    headerUpload,
    mbstring,
    memoryLocalCell: memory.localCell,
    memoryRow: memory.row,
    noLabelRow,
    overviewHero,
    overviewSection,
    postRow: post.row,
    root,
    search,
    status,
    tocCore,
    tocDate,
    tocGroupLoose,
    tocGroupModules,
    tocLoose,
    tocOverview,
    tripleRow,
    uploadRow: upload.row,
    wrap1,
    wrap2,
  };
}

function runSearch(fixture, value) {
  fixture.search.value = value;
  fixture.search.dispatch("input");
  flushTimers();
}

test("module bootstrap runs against the global document and roots without sections bail out", () => {
  assert.equal(documentQueryCount, 6);

  var listenerCount = windowListeners.length;

  initPhpInfoSearch(new Element());

  assert.equal(windowListeners.length, listenerCount);
});

test("initial section hash selects the module and marks local directive overrides", () => {
  var fixture = createFixture();

  installWindow("#phpinfo-core");
  initPhpInfoSearch(fixture.root);

  assert.equal(fixture.coreSection.hidden, false);
  assert.equal(fixture.overviewSection.hidden, true);
  assert.equal(fixture.dateSection.hidden, true);
  assert.equal(fixture.creditsSection.hidden, true);
  assert.equal(fixture.anonymousSection.hidden, true);
  assert.equal(fixture.tocCore.classList.contains("is-active"), true);
  assert.equal(fixture.tocCore.getAttribute("aria-current"), "page");
  assert.equal(fixture.tocOverview.classList.contains("is-active"), false);
  assert.equal(fixture.tocOverview.getAttribute("aria-current"), null);
  assert.equal(fixture.tocGroupModules.open, true);
  assert.equal(fixture.tocGroupModules.hidden, false);
  assert.equal(fixture.tocGroupLoose.open, false);
  assert.equal(fixture.tocGroupLoose.hidden, false);
  assert.equal(fixture.status.textContent, "");
  assert.equal(fixture.empty.hidden, true);
  assert.equal(fixture.clear.hidden, true);
  assert.deepEqual(replaceStateCalls, []);
  assert.equal(
    fixture.memoryRow.classList.contains("has-local-override"),
    true,
  );
  assert.equal(
    fixture.memoryLocalCell.title,
    "Local value differs from master value",
  );
  assert.equal(fixture.postRow.classList.contains("has-local-override"), false);
  assert.equal(
    fixture.noLabelRow.classList.contains("has-local-override"),
    false,
  );
  assert.equal(
    fixture.tripleRow.classList.contains("has-local-override"),
    false,
  );
  assert.equal(
    fixture.headerCore.classList.contains("has-local-override"),
    false,
  );
});

test("initial extension hash reveals the overview and scrolls to the compact module", () => {
  var fixture = createFixture();

  installWindow("#phpinfo-ext-mbstring");
  initPhpInfoSearch(fixture.root);

  assert.equal(fixture.overviewSection.hidden, false);
  assert.equal(fixture.coreSection.hidden, true);
  assert.equal(fixture.compactDetails.open, true);
  assert.equal(flushTimers(), 1);
  assert.deepEqual(fixture.mbstring.scrollCalls, [
    { behavior: "smooth", block: "center" },
  ]);
  assert.deepEqual(replaceStateCalls, []);
});

test("unknown location hashes fall back to the first section", () => {
  var fixture = createFixture();

  installWindow("#phpinfo-nope");
  initPhpInfoSearch(fixture.root);

  assert.equal(fixture.overviewSection.hidden, false);
  assert.equal(fixture.coreSection.hidden, true);
  assert.equal(fixture.tocOverview.classList.contains("is-active"), true);
});

test("directive searches debounce input and reveal matching rows with counts", () => {
  var fixture = createFixture();

  installWindow();
  initPhpInfoSearch(fixture.root);

  fixture.search.value = "memory";
  fixture.search.dispatch("input");
  fixture.search.dispatch("input");

  assert.equal(timers.size, 1);
  assert.equal(flushTimers(), 1);

  assert.equal(fixture.coreSection.hidden, false);
  assert.equal(fixture.overviewSection.hidden, true);
  assert.equal(fixture.dateSection.hidden, true);
  assert.equal(fixture.creditsSection.hidden, true);
  assert.equal(fixture.wrap1.hidden, false);
  assert.equal(fixture.wrap1.open, true);
  assert.equal(fixture.count1.textContent, "1 of 3 directives");
  assert.equal(
    fixture.count1.getAttribute("data-yii-debug-phpinfo-total"),
    "3 directives",
  );
  assert.equal(fixture.headerCore.hidden, false);
  assert.equal(fixture.memoryRow.hidden, false);
  assert.equal(fixture.memoryRow.classList.contains("is-search-match"), true);
  assert.equal(fixture.postRow.hidden, true);
  assert.equal(fixture.wrap2.hidden, true);
  assert.equal(fixture.headerUpload.hidden, true);
  assert.equal(fixture.uploadRow.hidden, true);
  assert.equal(fixture.compactDetails.open, false);
  assert.equal(fixture.mbstring.hidden, true);
  assert.equal(fixture.curl.hidden, true);
  assert.equal(fixture.groupA.hidden, true);
  assert.equal(fixture.groupB.hidden, true);
  assert.equal(fixture.overviewHero.hidden, true);
  assert.equal(fixture.configureDetails.hidden, true);
  assert.equal(fixture.status.textContent, "1 matching row");
  assert.equal(fixture.empty.hidden, true);
  assert.equal(fixture.clear.hidden, false);
  assert.equal(fixture.tocCore.hidden, false);
  assert.equal(fixture.tocCore.classList.contains("is-active"), false);
  assert.equal(fixture.tocOverview.hidden, true);
  assert.equal(fixture.tocOverview.parentElement.hidden, true);
  assert.equal(fixture.tocDate.hidden, true);
  assert.equal(fixture.tocDate.parentElement.hidden, undefined);
  assert.equal(fixture.tocLoose.hidden, true);
  assert.equal(fixture.tocGroupModules.hidden, false);
  assert.equal(fixture.tocGroupModules.open, true);
  assert.equal(fixture.tocGroupLoose.hidden, true);
  assert.equal(fixture.tocGroupLoose.open, false);

  runSearch(fixture, "upload");

  assert.equal(fixture.wrap1.hidden, true);
  assert.equal(fixture.wrap1.open, false);
  assert.equal(fixture.count1.textContent, "0 of 3 directives");
  assert.equal(fixture.wrap2.hidden, false);
  assert.equal(fixture.headerUpload.hidden, false);
  assert.equal(fixture.uploadRow.hidden, false);
  assert.equal(fixture.uploadRow.classList.contains("is-search-match"), true);
  assert.equal(fixture.memoryRow.hidden, true);
  assert.equal(fixture.status.textContent, "1 matching row");

  runSearch(fixture, "_");

  assert.equal(fixture.status.textContent, "3 matching rows");
  assert.equal(fixture.count1.textContent, "2 of 3 directives");
  assert.equal(fixture.wrap1.hidden, false);
  assert.equal(fixture.wrap2.hidden, false);
});

test("module and extension searches update grouped results and the status line", () => {
  var fixture = createFixture();

  installWindow();
  initPhpInfoSearch(fixture.root);

  runSearch(fixture, "date");

  assert.equal(fixture.dateSection.hidden, false);
  assert.equal(fixture.coreSection.hidden, true);
  assert.equal(fixture.overviewSection.hidden, true);
  assert.equal(fixture.status.textContent, "1 module");
  assert.equal(fixture.empty.hidden, true);

  runSearch(fixture, "curl");

  assert.equal(fixture.overviewSection.hidden, false);
  assert.equal(fixture.curl.hidden, false);
  assert.equal(fixture.curl.classList.contains("is-search-match"), true);
  assert.equal(fixture.mbstring.hidden, true);
  assert.equal(fixture.groupA.hidden, true);
  assert.equal(fixture.groupB.hidden, false);
  assert.equal(fixture.compactDetails.open, true);
  assert.equal(fixture.status.textContent, "1 extension");

  runSearch(fixture, "multibyte");

  assert.equal(fixture.mbstring.hidden, false);
  assert.equal(fixture.groupA.hidden, false);
  assert.equal(fixture.groupACount.textContent, "1 of 1 extension");
  assert.equal(fixture.groupB.hidden, true);
  assert.equal(fixture.status.textContent, "1 extension");

  runSearch(fixture, "overview");

  assert.equal(fixture.overviewSection.hidden, false);
  assert.equal(fixture.mbstring.hidden, false);
  assert.equal(fixture.curl.hidden, false);
  assert.equal(fixture.overviewHero.hidden, false);
  assert.equal(fixture.configureDetails.hidden, false);
  assert.equal(fixture.groupACount.textContent, "1 extension");
  assert.equal(fixture.status.textContent, "1 module");

  runSearch(fixture, "zzz");

  assert.equal(fixture.overviewSection.hidden, true);
  assert.equal(fixture.coreSection.hidden, true);
  assert.equal(fixture.status.textContent, "");
  assert.equal(fixture.empty.hidden, false);
  assert.equal(fixture.clear.hidden, false);
});

test("clear control resets the query, focuses the box and restores browsing", () => {
  var fixture = createFixture();

  installWindow("#phpinfo-core");
  initPhpInfoSearch(fixture.root);

  runSearch(fixture, "memory");

  assert.equal(fixture.compactDetails.open, false);

  fixture.search.value = "mem";
  fixture.search.dispatch("input");

  assert.equal(timers.size, 1);

  fixture.clear.dispatch("click");

  assert.equal(timers.size, 0);
  assert.equal(fixture.search.value, "");
  assert.equal(fixture.search.focusCalls, 1);
  assert.equal(fixture.compactDetails.open, true);
  assert.equal(fixture.coreSection.hidden, false);
  assert.equal(fixture.overviewSection.hidden, true);
  assert.equal(fixture.status.textContent, "");
  assert.equal(fixture.clear.hidden, true);
  assert.equal(fixture.empty.hidden, true);
  assert.equal(fixture.count1.textContent, "3 directives");
  assert.equal(fixture.wrap1.open, true);
  assert.equal(fixture.wrap1.hidden, false);
  assert.equal(fixture.wrap2.hidden, false);
  assert.equal(fixture.memoryRow.classList.contains("is-search-match"), false);
  assert.equal(fixture.memoryRow.hidden, false);
  assert.equal(fixture.postRow.hidden, false);
  assert.equal(fixture.mbstring.hidden, false);
  assert.equal(fixture.groupA.hidden, false);
});

test("escape clears only a populated search box", () => {
  var fixture = createFixture();

  installWindow();
  initPhpInfoSearch(fixture.root);

  runSearch(fixture, "memory");

  var prevented = 0;
  var escapeEvent = {
    key: "Escape",
    preventDefault() {
      prevented += 1;
    },
  };

  fixture.search.dispatch("keydown", escapeEvent);

  assert.equal(prevented, 1);
  assert.equal(fixture.search.value, "");
  assert.equal(fixture.search.focusCalls, 1);
  assert.equal(fixture.clear.hidden, true);

  fixture.search.dispatch("keydown", escapeEvent);

  assert.equal(prevented, 1);

  fixture.search.dispatch("keydown", {
    key: "a",
    preventDefault() {
      prevented += 1;
    },
  });

  assert.equal(prevented, 1);
});

test("blank queries return to browsing with and without an active filter", () => {
  var fixture = createFixture();

  installWindow();
  initPhpInfoSearch(fixture.root);

  runSearch(fixture, "   ");

  assert.equal(fixture.clear.hidden, true);
  assert.equal(fixture.status.textContent, "");
  assert.equal(fixture.overviewSection.hidden, false);

  runSearch(fixture, "memory");

  assert.equal(fixture.compactDetails.open, false);
  assert.equal(fixture.clear.hidden, false);

  runSearch(fixture, "");

  assert.equal(fixture.compactDetails.open, true);
  assert.equal(fixture.clear.hidden, true);
  assert.equal(fixture.overviewSection.hidden, false);
  assert.equal(fixture.coreSection.hidden, true);
});

test("toc links navigate sections and rewrite the location hash", () => {
  var fixture = createFixture();

  installWindow();
  initPhpInfoSearch(fixture.root);

  runSearch(fixture, "memory");

  var prevented = 0;
  var clickEvent = {
    preventDefault() {
      prevented += 1;
    },
  };

  fixture.tocCore.dispatch("click", clickEvent);

  assert.equal(prevented, 1);
  assert.equal(fixture.coreSection.hidden, false);
  assert.equal(fixture.search.value, "");
  assert.equal(fixture.compactDetails.open, true);
  assert.deepEqual(replaceStateCalls, ["#phpinfo-core"]);

  fixture.tocLoose.dispatch("click", clickEvent);

  assert.equal(prevented, 1);
  assert.deepEqual(replaceStateCalls, ["#phpinfo-core"]);

  window.history = null;
  fixture.tocOverview.dispatch("click", clickEvent);

  assert.equal(prevented, 2);
  assert.equal(fixture.overviewSection.hidden, false);
  assert.deepEqual(replaceStateCalls, ["#phpinfo-core"]);

  window.history = {};
  fixture.tocCore.dispatch("click", clickEvent);

  assert.equal(prevented, 3);
  assert.equal(fixture.coreSection.hidden, false);
  assert.deepEqual(replaceStateCalls, ["#phpinfo-core"]);
});

test("hash navigation switches sections and reveals compact extensions", () => {
  var fixture = createFixture();

  installWindow();
  initPhpInfoSearch(fixture.root);

  var onHashChange = lastHashChangeHandler();

  window.location.hash = "#phpinfo-date";
  onHashChange();

  assert.equal(fixture.dateSection.hidden, false);
  assert.equal(fixture.overviewSection.hidden, true);
  assert.deepEqual(replaceStateCalls, []);

  window.location.hash = "#phpinfo-ext-curl";
  onHashChange();

  assert.equal(fixture.overviewSection.hidden, false);
  assert.equal(fixture.dateSection.hidden, true);
  assert.equal(fixture.compactDetails.open, true);
  assert.equal(flushTimers(), 0);

  window.location.hash = "#phpinfo-unknown";
  onHashChange();

  assert.equal(fixture.overviewSection.hidden, false);
});

test("filtering works without status, empty, clear or extension containers", () => {
  var overview = createElement(
    { id: "phpinfo-overview" },
    { "data-section": "Overview" },
  );
  var search = createElement({ value: "" });

  search.focus = function () {};

  var root = new Element();

  root.single["[data-yii-debug-phpinfo-search]"] = search;
  root.many[".yii-debug-phpinfo-section"] = [overview];

  installWindow();
  initPhpInfoSearch(root);

  assert.equal(overview.hidden, false);

  search.value = "zzz";
  search.dispatch("input");
  flushTimers();

  assert.equal(overview.hidden, true);

  search.value = "";
  search.dispatch("input");
  flushTimers();

  assert.equal(overview.hidden, false);
});

test("clear-only roots reset browsing and follow hash navigation without a search box", () => {
  var overview = createElement(
    { id: "phpinfo-overview" },
    { "data-section": "Overview" },
  );
  var core = createElement({ id: "phpinfo-core" }, { "data-section": "Core" });
  var clear = createElement();
  var root = new Element();

  root.single["[data-yii-debug-phpinfo-clear]"] = clear;
  root.many[".yii-debug-phpinfo-section"] = [overview, core];

  installWindow();
  initPhpInfoSearch(root);

  assert.equal(overview.hidden, false);
  assert.equal(clear.hidden, true);

  clear.hidden = false;
  clear.dispatch("click");

  assert.equal(clear.hidden, true);
  assert.equal(overview.hidden, false);

  window.location.hash = "#phpinfo-core";
  lastHashChangeHandler()();

  assert.equal(core.hidden, false);
  assert.equal(overview.hidden, true);
});

test("extension hashes without an overview section fall back to the first section", () => {
  var main = createElement({ id: "phpinfo-main" }, { "data-section": "Main" });
  var solo = createElement(
    { id: "phpinfo-ext-solo", textContent: "Solo extension" },
    { "data-section": "solo" },
  );
  var root = new Element();

  root.many[".yii-debug-phpinfo-section"] = [main];
  root.many["[data-yii-debug-phpinfo-compact-module]"] = [solo];

  installWindow("#phpinfo-ext-solo");
  initPhpInfoSearch(root);

  assert.equal(main.hidden, false);
  assert.equal(solo.hidden, false);
  assert.equal(flushTimers(), 0);
});

test("filtered table counts capture missing totals from the rendered text", () => {
  var countLookups = 0;
  var count = createElement({ textContent: " 5 settings " });
  var row = new Element();

  row.single['th[scope="row"], th.e, td.e'] = { textContent: "tls.enabled" };

  var wrap = new Element();

  wrap.querySelector = function () {
    countLookups += 1;
    return countLookups >= 3 ? count : null;
  };
  wrap.many.tr = [row];

  var section = createElement(
    { id: "phpinfo-tls" },
    { "data-section": "Transport" },
  );

  section.classList.add("yii-debug-phpinfo-module");
  section.many[".yii-debug-table-wrap"] = [wrap];
  section.many.tr = [row];

  var search = createElement({ value: "" });

  search.focus = function () {};

  var root = new Element();

  root.single["[data-yii-debug-phpinfo-search]"] = search;
  root.many[".yii-debug-phpinfo-section"] = [section];

  installWindow();
  initPhpInfoSearch(root);

  search.value = "tls";
  search.dispatch("input");
  flushTimers();

  assert.equal(section.hidden, false);
  assert.equal(wrap.hidden, false);
  assert.equal(row.hidden, false);
  assert.equal(row.classList.contains("is-search-match"), true);
  assert.equal(
    count.getAttribute("data-yii-debug-phpinfo-total"),
    "5 settings",
  );
  assert.equal(count.textContent, "1 of 5 settings");
  assert.equal(countLookups, 3);
});
