// @vitest-environment jsdom
import assert from "node:assert/strict";
import { afterEach, test } from "vitest";

import {
  createToolbar,
  installLocalStorage,
  installMatchMedia,
  renderToolbar,
  teardownToolbars,
  toolbarPayload,
} from "./toolbar-element-harness.js";

var storage = installLocalStorage();

installMatchMedia();

/** Detaches the fixtures a thrown assertion would leave connected. */
afterEach(teardownToolbars);

function expanded() {
  storage.set("yii-debug-toolbar-expanded", "1");
}

function collapsed() {
  storage.set("yii-debug-toolbar-expanded", "0");
}

function fullPayload(overrides) {
  return toolbarPayload(
    Object.assign(
      {
        configUrl: "/debug/config",
        iconBaseUrl: "/assets/icons/",
        items: [
          null,
          {
            icon: "profiling",
            id: "profiling",
            items: [
              {
                id: "total",
                label: "Total",
                status: "success",
                value: "12 ms",
              },
            ],
            title: "Profiling",
            url: "/debug/profiling",
          },
          {
            icon: "request",
            id: "request",
            items: [
              {
                id: "status",
                status: "success",
                title: "Status code",
                url: "/debug/request",
                value: 200,
              },
            ],
            title: "Request",
          },
          {
            icon: "db",
            id: "db",
            items: [{ icon: "clock", value: "3" }],
            title: "Database",
            url: "/debug/db",
          },
        ],
        logo: "/debug/logo.svg",
        phpInfoUrl: "/debug/phpinfo",
        phpVersion: "8.1.30",
      },
      overrides || {},
    ),
  );
}

test("render stays a no-op until a payload arrives", () => {
  var element = createToolbar();

  element.render();

  assert.equal(
    element.shadowRoot.querySelector(".bar"),
    null,
    "No skeleton may be built without data.",
  );
  assert.equal(
    element.getPosition(),
    "bottom",
    "Missing payload and attribute must fall back to the bottom dock.",
  );
});

test("a toolbar rendered before it is connected detects its own theme", () => {
  expanded();

  var element = createToolbar();

  element.data = fullPayload();
  element.render();

  assert.equal(element.theme, null, "No theme may be pinned yet.");
  assert.equal(
    element.shadowRoot.querySelector(".brand-link-yii").getAttribute("href"),
    "http://localhost:3000/debug/config?yii_debug_theme=light",
    "Links must be stamped with the detected theme.",
  );
});

test("a collapsed toolbar renders only the branded opener", () => {
  collapsed();

  var element = renderToolbar(fullPayload());
  var opener = element.shadowRoot.querySelector(".brand-opener");

  assert.equal(
    element.shadowRoot.querySelector(".toolbar").className,
    "toolbar position-bottom",
    "Collapsed bar must carry neither the expanded nor the drawer modifier.",
  );
  assert.equal(
    opener.querySelector(".brand-text").textContent,
    "Yii Debugger",
    "Opener must show the payload title.",
  );
  assert.equal(
    opener.querySelector("img").getAttribute("src"),
    "/debug/logo.svg",
    "Opener must show the payload logo.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".panels"),
    null,
    "Collapsed bar must not render the inline strip.",
  );

  element.remove();
});

test("the collapsed opener falls back to the default product name", () => {
  collapsed();

  var element = renderToolbar(fullPayload({ title: undefined }));

  assert.equal(
    element.shadowRoot.querySelector(".brand-text").textContent,
    "Yii Debugger",
    "Missing title must fall back to the default.",
  );

  element.remove();
});

test("an expanded toolbar renders brand, profiling chip, AJAX panel, strip and controls", () => {
  expanded();

  var element = renderToolbar(fullPayload());
  var root = element.shadowRoot;
  var order = Array.prototype.map.call(
    root.querySelector(".bar").children,
    function (node) {
      return node.className.split(" ")[0];
    },
  );

  assert.deepEqual(
    order,
    ["brand", "panel", "panel", "panels", "controls"],
    "Bar order: brand, profiling chip, AJAX panel, strip, controls.",
  );
  assert.equal(
    root.querySelector(".toolbar").className,
    "toolbar position-bottom expanded",
    "Expanded bar must carry the expanded modifier only.",
  );
  assert.equal(
    root.querySelectorAll(".panels > .panel").length,
    2,
    "Profiling must be pulled out of the inline strip.",
  );
  assert.equal(
    root.querySelector(".ajax-panel .panel-title").textContent,
    "AJAX",
    "AJAX chip must sit outside the strip.",
  );

  element.remove();
});

test("a payload without panels still renders the surrounding chrome", () => {
  expanded();

  var element = renderToolbar(fullPayload({ items: undefined }));

  assert.equal(
    element.shadowRoot.querySelectorAll(".panels > .panel").length,
    0,
    "Strip must be empty.",
  );
  assert.equal(
    element.shadowRoot.querySelectorAll(".bar > .panel").length,
    1,
    "Only the AJAX chip may remain.",
  );

  element.remove();
});

test("the docking position comes from the attribute before the payload", () => {
  expanded();

  var element = renderToolbar(fullPayload({ position: "top" }));

  assert.equal(
    element.getAttribute("data-position"),
    "top",
    "Payload position must be mirrored onto the host.",
  );
  assert.ok(
    element.shadowRoot
      .querySelector(".toolbar")
      .classList.contains("position-top"),
    "Top dock must be reflected in the class list.",
  );

  element.remove();

  var pinned = renderToolbar(fullPayload({ position: "top" }), {
    "data-position": "bottom",
  });

  assert.ok(
    pinned.shadowRoot
      .querySelector(".toolbar")
      .classList.contains("position-bottom"),
    "Attribute must win over the payload.",
  );

  pinned.remove();
});

test("the logo falls back to the payload fallback and then to the brand mark", () => {
  collapsed();

  var fallback = renderToolbar(
    fullPayload({ logo: undefined, logoFallback: "/debug/fallback.svg" }),
  );

  assert.equal(
    fallback.shadowRoot.querySelector("img").getAttribute("src"),
    "/debug/fallback.svg",
    "Fallback source must be used.",
  );

  fallback.remove();

  var mark = renderToolbar(fullPayload({ logo: undefined }));

  assert.equal(
    mark.shadowRoot.querySelector(".brand-mark").textContent,
    "Y",
    "Without any source the lettermark must be drawn.",
  );

  mark.remove();
});

test("the brand links to the configuration page and to phpinfo", () => {
  expanded();

  var element = renderToolbar(fullPayload());
  var yii = element.shadowRoot.querySelector(".brand-link-yii");
  var php = element.shadowRoot.querySelector(".brand-link-php");

  assert.equal(
    yii.getAttribute("data-debug-url"),
    "http://localhost:3000/debug/config?yii_debug_theme=light",
    "Configuration link must carry the stamped theme.",
  );
  assert.equal(
    php.getAttribute("href"),
    "http://localhost:3000/debug/phpinfo?yii_debug_theme=light",
    "phpinfo link must carry the stamped theme.",
  );
  assert.equal(
    element.shadowRoot.querySelectorAll(".brand-divider").length,
    1,
    "Divider must separate the two brand links.",
  );

  element.remove();
});

test("the brand falls back to the index URL and drops the PHP half", () => {
  expanded();

  var element = renderToolbar(
    fullPayload({
      configUrl: undefined,
      indexUrl: "/debug",
      phpVersion: undefined,
    }),
  );

  assert.equal(
    element.shadowRoot.querySelector(".brand-link-yii").getAttribute("href"),
    "http://localhost:3000/debug?yii_debug_theme=light",
    "Index URL must back the configuration link.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".brand-link-php"),
    null,
    "Without a PHP version the PHP half must be absent.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".brand-divider"),
    null,
    "Divider must be absent with a single brand link.",
  );

  element.remove();
});

test("the brand renders statically when no destination is available", () => {
  expanded();

  var element = renderToolbar(
    fullPayload({ configUrl: undefined, phpInfoUrl: undefined }),
  );

  assert.equal(
    element.shadowRoot.querySelectorAll(".brand-static").length,
    2,
    "Both brand halves must be static.",
  );

  element.remove();
});

test("a panel is a link only while no metric carries its own URL", () => {
  expanded();

  var element = renderToolbar(fullPayload());
  var root = element.shadowRoot;

  assert.equal(
    root.querySelector('[title="Database"]').tagName,
    "A",
    "A panel whose metrics are inert must be the link itself.",
  );
  assert.equal(
    root.querySelector('[title="Request"]').tagName,
    "DIV",
    "A panel with linked metrics must stay a group.",
  );
  assert.equal(
    root.querySelector('[title="Request"]').getAttribute("role"),
    "group",
    "The group must expose its role.",
  );

  element.remove();
});

test("a linked panel with linked metrics wraps its label in an inner link", () => {
  expanded();

  var element = renderToolbar(fullPayload());

  element.data.items = [
    {
      icon: "logs",
      id: "logs",
      items: [
        {
          label: "Errors",
          status: "danger",
          url: "/debug/logs?level=error",
          value: "2",
        },
      ],
      title: "Logs",
      url: "/debug/logs",
    },
  ];
  element.render();

  var panel = element.shadowRoot.querySelector('[title="Logs"]');

  assert.equal(panel.tagName, "DIV", "Panel must stay a group.");
  assert.equal(
    panel.querySelector(".panel-link").getAttribute("data-debug-url"),
    "/debug/logs",
    "Label must become its own drawer trigger.",
  );
  assert.equal(
    panel.querySelector(".panel-link").getAttribute("href"),
    "http://localhost:3000/debug/logs?yii_debug_theme=light",
    "Navigable link must carry the stamped theme.",
  );
  assert.equal(
    panel.querySelector(".panel-link").getAttribute("aria-label"),
    "Logs",
    "Inner link must be labelled with the panel title.",
  );

  element.remove();
});

test("panel titles fall back to the identifier and then to a generic label", () => {
  expanded();

  var element = renderToolbar(fullPayload());

  assert.match(
    element.renderPanel({ id: "queue" }),
    /class="panel" title="queue"/,
    "A non-string title must fall back to the identifier.",
  );
  assert.match(
    element.renderPanel({}),
    /class="panel" title="Panel"/,
    "Without title and identifier a generic label must be used.",
  );
  assert.match(
    element.renderPanel({ id: "queue", title: "" }),
    /aria-label="queue"/,
    "An empty title must still label the group with the identifier.",
  );
  assert.match(
    element.renderPanel({ title: "" }),
    /aria-label="Panel"/,
    "An empty title without identifier must use the generic label.",
  );
  assert.equal(
    element.renderPanel({ title: "" }).indexOf("panel-title"),
    -1,
    "An empty title must not render a caption.",
  );

  element.remove();
});

test("metrics render an icon, a label, or neither", () => {
  expanded();

  var element = renderToolbar(fullPayload());
  var root = element.shadowRoot;

  assert.ok(
    root.querySelector('[title="Database"] .metric-icon'),
    "An icon metric must render its glyph.",
  );
  assert.equal(
    root.querySelector('[title="Profiling"] .metric-label').textContent,
    "Total",
    "A labelled metric must render its caption.",
  );
  assert.equal(
    root.querySelector('[title="Database"] .metric-value').className,
    "metric-value badge-default",
    "A metric without status must fall back to the default badge.",
  );
  assert.match(
    element.renderPanel({ id: "queue", items: [{ value: "0" }] }),
    /<span class="metric"><span class="metric-value badge-default">0<\/span><\/span>/,
    "A metric with neither icon nor label must render only its value.",
  );

  element.remove();
});

test("a metric pointing at the open panel is marked active", () => {
  expanded();

  var element = renderToolbar(fullPayload());

  element.activeUrl = "/debug/request";
  element.render();

  var root = element.shadowRoot;

  assert.ok(
    root.querySelector('[title="Request"]').classList.contains("panel-active"),
    "Owning panel must be marked active.",
  );
  assert.ok(
    root
      .querySelector('[data-item-id="status"]')
      .classList.contains("metric-active"),
    "Matching metric must be marked active.",
  );
  assert.equal(
    root.querySelector('[title="Database"]').classList.contains("panel-active"),
    false,
    "Unrelated panels must stay inactive.",
  );

  element.remove();
});

test("a panel whose own URL is open is marked active", () => {
  expanded();

  var element = renderToolbar(fullPayload());

  element.activeUrl = "/debug/db";
  element.render();

  assert.ok(
    element.shadowRoot
      .querySelector('[title="Database"]')
      .classList.contains("panel-active"),
    "Panel URL must be compared against the open URL.",
  );
  assert.equal(
    element.isPanelActive({ id: "db", url: "/debug/db" }),
    true,
    "A panel without metrics must still match on its own URL.",
  );

  element.remove();
});

test("icons resolve from the built-in set, then the payload base URL, then nothing", () => {
  expanded();

  var element = renderToolbar(fullPayload());

  assert.match(
    element.iconHtml("db", "panel-icon"),
    /mask-image:url\(data:image\/svg\+xml/,
    "Built-in glyphs must be inlined.",
  );
  assert.match(
    element.iconHtml("inertia-custom", "panel-icon"),
    /mask-image:url\(\/assets\/icons\/inertia-custom\.svg\)/,
    "Unknown glyphs must resolve against the payload base URL.",
  );
  assert.equal(
    element.iconHtml("", "panel-icon"),
    "",
    "No name means no glyph.",
  );

  element.data.iconBaseUrl = undefined;

  assert.equal(
    element.iconHtml("inertia-custom", "panel-icon"),
    "",
    "Without a base URL an unknown glyph must be dropped.",
  );

  element.data = null;

  assert.equal(
    element.iconHtml("db", "panel-icon"),
    "",
    "Without a payload no glyph may be drawn.",
  );

  element.remove();
});

test("controls expose the theme toggle, the external link and the collapse button", () => {
  expanded();

  var element = renderToolbar(fullPayload());
  var controls = element.shadowRoot.querySelector(".controls");

  assert.equal(
    controls.querySelector(".toggle-theme").getAttribute("aria-label"),
    "Switch to dark theme",
    "Toggle must announce the theme it switches to.",
  );
  assert.equal(
    controls.querySelector(".control.disabled").getAttribute("aria-label"),
    "Open a panel first",
    "Without an open panel the external link must be disabled.",
  );
  assert.equal(
    controls.querySelector(".close-drawer"),
    null,
    "Without an open drawer the close button must be absent.",
  );
  assert.equal(
    controls.querySelector(".toggle-toolbar").getAttribute("title"),
    "Collapse toolbar",
    "Expanded bar must offer to collapse.",
  );

  element.activeUrl = "/debug/db";
  element.render();

  assert.equal(
    element.shadowRoot
      .querySelector(".controls a.control")
      .getAttribute("target"),
    "_blank",
    "External link must open in a new tab.",
  );

  element.expanded = false;

  assert.match(
    element.renderControls(),
    /title="Expand toolbar"/,
    "A collapsed state must offer to expand.",
  );

  element.remove();
});

test("an error replaces the bar and clears the drawer", () => {
  expanded();

  var element = renderToolbar(fullPayload());

  element.drawerRoot.innerHTML = "<span>stale</span>";
  element.renderError("Debug data is <gone>.");

  assert.equal(
    element.shadowRoot.querySelector(".toolbar").className,
    "toolbar expanded",
    "Error state must drop the position modifier.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".error-message").textContent,
    "Debug data is <gone>.",
    "Message must be escaped, not interpreted.",
  );
  assert.equal(element.drawerRoot.innerHTML, "", "Drawer must be emptied.");
  assert.equal(
    element.drawerPosition,
    null,
    "Drawer position must be forgotten.",
  );

  element.remove();
});

test("the shadow skeleton and its stylesheet are built once", () => {
  expanded();

  var element = renderToolbar(fullPayload());
  var root = element.toolbarRoot;

  element.ensureShadowSkeleton();

  assert.equal(element.toolbarRoot, root, "Skeleton must be reused.");
  assert.equal(
    element.shadowRoot.querySelectorAll("style").length,
    1,
    "Only one stylesheet may be injected.",
  );
  assert.equal(
    element.contentRoot,
    root,
    "Legacy alias must point at the skeleton.",
  );
  assert.equal(
    element.shadowRoot.querySelector("style").textContent,
    element.getStyles(),
    "Injected stylesheet must be the shadow stylesheet.",
  );
  assert.ok(
    element.getStyles().indexOf("yii-debug-toolbar") !== -1,
    "Shadow stylesheet must be the authored one.",
  );

  element.remove();
});
